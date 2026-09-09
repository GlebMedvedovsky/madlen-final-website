<?php

namespace App\Console\Commands;

use App\Services\StaticReleaseActivator;
use Illuminate\Console\Command;

class ActivateProductionRelease extends Command
{
    protected $signature = 'madlen:production:activate
        {publication : UUID des Veröffentlichungsauftrags}
        {sequence : Monoton steigende Auftragsnummer}
        {archive : Dateiname im konfigurierten Eingangsverzeichnis}
        {sha256 : Erwartete SHA-256-Prüfsumme}';

    protected $description = 'Prüft und aktiviert einen bereits extern gebauten statischen Produktiv-Release';

    public function handle(StaticReleaseActivator $activator): int
    {
        if (! config('madlen.production_connected') || config('madlen.production_publisher') !== 'github-actions') {
            $this->error('Der Produktiv-Publisher ist nicht verbunden. Es wurde nichts verändert.');

            return self::FAILURE;
        }

        try {
            $release = $activator->activate(
                (string) $this->argument('publication'),
                (int) $this->argument('sequence'),
                (string) $this->argument('archive'),
                (string) $this->argument('sha256'),
            );
            $this->info("Produktiv-Release {$release} wurde atomar aktiviert.");

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            $this->warn('Der zuvor aktive öffentliche Stand wurde nicht ersetzt.');

            return self::FAILURE;
        }
    }
}
