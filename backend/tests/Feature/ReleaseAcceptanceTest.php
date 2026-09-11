<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Models\{Project, ProductionPublication, User};
use App\Services\{ProductionPublisher, ProductionReleaseManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{File, Http, Storage};
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class ReleaseAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/madlen-release-acceptance-'.Str::random(12);
        foreach (['packages', 'incoming', 'static'] as $dir) File::ensureDirectoryExists($this->root.'/'.$dir);
        File::put($this->root.'/incoming/.madlen-publisher-incoming', "madlen-production-incoming-v1\n");
        File::put($this->root.'/static/.madlen-publisher-root', "madlen-production-root-v1\n");
        config([
            'madlen.production_connected' => true, 'madlen.production_publisher' => 'github-actions',
            'madlen.publisher.package_root' => $this->root.'/packages',
            'madlen.publisher.incoming_root' => $this->root.'/incoming',
            'madlen.publisher.destination_root' => $this->root.'/static',
            'madlen.publisher.source_revision' => str_repeat('a', 40),
            'madlen.publisher.github_repository' => 'example/madlen',
            'madlen.publisher.github_workflow' => 'madlen-production-publisher.yml',
            'madlen.publisher.github_ref' => 'main', 'madlen.publisher.github_token' => 'synthetic',
            'madlen.publisher.api_token' => 'synthetic-api',
        ]);
        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake(['https://api.github.com/*' => Http::response(null, 204)]);
        $this->artisan('madlen:import')->assertSuccessful();
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_real_editor_save_publish_retry_remove_delete_restore_and_rollback(): void
    {
        $template = Project::where('slug', 'renaissance')->firstOrFail();
        $data = $template->only(['category_id', 'cover_media_id', 'title_de', 'title_en', 'description_de', 'description_en', 'position']);
        $data['slug'] = 'release-selected';
        Livewire::test(CreateProject::class)->fillForm($data)->call('create')->assertHasNoFormErrors();
        $project = Project::where('slug', 'release-selected')->firstOrFail();
        $this->assertSame('draft', $project->status);
        $other = $project->replicate(); $other->slug = 'test-unrelated-draft'; $other->save();
        $editor = Livewire::test(EditProject::class, ['record' => $project->id]);
        $request = $editor->get('productionRequestIds.publish');
        $editor->fillForm(['title_de' => 'Aktueller Text aus dem Editor'])
            ->callAction('publish')->assertHasNoFormErrors()->assertNoRedirect();
        $publication = ProductionPublication::sole();
        $this->assertSame($request, $publication->request_id);
        $this->assertSame('draft', $project->refresh()->status);
        $manifest = json_decode(File::get($publication->manifest_path), true);
        $row = collect($manifest['projects'])->firstWhere('slug', $project->slug);
        $this->assertSame('Aktueller Text aus dem Editor', $row['title']['de']);
        $this->assertNotContains($other->slug, array_column($manifest['projects'], 'slug'));
        // Replay the original Livewire request identity even after form data changed.
        $editor->set('productionRequestIds.publish', $request)->fillForm(['title_de' => 'Spätere nicht gespeicherte Änderung'])
            ->callAction('publish')->assertNoRedirect();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('production_publications', 1);
        $this->assertSame('Aktueller Text aus dem Editor', $project->refresh()->title_de);
        $this->finish($publication);
        $release = $publication->refresh()->target_release;
        $this->assertSame('published', $project->refresh()->status);
        $editor->set('productionRequestIds.publish', $request)->callAction('publish');
        $this->assertDatabaseCount('production_publications', 1);

        $remove = Livewire::test(EditProject::class, ['record' => $project->id]);
        $remove->callAction('unpublish');
        $unpublish = ProductionPublication::latest('sequence')->first();
        $this->assertSame('unpublish', $unpublish->operation);
        $this->assertSame('published', $project->refresh()->status);
        $this->assertNotContains($project->slug, array_column(json_decode(File::get($unpublish->manifest_path), true)['projects'], 'slug'));
        $this->finish($unpublish);
        $this->assertSame('unpublished', $project->refresh()->status);
        app(ProductionReleaseManager::class)->rollback($release);
        $this->assertSame('published', $project->refresh()->status);

        Livewire::test(EditProject::class, ['record' => $project->id])->callAction('delete');
        $delete = ProductionPublication::latest('sequence')->first();
        $this->assertFalse($project->refresh()->trashed());
        $this->finish($delete);
        $this->assertTrue($project->refresh()->trashed());
        app(ProductionReleaseManager::class)->rollback($release);
        $this->assertFalse($project->refresh()->trashed());
        $this->assertSame('published', $project->status);
        $other->delete(); $other->restore();
        $this->assertSame('draft', $other->refresh()->status);
    }

    public function test_lost_dispatch_and_parallel_runner_claims_do_not_duplicate_jobs(): void
    {
        Http::fake(['https://api.github.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('synthetic lost reply')]);
        $request = (string) Str::uuid();
        $publication = app(ProductionPublisher::class)->publish($request);
        $this->assertSame('dispatch_unknown', $publication->status);
        $this->assertSame($publication->id, app(ProductionPublisher::class)->publish($request)->id);
        $this->assertDatabaseCount('production_publications', 1);
        $claim = '/api/publisher/v1/publications/'.$publication->id.'/claim';
        $this->withToken('synthetic-api')->postJson($claim, ['runner_id' => '10-1'])->assertOk()->assertJson(['claimed' => true]);
        $this->withToken('synthetic-api')->postJson($claim, ['runner_id' => '11-1'])->assertOk()->assertJson(['claimed' => false]);
        $this->withToken('synthetic-api')->postJson('/api/publisher/v1/publications/'.$publication->id.'/status',
            ['runner_id' => '11-1', 'status' => 'failed'])->assertStatus(409);
        $this->assertSame('building', $publication->refresh()->status);
    }

    public function test_validation_and_disabled_publication_leave_draft_unchanged(): void
    {
        $project = Project::firstOrFail();
        $project->update(['status' => 'draft']);
        Livewire::test(EditProject::class, ['record' => $project->id])->fillForm(['title_en' => ''])
            ->callAction('publish')->assertHasErrors(['data.title_en' => 'required']);
        $this->assertDatabaseCount('production_publications', 0);
        $this->assertSame('draft', $project->refresh()->status);
        config(['madlen.production_connected' => false]);
        Livewire::test(EditProject::class, ['record' => $project->id])->callAction('publish')->assertNoRedirect();
        $this->assertDatabaseCount('production_publications', 0);
        $this->assertSame('draft', $project->refresh()->status);
    }

    public function test_failed_transfer_retry_and_late_runner_do_not_replace_the_stable_site(): void
    {
        $stable = app(ProductionPublisher::class)->publish((string) Str::uuid());
        $this->finish($stable);
        $pointer = readlink($this->root.'/static/current');
        $project = Project::firstOrFail(); $project->update(['status'=>'draft']);
        $job = app(ProductionPublisher::class)->publish((string) Str::uuid(), $project, 'publish');
        $job->update(['status'=>'building', 'runner_id'=>'20-1']);
        config(['madlen.publisher.simulate_transfer_failure'=>true]);
        try { $this->finish($job); $this->fail('Simulated delivery failure must stop activation'); }
        catch (\RuntimeException $error) { $this->assertStringContainsString('Simulierter', $error->getMessage()); }
        $this->assertSame($pointer, readlink($this->root.'/static/current'));
        $this->assertSame('draft', $project->refresh()->status);
        $status = '/api/publisher/v1/publications/'.$job->id.'/status';
        $this->withToken('synthetic-api')->postJson($status, ['runner_id'=>'20-1','status'=>'failed'])->assertOk();
        $checksum = $job->package_checksum;
        $this->artisan('madlen:production:retry', ['publication'=>$job->id,'--runner-stopped'=>true])->assertSuccessful();
        $this->assertDatabaseCount('production_publications', 2);
        $this->assertSame($checksum, $job->refresh()->package_checksum);
        $this->withToken('synthetic-api')->postJson('/api/publisher/v1/publications/'.$job->id.'/claim', ['runner_id'=>'21-1'])->assertOk()->assertJson(['claimed'=>true]);
        $this->withToken('synthetic-api')->postJson($status, ['runner_id'=>'20-1','status'=>'failed'])->assertStatus(409);
        config(['madlen.publisher.simulate_transfer_failure'=>false]);
        $this->finish($job->refresh());
        $this->withToken('synthetic-api')->postJson($status, ['runner_id'=>'21-1','status'=>'failed'])->assertOk()->assertJson(['status'=>'active']);
        $this->assertSame('published', $project->refresh()->status);
        app(ProductionReleaseManager::class)->rollback($stable->fresh()->target_release);
        $this->assertSame($pointer, readlink($this->root.'/static/current'));
        $this->assertSame('draft', $project->refresh()->status);
        $this->withToken('synthetic-api')->postJson('/api/publisher/v1/publications/'.$job->id.'/claim', ['runner_id'=>'22-1'])->assertOk()->assertJson(['claimed'=>false]);
        $this->withToken('synthetic-api')->postJson($status, ['runner_id'=>'21-1','status'=>'failed'])->assertStatus(409);
    }

    public function test_database_failure_after_pointer_switch_restores_the_old_site(): void
    {
        $stable = app(ProductionPublisher::class)->publish((string) Str::uuid());
        $this->finish($stable);
        $pointer = readlink($this->root.'/static/current');
        $project = Project::firstOrFail(); $project->update(['status'=>'draft']);
        $job = app(ProductionPublisher::class)->publish((string) Str::uuid(), $project, 'publish');
        $armed = true;
        Project::updating(function (Project $candidate) use ($project, &$armed): void {
            if ($armed && $candidate->id === $project->id && $candidate->status === 'published') {
                $armed = false;
                throw new \RuntimeException('Synthetic CMS transaction failure');
            }
        });
        try { $this->finish($job); $this->fail('Expected transaction failure'); }
        catch (\RuntimeException $error) { $this->assertSame('Synthetic CMS transaction failure', $error->getMessage()); }
        $this->assertSame($pointer, readlink($this->root.'/static/current'));
        $this->assertSame('draft', $project->refresh()->status);
        $this->assertSame('active', $stable->refresh()->status);
        $this->assertSame('uploading', $job->refresh()->status);
        $archive = $this->root.'/incoming/'.$job->id.'.zip';
        app(ProductionReleaseManager::class)->activate($job->id,$job->sequence,basename($archive),hash_file('sha256',$archive),$job->runner_id);
        $this->assertSame('published', $project->refresh()->status);
    }

    public function test_production_locators_survive_different_cli_and_fastcgi_prefixes(): void
    {
        $job = app(ProductionPublisher::class)->publish((string) Str::uuid());
        $raw = $job->getRawOriginal('manifest_path');
        $this->assertStringStartsWith('madlen-production-storage-v1://', $raw);
        File::copyDirectory($this->root.'/packages', $this->root.'/other-sapi/packages');
        config(['madlen.publisher.package_root'=>$this->root.'/other-sapi/packages']);
        $this->assertStringStartsWith($this->root.'/other-sapi/', $job->refresh()->manifest_path);
        $this->assertSame($job->package_checksum, hash_file('sha256', $job->package_path));
        $this->finish($job);
        $this->assertSame('active', $job->refresh()->status);
    }

    public function test_bulk_delete_is_all_or_nothing_and_restorable_media_stays_protected(): void
    {
        $published = Project::firstOrFail();
        $draft = $published->replicate(['source_key']); $draft->slug='batch-draft'; $draft->status='draft'; $draft->save();
        Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
            ->callTableBulkAction('delete', [$draft, $published]);
        $this->assertFalse($draft->refresh()->trashed());
        $this->assertFalse($published->refresh()->trashed());
        $draft->delete();
        $this->assertTrue($draft->cover->isUsed());
        $draft->restore();
        $this->assertSame('draft', $draft->status);
        $revision = \App\Models\Revision::where('revisionable_id',$draft->id)->latest('id')->firstOrFail();
        $revision->payload = [...$revision->payload,'status'=>'published','slug'=>'unexpected-url','title_de'=>'Restored text'];
        app(\App\Services\RevisionRestorer::class)->restore($revision);
        $this->assertSame('draft',$draft->refresh()->status);
        $this->assertSame('batch-draft',$draft->slug);
        $this->assertSame('Restored text',$draft->title_de);
    }

    /** Synthetic runner output; the full real Astro build is covered separately. */
    private function finish(ProductionPublication $publication): void
    {
        $manifest = File::get($publication->manifest_path);
        $zip = new ZipArchive;
        $archive = $this->root.'/incoming/'.$publication->id.'.zip';
        $zip->open($archive, ZipArchive::CREATE);
        $zip->addFromString('index.html', '<h1>DE</h1>');
        $zip->addFromString('en/index.html', '<h1>EN</h1>');
        $zip->addFromString('sitemap.xml', '<urlset/>');
        $zip->addFromString('.madlen-release.json', json_encode([
            'publicationId' => $publication->id, 'sequence' => $publication->sequence,
            'sourceRevision' => $publication->source_revision, 'contentChecksum' => hash('sha256', $manifest),
        ]));
        $zip->close();
        $publication->update(['status' => 'uploading']);
        app(ProductionReleaseManager::class)->activate($publication->id, $publication->sequence, basename($archive), hash_file('sha256', $archive), $publication->runner_id);
    }
}
