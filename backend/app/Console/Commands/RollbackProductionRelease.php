<?php

namespace App\Console\Commands;

use App\Services\ProductionReleaseManager;
use Illuminate\Console\Command;

class RollbackProductionRelease extends Command
{
    protected $signature = 'madlen:production:rollback {release : Vorhandener Ziel-Release, z. B. 12-UUID}';

    protected $description = 'Setzt den Produktivzeiger und die zugehörigen Projektstatus auf einen behaltenen Release zurück';

    public function handle(ProductionReleaseManager $activator): int
    {
        if (! config('madlen.production_connected') || config('madlen.production_publisher') !== 'github-actions') {
            $this->error('Der Produktiv-Publisher ist nicht verbunden. Es wurde nichts verändert.');

            return self::FAILURE;
        }

        try {
            $release = $activator->rollback((string) $this->argument('release'));
            $this->info("Produktiv-Release {$release} ist wieder aktiv. Projektstatus wurden abgeglichen; Texte und Uploads wurden nicht überschrieben.");

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
