<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\ProjectMedia;
use App\Models\Service;
use App\Models\Setting;
use App\Models\SiteMediaSlot;
use App\Services\MediaProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportBaselineContent extends Command
{
    protected $signature = 'madlen:import {--force-new-only : Keinesfalls bestehende Datensätze aktualisieren}';

    protected $description = 'Importiert die vorhandenen Astro-Inhalte idempotent als CMS-Ausgangsbestand';

    public function handle(MediaProcessor $processor): int
    {
        $path = config('madlen.baseline_path');
        if (! is_file($path)) {
            $this->error("Baseline nicht gefunden: {$path}");
            return self::FAILURE;
        }

        $baseline = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $created = ['projects' => 0, 'media' => 0, 'services' => 0, 'content' => 0];

        DB::transaction(function () use ($baseline, $processor, &$created): void {
            $categoryLabels = [
                'editorial' => ['Editorial', 'Editorial'],
                'events' => ['Events', 'Events'],
                'weddings' => ['Hochzeiten', 'Weddings'],
                'landscape' => ['Landschaft', 'Landscape'],
            ];
            $categories = [];
            foreach ($categoryLabels as $key => [$de, $en]) {
                $categories[$key] = Category::query()->firstOrCreate(
                    ['key' => $key],
                    ['label_de' => $de, 'label_en' => $en, 'position' => count($categories) + 1],
                );
            }

            foreach ($baseline['projects'] as $index => $item) {
                $project = Project::query()->where('source_key', $item['slug'])->first();
                if ($project) {
                    continue;
                }

                $cover = $this->sourceMedia($item['cover'], null, null, $processor, $created);
                $project = Project::query()->create([
                    'source_key' => $item['slug'],
                    'slug' => $item['slug'],
                    'title_de' => $item['title']['de'],
                    'title_en' => $item['title']['en'],
                    'description_de' => $item['description']['de'],
                    'description_en' => $item['description']['en'],
                    'category_id' => $categories[$item['category']]->id,
                    'cover_media_id' => $cover->id,
                    'status' => 'published',
                    'position' => ($index + 1) * 100,
                    'source_imported_at' => now(),
                    'published_at' => now(),
                ]);

                foreach ($item['images'] as $image) {
                    $media = $this->sourceMedia($image['src'], $image['altDe'], $image['altEn'], $processor, $created);
                    ProjectMedia::query()->create([
                        'project_id' => $project->id,
                        'media_asset_id' => $media->id,
                        'role' => 'gallery',
                        'position' => $image['order'],
                        'side' => $image['side'],
                    ]);
                }
                $created['projects']++;
            }

            foreach ($baseline['services'] as $item) {
                $service = Service::query()->firstOrCreate(
                    ['key' => $item['key']],
                    [
                        'title_de' => $item['titleDe'],
                        'title_en' => $item['titleEn'] ?: null,
                        'description_de' => $item['descriptionDe'],
                        'description_en' => $item['descriptionEn'] ?: null,
                        'icon' => $item['icon'],
                        'highlighted' => $item['highlighted'],
                        'active_de' => true,
                        'active_en' => filled($item['titleEn']),
                        'position' => $item['order'],
                        'source_imported_at' => now(),
                    ],
                );
                if ($service->wasRecentlyCreated) {
                    $created['services']++;
                    continue;
                }

                $repair = [];
                $missingMarkers = ['', '{service.title}', '{service.description}'];
                if (filled($item['titleEn']) && in_array((string) $service->title_en, $missingMarkers, true)) {
                    $repair['title_en'] = $item['titleEn'];
                }
                if (filled($item['descriptionEn']) && in_array((string) $service->description_en, $missingMarkers, true)) {
                    $repair['description_en'] = $item['descriptionEn'];
                }
                if (filled($item['titleEn']) && filled($item['descriptionEn']) && ! $service->active_en) {
                    $repair['active_en'] = true;
                }
                if ($repair !== []) {
                    $service->forceFill($repair)->save();
                }
            }

            foreach ($baseline['translations']['de'] as $key => $deValue) {
                if (in_array($key, ['lang', 'locale'], true)) continue;
                $entry = ContentEntry::query()->firstOrCreate(
                    ['key' => 'translations.'.$key],
                    [
                        'group_name' => $key,
                        'type' => 'json',
                        'value_de' => json_encode($deValue, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                        'value_en' => json_encode($baseline['translations']['en'][$key] ?? null, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                        'source_imported_at' => now(),
                    ],
                );
                if ($entry->wasRecentlyCreated) {
                    $created['content']++;
                    continue;
                }

                $repair = [];
                foreach (['de', 'en'] as $locale) {
                    $field = 'value_'.$locale;
                    $current = json_decode((string) $entry->{$field}, true) ?: [];
                    $source = $baseline['translations'][$locale][$key] ?? [];
                    $merged = $this->mergeMissing($current, $source);
                    if ($merged !== $current) {
                        $repair[$field] = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                    }
                }
                if ($repair !== []) {
                    $entry->forceFill($repair)->save();
                }
            }

            foreach ($baseline['legalPages'] as $legal) {
                $entry = ContentEntry::query()->firstOrCreate(
                    ['key' => 'legal.'.$legal['key']],
                    [
                        'group_name' => 'legal',
                        'type' => 'plain_text',
                        'value_de' => $legal['valueDe'],
                        'value_en' => $legal['valueEn'],
                        'source_imported_at' => now(),
                    ],
                );
                if ($entry->wasRecentlyCreated) $created['content']++;
            }

            foreach ($baseline['settings'] as $key => $value) {
                Setting::query()->firstOrCreate(
                    ['key' => $key],
                    ['label_de' => $key, 'value' => ['value' => $value]],
                );
            }

            foreach ([
                'home_video' => ['Startseite: Video', '/images/hero/hero-video.mp4'],
                'home_video_poster' => ['Startseite: Video-Poster', '/images/hero/hero-video-poster.webp'],
                'memories_photo' => ['Startseite: Memories-Foto', '/images/hero/memories-photo.webp'],
            ] as $key => [$label, $path]) {
                $media = $this->sourceMedia($path, null, null, $processor, $created);
                SiteMediaSlot::query()->firstOrCreate(
                    ['key' => $key],
                    ['label_de' => $label, 'media_asset_id' => $media->id],
                );
            }
        });

        $this->info("Import abgeschlossen: {$created['projects']} Projekte, {$created['media']} Medien, {$created['services']} Leistungen, {$created['content']} Textbereiche neu angelegt.");
        return self::SUCCESS;
    }

    private function sourceMedia(string $publicPath, ?string $altDe, ?string $altEn, MediaProcessor $processor, array &$created): MediaAsset
    {
        $asset = MediaAsset::query()->withTrashed()->where('source_key', $publicPath)->first();
        if ($asset) {
            if ($asset->trashed()) $asset->restore();
            return $asset;
        }

        $asset = MediaAsset::query()->create([
            'source_key' => $publicPath,
            'kind' => 'image',
            'path' => $publicPath,
            'original_name' => basename($publicPath),
            'mime_type' => 'application/octet-stream',
            'alt_de' => $altDe,
            'alt_en' => $altEn,
            'source_managed' => true,
        ]);
        $processor->inspect($asset);
        $created['media']++;
        return $asset;
    }

    private function mergeMissing(array $current, array $source): array
    {
        foreach ($source as $key => $value) {
            if (! array_key_exists($key, $current)) {
                $current[$key] = $value;
                continue;
            }

            if (is_array($value) && ! array_is_list($value) && is_array($current[$key]) && ! array_is_list($current[$key])) {
                $current[$key] = $this->mergeMissing($current[$key], $value);
            }
        }

        return $current;
    }
}
