<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\Release;
use App\Models\Service;
use App\Models\User;
use App\Services\ContentManifestService;
use App\Services\MediaProcessor;
use App\Services\PreviewBuilder;
use App\Services\ReleasePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicationPipelineTest extends TestCase
{
    use RefreshDatabase;

    private string $releaseRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->releaseRoot = storage_path('framework/publication-test-'.Str::random(10));
        config(['madlen.release_root' => $this->releaseRoot]);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        if (isset($this->releaseRoot) && str_starts_with($this->releaseRoot, storage_path('framework/publication-test-'))) {
            File::deleteDirectory($this->releaseRoot);
        }
        parent::tearDown();
    }

    public function test_draft_preview_and_real_astro_publication_use_immutable_protected_media(): void
    {
        $this->artisan('madlen:import')->assertSuccessful();
        $user = User::factory()->create();
        $this->actingAs($user);

        $upload = UploadedFile::fake()->image('abnahme.jpg', 1200, 800);
        $path = $upload->store('media/originals', 'local');
        $media = MediaAsset::query()->create([
            'path' => $path,
            'original_name' => 'abnahme.jpg',
            'mime_type' => 'application/octet-stream',
            'kind' => 'image',
            'source_managed' => false,
            'alt_de' => 'Temporäres Bild für die Abnahmeprüfung',
            'alt_en' => 'Temporary image for the acceptance test',
        ]);
        app(MediaProcessor::class)->inspect($media);

        $project = Project::query()->create([
            'slug' => 'cms-acceptance-check',
            'title_de' => 'CMS Abnahmeprojekt',
            'title_en' => 'CMS acceptance project',
            'description_de' => 'Temporäres Projekt für die lokale Veröffentlichungsprüfung.',
            'description_en' => 'Temporary project for local publication verification.',
            'category_id' => Project::query()->firstOrFail()->category_id,
            'cover_media_id' => $media->id,
            'status' => 'draft',
            'position' => 99990,
        ]);
        $project->mediaItems()->create([
            'media_asset_id' => $media->id,
            'role' => 'gallery',
            'side' => 'right',
            'position' => 1,
        ]);

        $home = ContentEntry::query()->where('key', 'translations.home')->firstOrFail();
        $de = json_decode($home->value_de, true, flags: JSON_THROW_ON_ERROR);
        $de['memories']['title'] = 'CMS Buildprüfung Deutsch';
        $home->update(['value_de' => json_encode($de, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)]);
        Service::query()->where('key', 'portraits')->update(['title_en' => 'CMS service build verification']);
        $legal = ContentEntry::query()->where('key', 'legal.privacy')->firstOrFail();
        $legal->update(['value_en' => $legal->value_en."\nCMS legal build verification"]);

        $manifests = app(ContentManifestService::class);
        $this->assertNotContains($project->slug, array_column($manifests->make()['projects'], 'slug'));
        $this->assertContains($project->slug, array_column($manifests->make(includeDrafts: true)['projects'], 'slug'));
        $this->get('/media/'.$media->id.'/'.basename($media->derivative_path))->assertNotFound();

        $preview = app(PreviewBuilder::class)->build();
        $previewBase = '/admin/preview/'.$preview->token;
        $this->assertFileExists($preview->build_path.'/portfolio/'.$project->slug.'/index.html');
        $previewHtml = file_get_contents($preview->build_path.'/index.html');
        $this->assertStringContainsString($previewBase.'/media/'.$media->id.'/', file_get_contents($preview->build_path.'/portfolio/'.$project->slug.'/index.html'));
        $this->assertStringContainsString($previewBase.'/images/brand/M_logo.png', $previewHtml);
        auth()->logout();
        $this->get($previewBase.'/')->assertNotFound();
        $this->get($previewBase.'/media/'.$media->id.'/'.basename($media->derivative_path))->assertNotFound();
        $this->actingAs($user)->get($previewBase.'/')->assertOk()->assertHeader('X-Robots-Tag');
        $this->get($previewBase.'/media/'.$media->id.'/'.basename($media->derivative_path))->assertOk();

        $project->update(['status' => 'published']);
        $release = app(ReleasePublisher::class)->publish();
        $this->assertSame('active', $release->status);
        $this->assertFileExists($release->build_path.'/portfolio/'.$project->slug.'/index.html');
        $this->assertFileExists($release->build_path.'/en/portfolio/'.$project->slug.'/index.html');
        $this->assertFileExists($release->build_path.'/media/'.$media->id.'/'.basename($media->derivative_path));
        $this->assertDirectoryDoesNotExist($release->build_path.'/design-reference');
        $this->assertFileDoesNotExist($release->build_path.'/images/start_seite.jpeg');
        $this->assertFileDoesNotExist($release->build_path.'/images/Kukes1.jpg');
        $this->assertStringContainsString('CMS Buildprüfung Deutsch', file_get_contents($release->build_path.'/index.html'));
        $this->assertStringContainsString('CMS service build verification', file_get_contents($release->build_path.'/en/services/index.html'));
        $this->assertStringContainsString('CMS legal build verification', file_get_contents($release->build_path.'/en/privacy/index.html'));
        $this->assertStringContainsString('https://admin.madebymadlen.de/api/contact', file_get_contents($release->build_path.'/kontakt/index.html'));
        $this->assertStringContainsString('https://admin.madebymadlen.de/api/contact', file_get_contents($release->build_path.'/en/contact/index.html'));
        $this->assertStringContainsString('/portfolio/'.$project->slug.'/', file_get_contents($release->build_path.'/sitemap.xml'));

        $project->update(['status' => 'unpublished']);
        $this->assertNotContains($project->slug, array_column($manifests->make()['projects'], 'slug'));
        $project->delete();
        $this->assertTrue($project->fresh()->trashed());
        $project->restore();
        $this->assertFalse($project->fresh()->trashed());
    }

    public function test_failed_build_keeps_the_previous_release_active(): void
    {
        $stablePath = $this->releaseRoot.'/builds/stable';
        File::ensureDirectoryExists($stablePath);
        file_put_contents($stablePath.'/index.html', '<h1>Stabiler Stand</h1>');
        $active = Release::query()->create([
            'version' => 1,
            'status' => 'active',
            'manifest_path' => $this->releaseRoot.'/manifests/stable.json',
            'build_path' => $stablePath,
            'published_at' => now(),
        ]);

        putenv('MADLEN_SIMULATE_BUILD_FAILURE=1');
        try {
            app(ReleasePublisher::class)->publish();
            $this->fail('Die simulierte fehlerhafte Veröffentlichung hätte abbrechen müssen.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Simulierter Build-Fehler', $error->getMessage());
        } finally {
            putenv('MADLEN_SIMULATE_BUILD_FAILURE');
        }

        $this->assertSame('active', $active->refresh()->status);
        $this->assertDatabaseHas('releases', ['version' => 2, 'status' => 'failed']);
        $this->assertStringContainsString('Stabiler Stand', file_get_contents($stablePath.'/index.html'));
    }
}
