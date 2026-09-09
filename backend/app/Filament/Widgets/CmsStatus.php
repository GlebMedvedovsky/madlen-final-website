<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\Release;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CmsStatus extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $active = Release::query()->where('status', 'active')->latest('published_at')->first();
        $projectsUrl = ProjectResource::canViewAny() ? ProjectResource::getUrl('index') : null;
        $mediaUrl = MediaAssetResource::canViewAny() ? MediaAssetResource::getUrl('index') : null;
        $releasesUrl = ReleaseResource::canViewAny() ? ReleaseResource::getUrl('index') : null;

        return [
            Stat::make('Projekte', Project::query()->count())
                ->description($this->description(
                    Project::query()->where('status', 'draft')->count().' Entwürfe',
                    'Projekte ansehen',
                    $projectsUrl,
                ))
                ->descriptionIcon($projectsUrl ? Heroicon::ArrowRight : null)
                ->url($projectsUrl),
            Stat::make('Medien', MediaAsset::query()->count())
                ->description($this->description(
                    'Originale bleiben außerhalb der Builds erhalten',
                    'Medien öffnen',
                    $mediaUrl,
                ))
                ->descriptionIcon($mediaUrl ? Heroicon::ArrowRight : null)
                ->url($mediaUrl),
            Stat::make('Aktive Veröffentlichung', $active ? 'Release '.$active->version : 'Noch keine')
                ->description($this->description(
                    $active?->published_at?->format('d.m.Y H:i') ?? 'Lokale Veröffentlichung ausstehend',
                    'Veröffentlichungen ansehen',
                    $releasesUrl,
                ))
                ->descriptionIcon($releasesUrl ? Heroicon::ArrowRight : null)
                ->url($releasesUrl),
            Stat::make('Produktiv-Publisher', config('madlen.production_connected') && config('madlen.production_publisher') !== 'unconfigured' ? 'Verbunden' : 'Nicht konfiguriert')
                ->description($this->description(
                    'Lokale Veröffentlichung funktioniert unabhängig davon',
                    'Veröffentlichungsstatus ansehen',
                    $releasesUrl,
                ))
                ->descriptionIcon($releasesUrl ? Heroicon::ArrowRight : null)
                ->url($releasesUrl),
        ];
    }

    private function description(string $context, string $hint, ?string $url): string
    {
        return $url ? "{$context} · {$hint}" : $context;
    }
}
