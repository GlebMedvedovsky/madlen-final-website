<?php

namespace Tests\Feature;

use App\Data\ProjectPreviewSnapshot;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Models\MediaAsset;
use App\Models\PreviewBuild;
use App\Models\Project;
use App\Models\User;
use App\Services\ExternalPreviewStorage;
use App\Services\PreviewBuilder;
use App\Services\ProjectPreviewSnapshotFactory;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProjectEditorPreviewTest extends TestCase
{
    use RefreshDatabase;

    private string $testRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testRoot = rtrim(sys_get_temp_dir(), '/\\').'/madlen-project-editor-preview-'.Str::random(10);
        $roots = [
            'package_root' => $this->testRoot.'/packages',
            'incoming_root' => $this->testRoot.'/incoming',
            'result_root' => $this->testRoot.'/results',
        ];
        foreach ($roots as $root) {
            File::ensureDirectoryExists($root);
        }
        file_put_contents($roots['package_root'].'/.madlen-preview-packages', "madlen-preview-packages-v1\n");
        file_put_contents($roots['incoming_root'].'/.madlen-preview-incoming', "madlen-preview-incoming-v1\n");
        file_put_contents($roots['result_root'].'/.madlen-preview-results', "madlen-preview-results-v1\n");

        config([
            'madlen.preview_execution' => 'external',
            'madlen.external_preview_connected' => true,
            'madlen.preview_ttl_minutes' => 30,
            'madlen.preview_runner.driver' => 'github-actions',
            'madlen.preview_runner.package_root' => $roots['package_root'],
            'madlen.preview_runner.incoming_root' => $roots['incoming_root'],
            'madlen.preview_runner.result_root' => $roots['result_root'],
            'madlen.preview_runner.source_revision' => str_repeat('e', 40),
            'madlen.preview_runner.api_token' => 'project-editor-preview-token',
            'madlen.preview_runner.github_repository' => 'example/madlen',
            'madlen.preview_runner.github_workflow' => 'madlen-external-preview.yml',
            'madlen.preview_runner.github_ref' => 'main',
            'madlen.preview_runner.github_token' => 'never-sent-test-token',
        ]);
        Storage::fake('local');
        Http::fake(['https://api.github.com/*' => Http::response(null, 204)]);
    }

    protected function tearDown(): void
    {
        if (isset($this->testRoot)
            && str_starts_with($this->testRoot, rtrim(sys_get_temp_dir(), '/\\').'/madlen-project-editor-preview-')) {
            File::deleteDirectory($this->testRoot);
        }
        parent::tearDown();
    }

    public function test_editor_preview_packages_unsaved_form_state_without_persisting_it(): void
    {
        [$user, $project, $cover, $firstGallery, $secondGallery] = $this->fixture();
        $original = $project->fresh();
        $originalGallery = $project->mediaItems()->get()->map->only(['media_asset_id', 'position', 'side'])->all();
        $editorUrl = EditProject::getUrl(['record' => $project]);
        $requestId = (string) Str::uuid();

        $component = Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->assertActionExists('preview', function (Action $action): bool {
                $attributes = $action->getExtraAttributes();
                $handler = (string) ($attributes['x-on:click.capture'] ?? '');

                return str_contains($handler, 'window.open')
                    && str_contains($handler, '__madlenProjectPreviewPending')
                    && str_contains($handler, 'stopImmediatePropagation')
                    && str_contains($handler, 'createElement')
                    && str_contains($handler, "Livewire.hook('request'")
                    && str_contains($handler, 'statusCode === 419')
                    && str_contains($handler, 'preventDefault?.()')
                    && str_contains($handler, 'previewRequestAttempt')
                    && ! str_contains($handler, 'window.location.reload')
                    && ! str_contains($handler, 'innerHTML')
                    && filled($action->getLivewireClickHandler());
            })
            ->set('previewRequestId', $requestId)
            ->set('previewRequestAttempt', 1)
            ->fillForm([
                'title_de' => 'Ungespeicherter Vorschautitel Deutsch',
                'title_en' => 'Unsaved English preview title',
                'description_de' => 'Dieser Text existiert nur im unveränderlichen Vorschau-Snapshot.',
                'description_en' => 'This text exists only in the immutable preview snapshot.',
                'cover_media_id' => $cover->id,
                'position' => 7,
                'mediaItems' => [
                    ['media_asset_id' => $secondGallery->id, 'side' => 'right'],
                    ['media_asset_id' => $firstGallery->id, 'side' => 'left'],
                ],
            ])
            ->callAction('preview')
            ->assertHasNoFormErrors()
            ->assertNoRedirect()
            ->assertSet('data.title_de', 'Ungespeicherter Vorschautitel Deutsch')
            ->assertSet('data.description_en', 'This text exists only in the immutable preview snapshot.')
            ->assertNotified('Projektvorschau wurde gestartet');

        $preview = PreviewBuild::query()->latest()->firstOrFail();
        $this->assertSame('queued', $preview->status);
        $this->assertSame('portfolio/'.$project->slug, $preview->target_path);
        $this->assertSame($requestId, $preview->request_id);
        $this->assertTrue(collect(Schema::getIndexes('preview_builds'))->contains(
            fn (array $index): bool => $index['name'] === 'preview_builds_request_id_unique' && $index['unique'],
        ));
        $manifest = json_decode(
            file_get_contents(app(ExternalPreviewStorage::class)->manifestPath($preview->id)),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $snapshot = collect($manifest['projects'])->firstWhere('slug', $project->slug);
        $this->assertSame('Ungespeicherter Vorschautitel Deutsch', $snapshot['title']['de']);
        $this->assertSame('Unsaved English preview title', $snapshot['title']['en']);
        $this->assertSame($cover->publicPath(), $snapshot['cover']);
        $this->assertSame(
            [$secondGallery->publicPath(), $firstGallery->publicPath()],
            array_column($snapshot['images'], 'src'),
        );
        $this->assertSame([1, 2], array_column($snapshot['images'], 'order'));
        $this->assertSame(['right', 'left'], array_column($snapshot['images'], 'side'));

        $fresh = $project->fresh();
        $this->assertSame($original->title_de, $fresh->title_de);
        $this->assertSame($original->description_en, $fresh->description_en);
        $this->assertSame($original->cover_media_id, $fresh->cover_media_id);
        $this->assertSame($original->position, $fresh->position);
        $this->assertSame($original->status, $fresh->status);
        $this->assertSame($originalGallery, $fresh->mediaItems()->get()->map->only(['media_asset_id', 'position', 'side'])->all());
        $this->assertDatabaseCount('releases', 0);
        $this->assertAuthenticatedAs($user);
        $this->assertSame($editorUrl, EditProject::getUrl(['record' => $project]));

        $targetUrl = '/admin/preview/'.$preview->token.'/'.$preview->target_path;
        $this->get($targetUrl)
            ->assertStatus(202)
            ->assertSee('Vorschau wartet')
            ->assertSee('http-equiv="refresh"', escape: false);
        $this->get('/admin/preview/'.$preview->token.'/portfolio/anderes-projekt')->assertNotFound();
        $this->actingAs(User::factory()->create())->get($targetUrl)->assertNotFound();

        $this->actingAs($user);
        $component
            ->set('previewRequestAttempt', 2)
            ->fillForm(['title_de' => 'Späterer Stand nach verlorener Antwort'])
            ->callAction('preview')
            ->assertNoRedirect()
            ->assertNotified('Bereits gestartete Projektvorschau gefunden');
        $this->assertDatabaseCount('preview_builds', 1);
        $this->assertSame($preview->id, PreviewBuild::query()->sole()->id);
        $manifestAfterRetry = json_decode(
            file_get_contents(app(ExternalPreviewStorage::class)->manifestPath($preview->id)),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $retriedSnapshot = collect($manifestAfterRetry['projects'])->firstWhere('slug', $project->slug);
        $this->assertSame('Ungespeicherter Vorschautitel Deutsch', $retriedSnapshot['title']['de']);
        $this->assertSame($original->title_de, $project->fresh()->title_de);

        $component
            ->fillForm(['title_de' => 'Bewusst gespeicherter Titel'])
            ->call('save', false)
            ->assertHasNoFormErrors();
        $this->assertSame('Bewusst gespeicherter Titel', $project->fresh()->title_de);
        $this->assertSame($original->status, $project->fresh()->status);
    }

    public function test_csrf_refresh_requires_admin_authentication_and_is_not_cached(): void
    {
        [$user] = $this->fixture();

        $this->app['auth']->guard()->logout();
        $this->getJson(route('admin.session.csrf'))
            ->assertNotFound();

        $csrfToken = str_repeat('a', 40);
        $response = $this->actingAs($user)
            ->withSession(['_token' => $csrfToken])
            ->getJson(route('admin.session.csrf'));

        $response
            ->assertOk()
            ->assertExactJson(['csrfToken' => $csrfToken])
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache');
    }

    public function test_validation_failure_and_duplicate_launch_keep_the_form_and_database_unchanged(): void
    {
        [$user, $project] = $this->fixture();
        $originalTitle = $project->title_de;
        $validationRequestId = (string) Str::uuid();

        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->set('previewRequestId', $validationRequestId)
            ->set('previewRequestAttempt', 1)
            ->fillForm(['title_de' => ''])
            ->callAction('preview')
            ->assertHasFormErrors(['title_de' => 'required'])
            ->assertSet('data.title_de', '')
            ->assertNoRedirect();

        $this->assertDatabaseCount('preview_builds', 0);
        $this->assertSame($originalTitle, $project->fresh()->title_de);

        $lockedRequestId = (string) Str::uuid();
        $lock = Cache::lock('madlen-project-preview:'.$user->id.':'.$project->id.':'.$lockedRequestId, 180);
        $this->assertTrue($lock->get());
        try {
            Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
                ->set('previewRequestId', $lockedRequestId)
                ->set('previewRequestAttempt', 1)
                ->fillForm(['title_de' => 'Nicht gespeicherter Doppelklick'])
                ->callAction('preview')
                ->assertSet('data.title_de', 'Nicht gespeicherter Doppelklick')
                ->assertNoRedirect()
                ->assertNotified('Vorschau wird bereits vorbereitet');
        } finally {
            $lock->release();
        }

        $this->assertDatabaseCount('preview_builds', 0);
        $this->assertDatabaseCount('releases', 0);
        $this->assertSame($originalTitle, $project->fresh()->title_de);
        $this->assertAuthenticatedAs($user);
    }

    public function test_build_failure_keeps_unsaved_editor_state_and_does_not_publish(): void
    {
        [$user, $project] = $this->fixture();
        $builder = Mockery::mock(PreviewBuilder::class);
        $builder->shouldReceive('build')
            ->once()
            ->with(Mockery::type(ProjectPreviewSnapshot::class), Mockery::type('string'))
            ->andThrow(new RuntimeException('Simulierter Vorschaufehler'));
        $this->app->instance(PreviewBuilder::class, $builder);

        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->set('previewRequestId', (string) Str::uuid())
            ->set('previewRequestAttempt', 1)
            ->fillForm(['title_en' => 'Unsaved state after failed build'])
            ->callAction('preview')
            ->assertSet('data.title_en', 'Unsaved state after failed build')
            ->assertNoRedirect()
            ->assertNotified('Vorschau konnte nicht erstellt werden');

        $this->assertNotSame('Unsaved state after failed build', $project->fresh()->title_en);
        $this->assertDatabaseCount('preview_builds', 0);
        $this->assertDatabaseCount('releases', 0);
        $this->assertSame('published', $project->fresh()->status);
        $this->assertAuthenticatedAs($user);
    }

    public function test_unsupported_temporary_media_state_is_rejected_without_falling_back_to_saved_media(): void
    {
        [$user, $project, $cover] = $this->fixture();
        $originalGallery = $project->mediaItems()->get()->map->only(['media_asset_id', 'position', 'side'])->all();

        try {
            app(ProjectPreviewSnapshotFactory::class)->make($project, [
                'slug' => $project->slug,
                'category_id' => $project->category_id,
                'cover_media_id' => $cover->id,
                'position' => $project->position,
                'title_de' => 'Nicht gespeicherter Titel',
                'title_en' => 'Unsaved title',
                'description_de' => 'Nicht gespeicherte Beschreibung.',
                'description_en' => 'Unsaved description.',
                'mediaItems' => [
                    ['media_asset_id' => 'livewire-tmp:not-yet-stored', 'side' => 'left'],
                ],
            ]);
            $this->fail('An unsupported temporary media reference must not create a preview snapshot.');
        } catch (ValidationException $error) {
            $this->assertSame(
                'Ein Galeriebild ist noch nicht als vorhandenes Medienobjekt verfügbar.',
                $error->errors()['data.mediaItems.0.media_asset_id'][0] ?? null,
            );
        }

        $this->assertSame($originalGallery, $project->fresh()->mediaItems()->get()->map->only(['media_asset_id', 'position', 'side'])->all());
        $this->assertDatabaseCount('preview_builds', 0);
        $this->assertDatabaseCount('releases', 0);
        $this->assertAuthenticatedAs($user);
    }

    public function test_only_the_recorded_target_can_show_pending_failed_and_expired_status(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = Str::random(48);
        $buildRoot = $this->testRoot.'/local-build';
        File::ensureDirectoryExists($buildRoot.'/portfolio/editor-preview-synthetic');
        file_put_contents($buildRoot.'/portfolio/editor-preview-synthetic/index.html', '<h1>Gewählte Projektvorschau</h1>');
        $preview = PreviewBuild::query()->create([
            'token' => $token,
            'execution_mode' => 'local',
            'status' => 'building',
            'manifest_path' => $this->testRoot.'/manifest.json',
            'build_path' => $buildRoot,
            'target_path' => 'portfolio/editor-preview-synthetic',
            'request_id' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'expires_at' => now()->addMinutes(20),
            'progress_message' => 'Das gewählte Projekt wird gebaut.',
        ]);
        $target = '/admin/preview/'.$token.'/portfolio/editor-preview-synthetic';

        $this->actingAs($owner)->get($target)->assertStatus(202)->assertSee('Das gewählte Projekt wird gebaut.');
        $this->get('/admin/preview/'.$token.'/portfolio/falsches-projekt')->assertNotFound();
        $this->actingAs($other)->get($target)->assertNotFound();

        $preview->update(['status' => 'failed', 'progress_message' => 'Build fehlgeschlagen.']);
        $this->actingAs($owner)->get($target)->assertStatus(422)->assertSee('Build fehlgeschlagen.');

        $preview->update(['status' => 'ready', 'progress_message' => null, 'completed_at' => now()]);
        $this->get($target)->assertOk();
        $this->assertStringContainsString('Gewählte Projektvorschau', file_get_contents($buildRoot.'/portfolio/editor-preview-synthetic/index.html'));

        $preview->update(['status' => 'expired', 'expires_at' => now()->subMinute()]);
        $this->get($target)->assertStatus(410)->assertSee('Vorschau abgelaufen');
    }

    /**
     * @return array{User, Project, MediaAsset, MediaAsset, MediaAsset}
     */
    private function fixture(): array
    {
        $this->artisan('madlen:import')->assertSuccessful();
        $user = User::factory()->create();
        $this->actingAs($user);
        $assets = collect(['cover', 'gallery-one', 'gallery-two'])->map(function (string $name): MediaAsset {
            $path = 'media/derivatives/'.$name.'.webp';
            Storage::disk('local')->put($path, strtoupper($name));

            return MediaAsset::query()->create([
                'path' => 'media/originals/'.$name.'.jpg',
                'derivative_path' => $path,
                'original_name' => $name.'.jpg',
                'mime_type' => 'image/webp',
                'kind' => 'image',
                'source_managed' => false,
                'alt_de' => 'Testbild '.$name,
                'alt_en' => 'Test image '.$name,
            ]);
        });
        $project = Project::query()->create([
            'slug' => 'editor-preview-synthetic',
            'title_de' => 'Gespeicherter deutscher Titel',
            'title_en' => 'Saved English title',
            'description_de' => 'Gespeicherte deutsche Beschreibung.',
            'description_en' => 'Saved English description.',
            'category_id' => Project::query()->firstOrFail()->category_id,
            'cover_media_id' => $assets[0]->id,
            'status' => 'published',
            'position' => 9000,
        ]);
        $project->mediaItems()->create([
            'media_asset_id' => $assets[1]->id,
            'role' => 'gallery',
            'position' => 1,
            'side' => 'left',
        ]);

        return [$user, $project, $assets[0], $assets[1], $assets[2]];
    }
}
