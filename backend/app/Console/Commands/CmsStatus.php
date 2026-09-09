<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\ProjectMedia;
use App\Models\Release;
use App\Models\Service;
use Illuminate\Console\Command;

class CmsStatus extends Command
{
    protected $signature = 'madlen:status';
    protected $description = 'Zeigt reale lokale CMS-Zahlen und den aktiven Release-Stand';

    public function handle(): int
    {
        $active = Release::query()->where('status', 'active')->latest('published_at')->first();
        $this->table(['Bereich', 'Anzahl'], [
            ['Projekte', Project::query()->count()],
            ['Veröffentlichte Projekte', Project::query()->where('status', 'published')->count()],
            ['Kategorien', Category::query()->count()],
            ['Galerie-Zuordnungen', ProjectMedia::query()->count()],
            ['Medien', MediaAsset::query()->count()],
            ['Leistungen', Service::query()->count()],
            ['Aktives Release', $active?->version ?? '—'],
        ]);

        return self::SUCCESS;
    }
}
