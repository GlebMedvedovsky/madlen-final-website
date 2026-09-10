<?php

namespace App\Services;

use App\Data\ProjectPreviewSnapshot;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Project;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectPreviewSnapshotFactory
{
    /**
     * @param  array<string, mixed>|Arrayable<string, mixed>  $formState
     */
    public function make(Project $project, array|Arrayable $formState): ProjectPreviewSnapshot
    {
        if ($project->trashed()) {
            throw ValidationException::withMessages([
                'data.slug' => 'Ein gelöschtes Projekt kann nicht als Vorschau geöffnet werden.',
            ]);
        }

        $state = $formState instanceof Arrayable ? $formState->toArray() : $formState;
        $gallery = $state['mediaItems'] ?? [];
        if ($gallery instanceof Arrayable) {
            $gallery = $gallery->toArray();
        }
        if (is_array($gallery)) {
            $gallery = array_map(
                fn (mixed $item): mixed => $item instanceof Arrayable ? $item->toArray() : $item,
                array_values($gallery),
            );
        }

        $candidate = [
            ...Arr::only($state, [
                'slug', 'category_id', 'cover_media_id', 'position',
                'title_de', 'title_en', 'description_de', 'description_en',
            ]),
            'slug' => $state['slug'] ?? $project->slug,
            'mediaItems' => $gallery,
        ];

        $validated = Validator::make(['data' => $candidate], [
            'data.slug' => ['required', 'string', Rule::in([$project->slug]), 'regex:/\A[a-zA-Z0-9-]+\z/'],
            'data.category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'data.cover_media_id' => [
                'bail',
                'required',
                'uuid',
                Rule::exists('media_assets', 'id')->where(
                    fn ($query) => $query->where('kind', 'image')->whereNull('deleted_at'),
                ),
            ],
            'data.position' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'data.title_de' => ['required', 'string', 'max:255'],
            'data.title_en' => ['required', 'string', 'max:255'],
            'data.description_de' => ['required', 'string'],
            'data.description_en' => ['required', 'string'],
            'data.mediaItems' => ['present', 'array'],
            'data.mediaItems.*' => ['array'],
            'data.mediaItems.*.media_asset_id' => [
                'bail',
                'required',
                'uuid',
                'distinct',
                Rule::exists('media_assets', 'id')->where(
                    fn ($query) => $query->where('kind', 'image')->whereNull('deleted_at'),
                ),
            ],
            'data.mediaItems.*.side' => ['required', Rule::in(['left', 'right'])],
        ], [
            'data.slug.in' => 'Die stabile URL-Kennung der geöffneten Seite wurde unerwartet verändert.',
            'data.slug.regex' => 'Die stabile URL-Kennung kann für diese Vorschau nicht sicher verwendet werden.',
            'data.cover_media_id.uuid' => 'Das aktuelle Titelbild ist noch nicht als vorhandenes Medienobjekt verfügbar.',
            'data.cover_media_id.exists' => 'Das gewählte Titelbild ist nicht mehr verfügbar. Bitte wählen Sie ein vorhandenes Bild.',
            'data.mediaItems.array' => 'Die aktuelle Galerie kann nicht als Vorschau verarbeitet werden.',
            'data.mediaItems.*.media_asset_id.uuid' => 'Ein Galeriebild ist noch nicht als vorhandenes Medienobjekt verfügbar.',
            'data.mediaItems.*.media_asset_id.exists' => 'Ein Galeriebild ist nicht mehr verfügbar. Bitte wählen Sie ein vorhandenes Bild.',
            'data.mediaItems.*.media_asset_id.distinct' => 'Jedes Galeriebild darf in einem Projekt nur einmal verwendet werden.',
            'data.mediaItems.*.side.in' => 'Bitte wählen Sie für jedes Galeriebild Links oder Rechts.',
        ], [
            'data.category_id' => 'Kategorie',
            'data.cover_media_id' => 'Titelbild',
            'data.position' => 'Reihenfolge',
            'data.title_de' => 'deutscher Titel',
            'data.title_en' => 'englischer Titel',
            'data.description_de' => 'deutsche Beschreibung',
            'data.description_en' => 'englische Beschreibung',
            'data.mediaItems' => 'Galeriebilder',
        ])->validate()['data'];

        $category = Category::query()->findOrFail($validated['category_id']);
        $assetIds = collect([$validated['cover_media_id']])
            ->merge(collect($validated['mediaItems'])->pluck('media_asset_id'))
            ->unique()
            ->values();
        $assets = MediaAsset::query()
            ->where('kind', 'image')
            ->whereIn('id', $assetIds)
            ->get()
            ->keyBy(fn (MediaAsset $asset): string => (string) $asset->getKey());

        $cover = $assets->get($validated['cover_media_id']);
        if (! $cover) {
            throw ValidationException::withMessages([
                'data.cover_media_id' => 'Das gewählte Titelbild ist nicht mehr verfügbar.',
            ]);
        }

        $images = collect($validated['mediaItems'])
            ->values()
            ->map(function (array $item, int $index) use ($assets, $validated): array {
                $asset = $assets->get($item['media_asset_id']);
                if (! $asset) {
                    throw ValidationException::withMessages([
                        "data.mediaItems.{$index}.media_asset_id" => 'Dieses Galeriebild ist nicht mehr verfügbar.',
                    ]);
                }

                return [
                    'src' => $asset->publicPath(),
                    'altDe' => $asset->alt_de ?: $validated['title_de'].' – Fotografie von Madlen Medvedovskyy',
                    'altEn' => $asset->alt_en ?: $validated['title_en'].' — photography by Madlen Medvedovskyy',
                    'order' => $index + 1,
                    'side' => $item['side'],
                ];
            })
            ->all();

        $manifestProject = [
            'slug' => $project->slug,
            'title' => ['de' => $validated['title_de'], 'en' => $validated['title_en']],
            'category' => $category->key,
            'cover' => $cover->publicPath(),
            'description' => ['de' => $validated['description_de'], 'en' => $validated['description_en']],
            'images' => $images,
        ];

        return new ProjectPreviewSnapshot(
            projectId: (string) $project->getKey(),
            slug: $project->slug,
            position: (int) $validated['position'],
            manifestProject: $manifestProject,
        );
    }
}
