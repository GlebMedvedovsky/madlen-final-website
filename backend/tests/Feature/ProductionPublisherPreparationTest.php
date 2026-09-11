<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\ProductionPublication;
use App\Models\Project;
use App\Services\ProductionPublisher;
use App\Services\ProductionPublicationStatus;
use App\Services\ProductionReleaseManager;
use App\Services\StaticReleaseActivator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class ProductionPublisherPreparationTest extends TestCase
{
    use RefreshDatabase;

    private string $testRoot;

    private string $incomingRoot;

    private string $destinationRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testRoot = rtrim(sys_get_temp_dir(), '/\\').'/madlen-production-publisher-test-'.Str::random(10);
        $this->incomingRoot = $this->testRoot.'/incoming';
        $this->destinationRoot = $this->testRoot.'/destination';
        File::ensureDirectoryExists($this->incomingRoot);
        File::ensureDirectoryExists($this->destinationRoot);
        file_put_contents($this->incomingRoot.'/.madlen-publisher-incoming', "madlen-production-incoming-v1\n");
        file_put_contents($this->destinationRoot.'/.madlen-publisher-root', "madlen-production-root-v1\n");
        Storage::fake('local');
        config([
            'madlen.publisher.package_root' => $this->testRoot.'/packages',
            'madlen.publisher.incoming_root' => $this->incomingRoot,
            'madlen.publisher.destination_root' => $this->destinationRoot,
            'madlen.publisher.source_revision' => str_repeat('a', 40),
            'madlen.publisher.simulate_transfer_failure' => false,
            'madlen.production_publisher' => 'unconfigured',
            'madlen.production_connected' => false,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->testRoot) && str_starts_with($this->testRoot, rtrim(sys_get_temp_dir(), '/\\').'/madlen-production-publisher-test-')) {
            File::deleteDirectory($this->testRoot);
        }
        parent::tearDown();
    }

    public function test_unconfigured_publisher_cannot_create_or_dispatch_a_request(): void
    {
        try {
            app(ProductionPublisher::class)->publish();
            $this->fail('Ein nicht verbundener Produktiv-Publisher hätte abbrechen müssen.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('nicht konfiguriert', $error->getMessage());
        }

        $this->assertDatabaseCount('production_publications', 0);
    }

    public function test_selected_published_content_is_dispatched_once_built_in_de_and_en_and_excludes_drafts(): void
    {
        $this->artisan('madlen:import')->assertSuccessful();
        Storage::disk('local')->put('media/originals/private-original.jpg', 'PRIVATE-ORIGINAL-MUST-NOT-LEAVE');
        Storage::disk('local')->put('media/derivatives/published.webp', 'PUBLIC-DERIVATIVE');
        $media = MediaAsset::query()->create([
            'path' => 'media/originals/private-original.jpg',
            'derivative_path' => 'media/derivatives/published.webp',
            'original_name' => 'private-original.jpg',
            'mime_type' => 'image/webp',
            'kind' => 'image',
            'source_managed' => false,
            'alt_de' => 'Veröffentlichtes Testbild',
            'alt_en' => 'Published test image',
        ]);
        $category = Project::query()->firstOrFail()->category_id;
        $published = Project::query()->create([
            'slug' => 'production-package-check',
            'title_de' => 'Produktiv-Paket',
            'title_en' => 'Production package',
            'description_de' => 'Veröffentlichter Inhalt.',
            'description_en' => 'Published content.',
            'category_id' => $category,
            'cover_media_id' => $media->id,
            'status' => 'published',
            'position' => 90001,
        ]);
        $published->mediaItems()->create([
            'media_asset_id' => $media->id,
            'role' => 'gallery',
            'side' => 'left',
            'position' => 1,
        ]);
        Project::query()->create([
            'slug' => 'private-draft-must-stay-out',
            'title_de' => 'Privater Entwurf',
            'title_en' => 'Private draft',
            'description_de' => 'Nur Entwurf.',
            'description_en' => 'Draft only.',
            'category_id' => $category,
            'status' => 'draft',
            'position' => 90002,
        ]);

        config([
            'madlen.production_connected' => true,
            'madlen.production_publisher' => 'github-actions',
            'madlen.publisher.github_repository' => 'example/madlen',
            'madlen.publisher.github_workflow' => 'madlen-production-publisher.yml',
            'madlen.publisher.github_ref' => 'main',
            'madlen.publisher.github_token' => 'test-token-never-logged',
        ]);
        Http::fake(['https://api.github.com/*' => Http::response(null, 204)]);
        $publication = app(ProductionPublisher::class)->publish();
        $this->assertSame('queued', $publication->status);
        $this->assertSame(1, $publication->sequence);
        $this->assertSame($publication->id, app(ProductionPublisher::class)->publish($publication->request_id)->id);
        $this->assertDatabaseCount('production_publications', 1);
        $this->assertFileExists($publication->package_path);
        $this->assertSame($publication->package_checksum, hash_file('sha256', $publication->package_path));

        $extracted = $this->testRoot.'/extracted';
        File::ensureDirectoryExists($extracted);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($publication->package_path) === true);
        $this->assertTrue($zip->extractTo($extracted));
        $zip->close();

        $manifest = file_get_contents($extracted.'/content-manifest.json');
        $this->assertStringContainsString('production-package-check', $manifest);
        $this->assertStringNotContainsString('private-draft-must-stay-out', $manifest);
        $this->assertFileExists($extracted.'/media/'.$media->id.'/published.webp');
        $this->assertFileDoesNotExist($extracted.'/media/'.$media->id.'/private-original.jpg');
        $this->assertStringNotContainsString('PRIVATE-ORIGINAL-MUST-NOT-LEAVE', file_get_contents($publication->package_path));

        $buildPath = $this->testRoot.'/external-build';
        $process = new Process(
            ['npm', 'run', 'build:publication', '--', $extracted, $buildPath],
            config('madlen.repository_root'),
            [
                'MADLEN_EXPECTED_PUBLICATION_ID' => $publication->id,
                'MADLEN_EXPECTED_SEQUENCE' => (string) $publication->sequence,
                'MADLEN_EXPECTED_SOURCE_REVISION' => $publication->source_revision,
            ],
        );
        $process->setTimeout(300);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $this->assertFileExists($buildPath.'/index.html');
        $this->assertFileExists($buildPath.'/en/index.html');
        $this->assertFileDoesNotExist($buildPath.'/images/start_seite.jpeg');
        $this->assertFileExists($buildPath.'/portfolio/production-package-check/index.html');
        $this->assertFileExists($buildPath.'/en/portfolio/production-package-check/index.html');
        $this->assertFileExists($buildPath.'/media/'.$media->id.'/published.webp');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/example/madlen/actions/workflows/madlen-production-publisher.yml/dispatches'
            && $request['inputs']['publication_id'] === $publication->id
            && $request['inputs']['package_sha256'] === $publication->package_checksum
            && $request['inputs']['source_revision'] === str_repeat('a', 40)
        );
        Http::assertSentCount(1);
    }

    public function test_runner_api_is_closed_by_default_and_reports_german_progress_when_connected(): void
    {
        $publication = ProductionPublication::query()->create([
            'sequence' => 1,
            'status' => 'queued',
            'source_revision' => str_repeat('b', 40),
            'package_checksum' => str_repeat('c', 64),
            'progress_message' => 'Wartet.',
        ]);
        $publication->update(['package_path'=>'madlen-production-storage-v1://packages/madlen-publication-1-'.$publication->id.'.zip']);
        File::ensureDirectoryExists(dirname($publication->package_path));
        file_put_contents($publication->package_path, 'private-package');

        $this->get('/api/publisher/v1/publications/'.$publication->id.'/package')->assertNotFound();

        config([
            'madlen.production_connected' => true,
            'madlen.production_publisher' => 'github-actions',
            'madlen.publisher.api_token' => 'isolated-runner-token',
        ]);
        $this->get('/api/publisher/v1/publications/'.$publication->id.'/package')->assertUnauthorized();
        $this->withToken('isolated-runner-token')
            ->get('/api/publisher/v1/publications/'.$publication->id.'/package')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->withToken('isolated-runner-token')
            ->postJson('/api/publisher/v1/publications/'.$publication->id.'/claim', ['runner_id' => '100-1'])
            ->assertOk();
        $this->assertStringContainsString('zweisprachige Website', $publication->refresh()->progress_message);
        $this->withToken('isolated-runner-token')
            ->postJson('/api/publisher/v1/publications/'.$publication->id.'/status', ['status' => 'uploading', 'runner_id' => '100-1'])
            ->assertOk();
        $this->withToken('isolated-runner-token')
            ->postJson('/api/publisher/v1/publications/'.$publication->id.'/status', [
                'status' => 'active',
                'target_release' => '1-'.$publication->id,
                'runner_id' => '100-1',
            ])
            ->assertStatus(409);
        $this->assertSame('uploading', $publication->refresh()->status);
    }

    public function test_full_external_production_flow_builds_selected_content_activates_current_and_rolls_back(): void
    {
        $this->artisan('madlen:import')->assertSuccessful();
        $baseline = ProductionPublication::query()->create([
            'sequence' => 1,
            'status' => 'active',
            'source_revision' => str_repeat('a', 40),
            'progress_message' => 'Vorheriger stabiler Stand.',
        ]);
        [$baselineArchive, $baselineChecksum] = $this->makeStaticArchive('baseline.zip', 'Vorheriger stabiler Stand', $baseline->id, 1);
        $activator = app(StaticReleaseActivator::class);
        $baselineRelease = $activator->activate($baseline->id, 1, basename($baselineArchive), $baselineChecksum);
        $baseline->update(['target_release' => $baselineRelease]);

        $categoryId = Project::query()->firstOrFail()->category_id;
        $selected = Project::query()->create([
            'slug' => 'selected-production-project',
            'title_de' => 'Ausgewähltes Produktionsprojekt',
            'title_en' => 'Selected production project',
            'description_de' => 'Dieser gespeicherte Inhalt muss im Produktivpaket erscheinen.',
            'description_en' => 'This saved content must appear in the production package.',
            'category_id' => $categoryId,
            'status' => 'published',
            'position' => 99001,
        ]);
        Project::query()->create([
            'slug' => 'unrelated-draft-must-stay-private',
            'title_de' => 'Anderer Entwurf',
            'title_en' => 'Unrelated draft',
            'description_de' => 'Darf nicht veröffentlicht werden.',
            'description_en' => 'Must not be published.',
            'category_id' => $categoryId,
            'status' => 'draft',
            'position' => 99002,
        ]);

        config([
            'madlen.production_connected' => true,
            'madlen.production_publisher' => 'github-actions',
            'madlen.publisher.github_repository' => 'example/madlen',
            'madlen.publisher.github_workflow' => 'madlen-production-publisher.yml',
            'madlen.publisher.github_ref' => 'main',
            'madlen.publisher.github_token' => 'test-token-never-logged',
        ]);
        Http::fake(['https://api.github.com/*' => Http::response(null, 204)]);
        $publication = app(ProductionPublisher::class)->publish();
        $this->assertSame(2, $publication->sequence);
        $this->assertSame('queued', $publication->status);
        $this->assertSame($publication->id, app(ProductionPublisher::class)->publish($publication->request_id)->id);
        $this->assertDatabaseCount('production_publications', 2);

        $packageRoot = $this->extractPackage($publication);
        $manifest = File::get($packageRoot.'/content-manifest.json');
        $this->assertStringContainsString($selected->slug, $manifest);
        $this->assertStringNotContainsString('unrelated-draft-must-stay-private', $manifest);
        $buildRoot = $this->buildPublication($publication, $packageRoot);
        $this->assertFileExists($buildRoot.'/portfolio/'.$selected->slug.'/index.html');
        $this->assertFileExists($buildRoot.'/en/portfolio/'.$selected->slug.'/index.html');

        [$archive, $checksum] = $this->archiveBuild($buildRoot, 'selected-production.zip');
        $statuses = app(ProductionPublicationStatus::class);
        $statuses->update($publication, 'building');
        $statuses->update($publication, 'uploading');
        $release = app(ProductionReleaseManager::class)->activate($publication->id, $publication->sequence, basename($archive), $checksum);

        $this->assertSame('releases/'.$release, readlink($this->destinationRoot.'/current'));
        $this->assertFileExists($this->destinationRoot.'/current/index.html');
        $this->assertFileExists($this->destinationRoot.'/current/en/index.html');
        $this->assertFileExists($this->destinationRoot.'/current/portfolio/'.$selected->slug.'/index.html');
        $this->assertFileExists($this->destinationRoot.'/current/en/portfolio/'.$selected->slug.'/index.html');
        $this->assertSame('active', $publication->refresh()->status);

        $this->assertSame($baselineRelease, $activator->rollback($baselineRelease));
        $this->assertSame('releases/'.$baselineRelease, readlink($this->destinationRoot.'/current'));
        $this->assertFileDoesNotExist($this->destinationRoot.'/current/portfolio/'.$selected->slug.'/index.html');

        $invalidPackage = $this->testRoot.'/invalid-external-package';
        File::ensureDirectoryExists($invalidPackage);
        $failedBuild = new Process(
            ['npm', 'run', 'build:publication', '--', $invalidPackage, $this->testRoot.'/must-not-exist'],
            config('madlen.repository_root'),
            ['ASTRO_TELEMETRY_DISABLED' => '1'],
        );
        $failedBuild->run();
        $this->assertFalse($failedBuild->isSuccessful());
        $this->assertSame('releases/'.$baselineRelease, readlink($this->destinationRoot.'/current'));
    }

    public function test_isolated_target_keeps_old_release_on_transfer_failure_blocks_older_jobs_and_rolls_back(): void
    {
        $firstId = '11111111-1111-4111-8111-111111111111';
        $secondId = '22222222-2222-4222-8222-222222222222';
        [$firstArchive, $firstChecksum] = $this->makeStaticArchive('first.zip', 'Erster stabiler Stand', $firstId, 1);
        [$secondArchive, $secondChecksum] = $this->makeStaticArchive('second.zip', 'Zweiter stabiler Stand', $secondId, 2);
        $activator = app(StaticReleaseActivator::class);

        $firstRelease = $activator->activate($firstId, 1, basename($firstArchive), $firstChecksum);
        $this->assertSame('Erster stabiler Stand', trim(strip_tags(file_get_contents($this->destinationRoot.'/current/index.html'))));

        config(['madlen.publisher.simulate_transfer_failure' => true]);
        try {
            $activator->activate($secondId, 2, basename($secondArchive), $secondChecksum);
            $this->fail('Der simulierte Übertragungsfehler hätte abbrechen müssen.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Simulierter Übertragungsfehler', $error->getMessage());
        }
        $this->assertSame('Erster stabiler Stand', trim(strip_tags(file_get_contents($this->destinationRoot.'/current/index.html'))));

        config(['madlen.publisher.simulate_transfer_failure' => false]);
        $secondRelease = $activator->activate($secondId, 2, basename($secondArchive), $secondChecksum);
        $this->assertSame('Zweiter stabiler Stand', trim(strip_tags(file_get_contents($this->destinationRoot.'/current/index.html'))));

        $oldId = '33333333-3333-4333-8333-333333333333';
        [$oldArchive, $oldChecksum] = $this->makeStaticArchive('old.zip', 'Verspäteter alter Stand', $oldId, 1);
        try {
            $activator->activate($oldId, 1, basename($oldArchive), $oldChecksum);
            $this->fail('Ein älterer Auftrag hätte den neueren Stand nicht ersetzen dürfen.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('älter', $error->getMessage());
        }
        $this->assertSame('Zweiter stabiler Stand', trim(strip_tags(file_get_contents($this->destinationRoot.'/current/index.html'))));

        $this->assertSame($firstRelease, $activator->rollback($firstRelease));
        $this->assertSame('Erster stabiler Stand', trim(strip_tags(file_get_contents($this->destinationRoot.'/current/index.html'))));
        $state = json_decode(file_get_contents($this->destinationRoot.'/.publisher-state.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(2, $state['highestSequence']);
        $this->assertSame($firstRelease, $state['activeRelease']);
        $this->assertDirectoryExists($this->destinationRoot.'/releases/'.$secondRelease);

        $delayedId = '44444444-4444-4444-8444-444444444444';
        [$delayedArchive, $delayedChecksum] = $this->makeStaticArchive('delayed.zip', 'Verspäteter Stand nach Rollback', $delayedId, 2);
        try {
            $activator->activate($delayedId, 2, basename($delayedArchive), $delayedChecksum);
            $this->fail('Ein verspäteter Auftrag hätte den bewussten Rollback nicht überschreiben dürfen.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('älter', $error->getMessage());
        }
        $this->assertSame('Erster stabiler Stand', trim(strip_tags(file_get_contents($this->destinationRoot.'/current/index.html'))));
    }

    private function makeStaticArchive(string $archiveName, string $label, string $publicationId, int $sequence): array
    {
        $source = $this->testRoot.'/archive-source-'.Str::random(8);
        File::ensureDirectoryExists($source.'/en');
        File::ensureDirectoryExists($source.'/media/test');
        file_put_contents($source.'/index.html', '<h1>'.$label.'</h1><img src="/media/test/photo.webp">');
        file_put_contents($source.'/en/index.html', '<h1>'.$label.'</h1><img src="/media/test/photo.webp">');
        file_put_contents($source.'/media/test/photo.webp', 'image');
        file_put_contents($source.'/sitemap.xml', '<?xml version="1.0"?><urlset/>');
        file_put_contents($source.'/.madlen-release.json', json_encode([
            'schemaVersion' => 1,
            'publicationId' => $publicationId,
            'sequence' => $sequence,
            'sourceRevision' => str_repeat('a', 40),
        ], JSON_THROW_ON_ERROR));

        $archive = $this->incomingRoot.'/'.$archiveName;
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) === true);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($entry->isFile()) {
                $zip->addFile($entry->getPathname(), str_replace('\\', '/', substr($entry->getPathname(), strlen($source) + 1)));
            }
        }
        $zip->close();
        File::deleteDirectory($source);

        return [$archive, hash_file('sha256', $archive)];
    }

    private function extractPackage(ProductionPublication $publication): string
    {
        $target = $this->testRoot.'/package-'.Str::random(8);
        File::ensureDirectoryExists($target);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($publication->package_path) === true);
        $this->assertTrue($zip->extractTo($target));
        $zip->close();

        return $target;
    }

    private function buildPublication(ProductionPublication $publication, string $packageRoot): string
    {
        $buildRoot = $this->testRoot.'/build-'.Str::random(8);
        $process = new Process(
            ['npm', 'run', 'build:publication', '--', $packageRoot, $buildRoot],
            config('madlen.repository_root'),
            [
                'ASTRO_TELEMETRY_DISABLED' => '1',
                'MADLEN_EXPECTED_PUBLICATION_ID' => $publication->id,
                'MADLEN_EXPECTED_SEQUENCE' => (string) $publication->sequence,
                'MADLEN_EXPECTED_SOURCE_REVISION' => $publication->source_revision,
            ],
        );
        $process->setTimeout(300);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

        return $buildRoot;
    }

    private function archiveBuild(string $buildRoot, string $archiveName): array
    {
        $archive = $this->incomingRoot.'/'.$archiveName;
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) === true);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($buildRoot, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($entry->isFile()) {
                $zip->addFile($entry->getPathname(), str_replace('\\', '/', substr($entry->getPathname(), strlen($buildRoot) + 1)));
            }
        }
        $zip->close();

        return [$archive, hash_file('sha256', $archive)];
    }
}
