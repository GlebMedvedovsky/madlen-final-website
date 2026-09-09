<?php

namespace Tests\Feature;

use App\Filament\Resources\ContentEntries\Pages\EditContentEntry;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\MediaAssets\Pages\CreateMediaAsset;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Filament\Widgets\CmsStatus;
use App\Models\ContentEntry;
use App\Models\MediaAsset;
use App\Models\PreviewBuild;
use App\Models\Project;
use App\Models\Release;
use App\Models\Revision;
use App\Models\Service;
use App\Models\Setting;
use App\Models\SiteMediaSlot;
use App\Models\User;
use App\Services\ContentManifestService;
use App\Services\RevisionRestorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CmsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_status_cards_are_semantic_authorized_resource_links(): void
    {
        $this->actingAs(User::factory()->create());

        $html = Livewire::test(CmsStatus::class, ['lazy' => false])->html();
        $projectsUrl = ProjectResource::getUrl('index');
        $mediaUrl = MediaAssetResource::getUrl('index');
        $releasesUrl = ReleaseResource::getUrl('index');

        $this->assertSame(4, preg_match_all('/<a\b/', $html));
        $this->assertSame(1, substr_count($html, 'href="'.e($projectsUrl).'"'));
        $this->assertSame(1, substr_count($html, 'href="'.e($mediaUrl).'"'));
        $this->assertSame(2, substr_count($html, 'href="'.e($releasesUrl).'"'));
        $this->assertStringContainsString('Projekte ansehen', $html);
        $this->assertStringContainsString('Medien öffnen', $html);
        $this->assertStringContainsString('Veröffentlichungen ansehen', $html);
        $this->assertStringContainsString('Veröffentlichungsstatus ansehen', $html);
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringNotContainsString('href="#"', $html);

        $before = [Project::query()->count(), MediaAsset::query()->count(), Release::query()->count()];
        foreach ([$projectsUrl, $mediaUrl, $releasesUrl] as $url) {
            $this->get(parse_url($url, PHP_URL_PATH))->assertOk();
        }
        $this->assertSame($before, [Project::query()->count(), MediaAsset::query()->count(), Release::query()->count()]);
    }

    public function test_baseline_import_is_complete_idempotent_and_does_not_overwrite_edits(): void
    {
        $this->artisan('madlen:import')->assertSuccessful();
        $this->assertDatabaseCount('projects', 16);
        $this->assertDatabaseCount('media_assets', 185);
        $this->assertDatabaseCount('project_media', 166);
        $this->assertDatabaseCount('categories', 4);
        $this->assertDatabaseCount('services', 7);
        $this->assertDatabaseCount('site_media_slots', 3);
        $this->assertSame('video', SiteMediaSlot::query()->where('key', 'home_video')->firstOrFail()->mediaAsset->kind);
        $expectedSlugs = collect(json_decode(file_get_contents(config('madlen.baseline_path')), true, flags: JSON_THROW_ON_ERROR)['projects'])
            ->pluck('slug')->sort()->values()->all();
        $this->assertSame($expectedSlugs, Project::query()->pluck('slug')->sort()->values()->all());
        $this->assertSame([
            'editorial' => 5,
            'events' => 5,
            'landscape' => 2,
            'weddings' => 4,
        ], Project::query()->join('categories', 'projects.category_id', '=', 'categories.id')
            ->selectRaw('categories.key, count(*) as total')->groupBy('categories.key')
            ->pluck('total', 'key')->map(fn ($count): int => (int) $count)->sortKeys()->all());
        $this->assertTrue(Project::query()->get()->every(fn (Project $item): bool => $item->isTranslationReady()));

        $project = Project::query()->where('slug', 'rainbow')->firstOrFail();
        $project->update(['title_de' => 'Redaktionell geändert']);
        $this->artisan('madlen:import')->assertSuccessful();
        $this->assertSame('Redaktionell geändert', $project->refresh()->title_de);
        $this->assertDatabaseCount('projects', 16);
        $this->assertGreaterThan(0, Revision::query()->count());

        $revision = Revision::query()->where('revisionable_id', $project->id)->oldest()->firstOrFail();
        app(RevisionRestorer::class)->restore($revision);
        $this->assertNotSame('Redaktionell geändert', $project->refresh()->title_de);

        $home = ContentEntry::query()->where('key', 'translations.home')->firstOrFail();
        $de = json_decode($home->value_de, true, flags: JSON_THROW_ON_ERROR);
        $de['memories']['title'] = 'CMS-Testüberschrift';
        $home->update(['value_de' => json_encode($de, JSON_UNESCAPED_UNICODE)]);
        $service = Service::query()->where('key', 'portraits')->firstOrFail();
        $service->update(['title_en' => 'CMS portrait test']);

        $draft = Project::query()->create([
            'slug' => 'cms-test-draft', 'title_de' => 'Entwurf', 'title_en' => 'Draft',
            'description_de' => 'Nur Vorschau', 'description_en' => 'Preview only',
            'category_id' => $project->category_id, 'cover_media_id' => $project->cover_media_id,
            'status' => 'draft', 'position' => 9999,
        ]);
        $manifests = app(ContentManifestService::class);
        $public = $manifests->make();
        $preview = $manifests->make(includeDrafts: true);
        $this->assertNotContains($draft->slug, array_column($public['projects'], 'slug'));
        $this->assertContains($draft->slug, array_column($preview['projects'], 'slug'));
        $this->assertSame('CMS-Testüberschrift', $public['translations']['de']['home']['memories']['title']);
        $this->assertSame('CMS portrait test', collect($public['services'])->firstWhere('key', 'portraits')['titleEn']);
    }

    public function test_admin_and_preview_are_protected_and_shared_media_cannot_be_trashed(): void
    {
        $this->artisan('madlen:import')->assertSuccessful();
        $user = User::factory()->create();

        $this->get('/admin/login')->assertOk();
        $this->get('/admin/projects')->assertRedirect();
        $this->actingAs($user)->get('/admin/projects')->assertOk();
        $project = Project::query()->firstOrFail();
        $this->get('/admin/projects/'.$project->id.'/edit')->assertOk();
        $this->get('/admin/media-assets/create')->assertOk();
        $this->get('/admin/services')->assertOk();
        $this->get('/admin/content-entries')->assertOk();
        $content = ContentEntry::query()->where('key', 'translations.home')->firstOrFail();
        $this->get('/admin/content-entries/'.$content->id.'/edit')->assertOk();
        $this->get('/admin/releases')->assertOk();
        $this->get('/admin/site-media-slots')->assertOk();
        $this->get('/admin/revisions')->assertOk();
        $setting = Setting::query()->firstOrFail();
        $this->get('/admin/settings/'.$setting->id.'/edit')->assertOk();

        $sourceMedia = MediaAsset::query()->where('kind', 'image')->where('source_managed', true)->firstOrFail();
        auth()->logout();
        $this->get(route('admin.media', ['mediaAsset' => $sourceMedia]))->assertNotFound();
        $this->actingAs($user)
            ->get(route('admin.media', ['mediaAsset' => $sourceMedia]))
            ->assertOk()
            ->assertHeader('Content-Type', $sourceMedia->mime_type);

        $buildPath = storage_path('framework/testing-preview-'.Str::random(8));
        mkdir($buildPath, 0775, true);
        file_put_contents($buildPath.'/index.html', '<h1>Privater Entwurf</h1>');
        $preview = PreviewBuild::query()->create([
            'token' => Str::random(48), 'manifest_path' => $buildPath.'/manifest.json',
            'build_path' => $buildPath, 'user_id' => $user->id, 'expires_at' => now()->addHour(),
        ]);
        auth()->logout();
        $this->get('/admin/preview/'.$preview->token)->assertNotFound();
        $this->actingAs($user)->get('/admin/preview/'.$preview->token)->assertOk()->assertHeader('X-Robots-Tag');
        $this->assertStringContainsString('Privater Entwurf', file_get_contents($buildPath.'/index.html'));

        $used = MediaAsset::query()->whereHas('projects')->firstOrFail();
        $this->expectException(ValidationException::class);
        $used->delete();
    }

    public function test_english_service_repair_is_explicit_idempotent_and_preserves_authored_values(): void
    {
        $this->artisan('madlen:import')->assertSuccessful();

        Service::query()->where('key', 'portraits')->update([
            'title_en' => '{service.title}',
            'description_en' => '{service.description}',
        ]);
        Service::query()->where('key', 'couples-families')->update([
            'title_en' => 'User-authored English title',
            'description_en' => null,
            'active_en' => false,
        ]);

        $this->artisan('madlen:import')->assertSuccessful();

        $services = Service::query()->orderBy('position')->get()->keyBy('key');
        $this->assertSame('Portrait Photography', $services['portraits']->title_en);
        $this->assertSame('Natural and expressive portraits shaped by light, personality and atmosphere.', $services['portraits']->description_en);
        $this->assertSame('User-authored English title', $services['couples-families']->title_en);
        $this->assertSame('Authentic photographs of shared moments without forced poses.', $services['couples-families']->description_en);
        $this->assertTrue($services['couples-families']->active_en);
        $this->assertSame('Videography & Editing', $services['videography']->title_en);
        $this->assertFalse($services['editing']->active_en);
        $this->assertSame('Zusammengefasst', $services['editing']->englishReadinessLabel());
        $englishServices = array_values(array_filter(
            app(ContentManifestService::class)->make()['services'],
            fn (array $service): bool => filled($service['titleEn']),
        ));
        $this->assertCount(6, $englishServices);

        $snapshot = Service::query()->orderBy('position')->get()->map->only(['key', 'title_en', 'description_en', 'active_en'])->all();
        $this->artisan('madlen:import')->assertSuccessful();
        $this->assertSame($snapshot, Service::query()->orderBy('position')->get()->map->only(['key', 'title_en', 'description_en', 'active_en'])->all());
    }

    public function test_structured_content_form_preserves_other_locale_and_unknown_nested_fields(): void
    {
        $this->artisan('madlen:import')->assertSuccessful();
        $this->actingAs(User::factory()->create());

        $home = ContentEntry::query()->where('key', 'translations.home')->firstOrFail();
        $de = json_decode($home->value_de, true, flags: JSON_THROW_ON_ERROR);
        $en = json_decode($home->value_en, true, flags: JSON_THROW_ON_ERROR);
        $de['futureField'] = ['preserve' => true];
        $en['futureField'] = ['preserve' => 'english'];
        $home->update([
            'value_de' => json_encode($de, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'value_en' => json_encode($en, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $englishBefore = $home->fresh()->value_en;

        Livewire::test(EditContentEntry::class, ['record' => $home->id])
            ->fillForm(['structured_de.workApart.title' => 'Geänderte CMS-Überschrift'])
            ->call('save')
            ->assertHasNoFormErrors();

        $home->refresh();
        $savedDe = json_decode($home->value_de, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Geänderte CMS-Überschrift', $savedDe['workApart']['title']);
        $this->assertTrue($savedDe['futureField']['preserve']);
        $this->assertSame($englishBefore, $home->value_en);
    }

    public function test_filament_can_create_media_and_a_bilingual_project_draft(): void
    {
        $this->artisan('madlen:import')->assertSuccessful();
        $this->actingAs(User::factory()->create());
        Storage::fake('local');

        Livewire::test(CreateMediaAsset::class)
            ->fillForm([
                'path' => UploadedFile::fake()->image('cms-test.jpg', 1200, 800),
                'alt_de' => 'Testbild für die deutsche Seite',
                'alt_en' => 'Test image for the English page',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateMediaAsset::class)
            ->fillForm([
                'path' => UploadedFile::fake()->image('cms-test-2.jpg', 900, 1200),
                'alt_de' => 'Zweites Testbild',
                'alt_en' => 'Second test image',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        [$media, $secondMedia] = MediaAsset::query()->where('source_managed', false)->orderBy('created_at')->get()->all();
        foreach ([$media, $secondMedia] as $uploadedMedia) {
            $response = $this->get(route('admin.media', ['mediaAsset' => $uploadedMedia]))->assertOk();
            $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
            $this->assertNotFalse(getimagesize(Storage::disk('local')->path($uploadedMedia->derivative_path)));
        }
        $category = Project::query()->firstOrFail()->category_id;
        Livewire::test(CreateProject::class)
            ->fillForm([
                'slug' => 'filament-workflow-test',
                'category_id' => $category,
                'cover_media_id' => $media->id,
                'position' => 9900,
                'title_de' => 'Filament Testprojekt',
                'description_de' => 'Ein nur lokal angelegter zweisprachiger Entwurf.',
                'title_en' => 'Filament test project',
                'description_en' => 'A bilingual draft created only in the local test database.',
                'mediaItems' => [
                    ['media_asset_id' => $secondMedia->id, 'role' => 'gallery', 'side' => 'right'],
                    ['media_asset_id' => $media->id, 'role' => 'gallery', 'side' => 'left'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = Project::query()->where('slug', 'filament-workflow-test')->firstOrFail();
        $this->assertSame('draft', $created->status);
        $this->assertSame('Filament test project', $created->title_en);
        $this->assertDatabaseHas('project_media', ['project_id' => $created->id, 'media_asset_id' => $secondMedia->id, 'side' => 'right', 'position' => 1]);
        $this->assertDatabaseHas('project_media', ['project_id' => $created->id, 'media_asset_id' => $media->id, 'side' => 'left', 'position' => 2]);
        $this->get('/media/'.$media->id.'/'.basename($media->derivative_path))->assertNotFound();
    }
}
