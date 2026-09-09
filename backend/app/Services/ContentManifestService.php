<?php

namespace App\Services;

use App\Models\ContentEntry;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\Service;
use App\Models\Setting;
use App\Models\SiteMediaSlot;

class ContentManifestService
{
    public function make(bool $includeDrafts = false): array
    {
        $baseline = json_decode(file_get_contents(config('madlen.baseline_path')), true, flags: JSON_THROW_ON_ERROR);
        $translations = $baseline['translations'];

        ContentEntry::query()
            ->where('key', 'like', 'translations.%')
            ->orderBy('position')
            ->each(function (ContentEntry $entry) use (&$translations): void {
                $section = substr($entry->key, strlen('translations.'));
                foreach (['de', 'en'] as $locale) {
                    $value = $entry->{'value_'.$locale};
                    if (filled($value)) {
                        $translations[$locale][$section] = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
                    }
                }
            });

        $query = Project::query()
            ->with(['category', 'cover', 'mediaItems.mediaAsset'])
            ->orderBy('position');
        if (! $includeDrafts) {
            $query->where('status', 'published');
        } else {
            $query->where('status', '!=', 'unpublished');
        }

        $projects = $query->get()->map(function (Project $project): array {
            return [
                'slug' => $project->slug,
                'title' => ['de' => $project->title_de, 'en' => $project->title_en],
                'category' => $project->category->key,
                'cover' => $project->cover?->publicPath(),
                'description' => ['de' => $project->description_de, 'en' => $project->description_en],
                'images' => $project->mediaItems->map(fn ($item): array => [
                    'src' => $item->mediaAsset->publicPath(),
                    'altDe' => $item->mediaAsset->alt_de ?: $project->title_de.' – Fotografie von Madlen Medvedovskyy',
                    'altEn' => $item->mediaAsset->alt_en ?: $project->title_en.' — photography by Madlen Medvedovskyy',
                    'order' => $item->position,
                    'side' => $item->side,
                ])->values()->all(),
            ];
        })->values()->all();

        $services = Service::query()->orderBy('position')->get()->map(fn (Service $service): array => [
            'key' => $service->key,
            'order' => $service->position,
            'titleDe' => $service->active_de ? $service->title_de : '',
            'titleEn' => $service->active_en ? $service->title_en : '',
            'descriptionDe' => $service->active_de ? $service->description_de : '',
            'descriptionEn' => $service->active_en ? $service->description_en : '',
            'icon' => $service->icon,
            'highlighted' => $service->highlighted,
        ])->values()->all();

        $legalPages = ContentEntry::query()->where('group_name', 'legal')->get()->map(fn (ContentEntry $entry): array => [
            'key' => str_replace('legal.', '', $entry->key),
            'type' => $entry->type,
            'valueDe' => $entry->value_de,
            'valueEn' => $entry->value_en,
        ])->values()->all();

        return [
            'schemaVersion' => 1,
            'generatedAt' => now()->toIso8601String(),
            'projects' => $projects,
            'translations' => $translations,
            'services' => $services,
            'legalPages' => $legalPages,
            'settings' => [
                ...Setting::query()->get()->mapWithKeys(fn (Setting $setting): array => [
                    $setting->key => is_array($setting->value) && array_key_exists('value', $setting->value)
                        ? $setting->value['value']
                        : $setting->value,
                ])->all(),
                'mediaSlots' => SiteMediaSlot::query()->with('mediaAsset')->get()
                    ->filter(fn (SiteMediaSlot $slot): bool => filled($slot->mediaAsset?->publicPath()))
                    ->mapWithKeys(
                    fn (SiteMediaSlot $slot): array => [$slot->key => $slot->mediaAsset?->publicPath()],
                )->all(),
            ],
        ];
    }

    public function mediaCopies(array $manifest): array
    {
        $paths = collect($manifest['projects'])
            ->flatMap(fn (array $project): array => array_merge([$project['cover']], array_column($project['images'], 'src')))
            ->merge(array_values($manifest['settings']['mediaSlots'] ?? []))
            ->filter(fn (?string $path): bool => filled($path) && str_starts_with($path, '/media/'))
            ->unique();

        return MediaAsset::query()->whereIn('id', $paths->map(fn (string $path): string => explode('/', $path)[2]))
            ->get()
            ->mapWithKeys(fn (MediaAsset $asset): array => [
                ltrim($asset->publicPath(), '/') => $asset->derivative_path ?: $asset->path,
            ])->all();
    }
}
