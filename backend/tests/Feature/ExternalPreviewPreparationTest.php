<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\Release;
use App\Models\User;
use App\Services\PreviewBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class ExternalPreviewPreparationTest extends TestCase
{
    use RefreshDatabase;

    private string $testRoot;

    private string $packageRoot;

    private string $incomingRoot;

    private string $resultRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testRoot = rtrim(sys_get_temp_dir(), '/\\').'/madlen-external-preview-test-'.Str::random(10);
        $this->packageRoot = $this->testRoot.'/packages';
        $this->incomingRoot = $this->testRoot.'/incoming';
        $this->resultRoot = $this->testRoot.'/results';
        foreach ([$this->packageRoot, $this->incomingRoot, $this->resultRoot] as $directory) {
            File::ensureDirectoryExists($directory);
        }
        file_put_contents($this->packageRoot.'/.madlen-preview-packages', "madlen-preview-packages-v1\n");
        file_put_contents($this->incomingRoot.'/.madlen-preview-incoming', "madlen-preview-incoming-v1\n");
        file_put_contents($this->resultRoot.'/.madlen-preview-results', "madlen-preview-results-v1\n");
        Storage::fake('local');
        config([
            'madlen.release_root' => $this->testRoot.'/local-releases',
            'madlen.preview_execution' => 'external',
            'madlen.external_preview_connected' => false,
            'madlen.preview_ttl_minutes' => 30,
            'madlen.preview_runner.driver' => 'unconfigured',
            'madlen.preview_runner.package_root' => $this->packageRoot,
            'madlen.preview_runner.incoming_root' => $this->incomingRoot,
            'madlen.preview_runner.result_root' => $this->resultRoot,
            'madlen.preview_runner.source_revision' => str_repeat('d', 40),
            'madlen.preview_runner.api_token' => 'isolated-preview-runner-token',
            'madlen.preview_runner.github_repository' => 'example/madlen',
            'madlen.preview_runner.github_workflow' => 'madlen-external-preview.yml',
            'madlen.preview_runner.github_ref' => 'main',
            'madlen.preview_runner.github_token' => 'dispatch-token-never-logged',
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->testRoot)
            && str_starts_with($this->testRoot, rtrim(sys_get_temp_dir(), '/\\').'/madlen-external-preview-test-')) {
            File::deleteDirectory($this->testRoot);
        }
        parent::tearDown();
    }

    public function test_external_mode_is_closed_until_explicitly_connected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        try {
            app(PreviewBuilder::class)->build();
            $this->fail('Eine nicht verbundene externe Vorschau hätte abbrechen müssen.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('nicht verbunden', $error->getMessage());
        }

        $this->assertDatabaseCount('preview_builds', 0);
        $this->get('/api/preview-runner/v1/previews/unknown/package')->assertNotFound();
    }

    public function test_immutable_draft_is_built_returned_and_served_only_to_its_owner(): void
    {
        $this->artisan('madlen:import')->assertSuccessful();
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($owner);

        Storage::disk('local')->put('media/originals/draft-secret.jpg', 'PRIVATE-ORIGINAL-MUST-STAY-HOSTED');
        Storage::disk('local')->put('media/derivatives/draft-preview.webp', 'DRAFT-DERIVATIVE');
        $media = MediaAsset::query()->create([
            'path' => 'media/originals/draft-secret.jpg',
            'derivative_path' => 'media/derivatives/draft-preview.webp',
            'original_name' => 'draft-secret.jpg',
            'mime_type' => 'image/webp',
            'kind' => 'image',
            'source_managed' => false,
            'alt_de' => 'Privates Vorschau-Testbild',
            'alt_en' => 'Private preview test image',
        ]);
        $project = Project::query()->create([
            'slug' => 'external-preview-draft',
            'title_de' => 'Unveränderlicher Entwurf Deutsch',
            'title_en' => 'Immutable English draft',
            'description_de' => 'Noch nicht veröffentlichter Entwurf.',
            'description_en' => 'Draft that is not published yet.',
            'category_id' => Project::query()->firstOrFail()->category_id,
            'cover_media_id' => $media->id,
            'status' => 'draft',
            'position' => 99001,
        ]);
        $project->mediaItems()->create([
            'media_asset_id' => $media->id,
            'role' => 'gallery',
            'side' => 'left',
            'position' => 1,
        ]);

        $stable = $this->testRoot.'/stable-release';
        File::ensureDirectoryExists($stable);
        file_put_contents($stable.'/index.html', '<h1>Aktiver Stand 8</h1>');
        $release = Release::query()->create([
            'version' => 8,
            'status' => 'active',
            'manifest_path' => $this->testRoot.'/stable.json',
            'build_path' => $stable,
            'published_at' => now(),
        ]);

        $this->connectExternalPreview();
        Http::fake(['https://api.github.com/*' => Http::response(null, 204)]);
        $preview = app(PreviewBuilder::class)->build();
        $this->assertSame('queued', $preview->status);
        $this->assertSame('external', $preview->execution_mode);
        $this->assertFileExists($preview->package_path);
        $this->assertSame($preview->package_checksum, hash_file('sha256', $preview->package_path));
        $this->get('/admin/preview/'.$preview->token.'/')->assertStatus(202)->assertSee('Vorschau wartet');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/example/madlen/actions/workflows/madlen-external-preview.yml/dispatches'
            && $request['inputs']['preview_id'] === $preview->id
            && $request['inputs']['package_sha256'] === $preview->package_checksum
            && $request['inputs']['source_revision'] === str_repeat('d', 40));

        $project->update([
            'title_de' => 'Spätere Änderung Deutsch',
            'title_en' => 'Later English change',
        ]);

        $this->get('/api/preview-runner/v1/previews/'.$preview->id.'/package')->assertUnauthorized();
        $this->withToken('isolated-preview-runner-token')
            ->get('/api/preview-runner/v1/previews/'.$preview->id.'/package')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->withToken('isolated-preview-runner-token')
            ->postJson('/api/preview-runner/v1/previews/'.$preview->id.'/status', ['status' => 'building'])
            ->assertOk();
        $this->assertSame('building', $preview->refresh()->status);

        $extracted = $this->testRoot.'/extracted-'.$preview->id;
        File::ensureDirectoryExists($extracted);
        $this->extract($preview->package_path, $extracted);
        $manifest = file_get_contents($extracted.'/content-manifest.json');
        $this->assertStringContainsString('Unveränderlicher Entwurf Deutsch', $manifest);
        $this->assertStringContainsString('Immutable English draft', $manifest);
        $this->assertStringNotContainsString('Spätere Änderung Deutsch', $manifest);
        $this->assertFileExists($extracted.'/media/'.$media->id.'/draft-preview.webp');
        $this->assertFileDoesNotExist($extracted.'/media/'.$media->id.'/draft-secret.jpg');
        $this->assertStringNotContainsString('PRIVATE-ORIGINAL-MUST-STAY-HOSTED', file_get_contents($preview->package_path));

        $externalBuild = $this->testRoot.'/runner-build-'.$preview->id;
        $process = new Process(
            ['npm', 'run', 'build:preview', '--', $extracted, $externalBuild],
            config('madlen.repository_root'),
            [
                'MADLEN_EXPECTED_PREVIEW_ID' => $preview->id,
                'MADLEN_EXPECTED_SOURCE_REVISION' => $preview->source_revision,
                'MADLEN_EXPECTED_EXPIRES_AT' => $preview->expires_at->toIso8601String(),
            ],
        );
        $process->setTimeout(300);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $this->assertFileExists($externalBuild.'/index.html');
        $this->assertFileExists($externalBuild.'/en/index.html');
        $this->assertFileExists($externalBuild.'/portfolio/external-preview-draft/index.html');
        $this->assertFileExists($externalBuild.'/en/portfolio/external-preview-draft/index.html');
        $this->assertFileExists($externalBuild.'/media/'.$media->id.'/draft-preview.webp');
        $this->assertStringContainsString('/admin/preview/'.$preview->token.'/media/'.$media->id.'/', file_get_contents($externalBuild.'/portfolio/external-preview-draft/index.html'));

        [$archive, $resultChecksum] = $this->makeResultArchive($externalBuild, $preview->id);
        $this->withToken('isolated-preview-runner-token')
            ->postJson('/api/preview-runner/v1/previews/'.$preview->id.'/status', [
                'status' => 'ready',
                'result_archive' => basename($archive),
                'result_sha256' => $resultChecksum,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ready');
        $preview->refresh();
        $this->assertSame('ready', $preview->status);
        $this->assertDirectoryExists($preview->build_path);
        $this->assertFileDoesNotExist($archive);

        $this->withToken('isolated-preview-runner-token')
            ->postJson('/api/preview-runner/v1/previews/'.$preview->id.'/status', [
                'status' => 'ready',
                'result_archive' => basename($archive),
                'result_sha256' => $resultChecksum,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ready');

        auth()->logout();
        $base = '/admin/preview/'.$preview->token;
        $mediaPath = $base.'/media/'.$media->id.'/draft-preview.webp';
        $this->get($base.'/')->assertNotFound();
        $this->get($mediaPath)->assertNotFound();
        $this->actingAs($otherUser)->get($base.'/')->assertNotFound();
        $this->actingAs($owner)->get($base.'/')->assertOk()->assertHeader('X-Robots-Tag');
        $this->get($base.'/portfolio/external-preview-draft/')
            ->assertOk()
            ->assertHeader('X-Robots-Tag');
        $this->get($base.'/en/portfolio/external-preview-draft/')
            ->assertOk()
            ->assertHeader('X-Robots-Tag');
        $this->get($mediaPath)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(
            'DRAFT-DERIVATIVE',
            file_get_contents($preview->build_path.'/media/'.$media->id.'/draft-preview.webp'),
        );
        $this->assertStringContainsString(
            'Unveränderlicher Entwurf Deutsch',
            file_get_contents($preview->build_path.'/portfolio/external-preview-draft/index.html'),
        );
        $this->assertStringContainsString(
            'Immutable English draft',
            file_get_contents($preview->build_path.'/en/portfolio/external-preview-draft/index.html'),
        );

        $this->assertSame('active', $release->refresh()->status);
        $this->assertSame(8, $release->version);
        $this->assertStringContainsString('Aktiver Stand 8', file_get_contents($stable.'/index.html'));
        $this->assertDatabaseCount('production_publications', 0);

        $outOfOrder = app(PreviewBuilder::class)->build();
        [$wrongArchive, $wrongChecksum] = $this->makeResultArchive($externalBuild, $outOfOrder->id);
        $this->withToken('isolated-preview-runner-token')
            ->postJson('/api/preview-runner/v1/previews/'.$outOfOrder->id.'/status', [
                'status' => 'ready',
                'result_archive' => basename($wrongArchive),
                'result_sha256' => $wrongChecksum,
            ])
            ->assertStatus(409);
        $this->assertSame('failed', $outOfOrder->refresh()->status);
        $this->assertSame('ready', $preview->refresh()->status);
    }

    public function test_failed_and_expired_previews_report_german_states_and_remove_only_their_files(): void
    {
        $this->artisan('madlen:import')->assertSuccessful();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->connectExternalPreview();
        Http::fake(['https://api.github.com/*' => Http::response(null, 204)]);

        $failed = app(PreviewBuilder::class)->build();
        $this->withToken('isolated-preview-runner-token')
            ->postJson('/api/preview-runner/v1/previews/'.$failed->id.'/status', [
                'status' => 'failed',
                'error' => 'Simulierter Fehler des isolierten Runners.',
            ])
            ->assertOk();
        $this->assertSame('failed', $failed->refresh()->status);
        $this->assertStringContainsString('Simulierter Fehler', $failed->error_message);
        $this->get('/admin/preview/'.$failed->token.'/')
            ->assertStatus(422)
            ->assertSee('Vorschau fehlgeschlagen')
            ->assertSee('öffentliche Website wurde nicht verändert');

        $expired = app(PreviewBuilder::class)->build();
        $expiredPackage = $expired->package_path;
        $expiredRequest = $this->packageRoot.'/requests/'.$expired->id;
        $expired->update(['expires_at' => now()->subMinute()]);
        $this->get('/admin/preview/'.$expired->token.'/')
            ->assertStatus(410)
            ->assertSee('Vorschau abgelaufen');
        $this->get('/admin/preview/'.$expired->token.'/media/example/image.webp')->assertNotFound();
        $this->assertSame('expired', $expired->refresh()->status);
        $this->assertFileDoesNotExist($expiredPackage);
        $this->assertDirectoryDoesNotExist($expiredRequest);
        $this->assertFileExists($failed->package_path);
        $this->assertSame('failed', $failed->refresh()->status);

        $this->artisan('madlen:previews:cleanup')->assertSuccessful();
    }

    private function connectExternalPreview(): void
    {
        config([
            'madlen.preview_execution' => 'external',
            'madlen.external_preview_connected' => true,
            'madlen.preview_runner.driver' => 'github-actions',
        ]);
    }

    private function extract(string $archive, string $destination): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archive) === true);
        $this->assertTrue($zip->extractTo($destination));
        $zip->close();
    }

    private function makeResultArchive(string $source, string $previewId): array
    {
        $archive = $this->incomingRoot.'/madlen-preview-result-'.$previewId.'.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) === true);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($entry->isFile()) {
                $zip->addFile($entry->getPathname(), str_replace('\\', '/', substr($entry->getPathname(), strlen($source) + 1)));
            }
        }
        $zip->close();

        return [$archive, hash_file('sha256', $archive)];
    }
}
