<?php

namespace App\Console\Commands;

use App\Models\Release;
use App\Services\ReleaseExporter;
use Illuminate\Console\Command;

class ExportRelease extends Command
{
    protected $signature = 'madlen:release:export {version? : Release-Nummer; ohne Angabe wird das aktive Release verwendet}';
    protected $description = 'Erstellt ein geprüftes statisches Paket für einen späteren externen Publisher';

    public function handle(ReleaseExporter $exporter): int
    {
        $release = filled($this->argument('version'))
            ? Release::query()->where('version', $this->argument('version'))->first()
            : Release::query()->where('status', 'active')->latest('published_at')->first();
        if (! $release) {
            $this->error('Kein passendes Release gefunden.');
            return self::FAILURE;
        }

        try {
            $result = $exporter->export($release);
            $this->info($result['archive']);
            $this->line('SHA-256: '.$result['checksum']);
            $this->warn('Produktiv-Upload ist nicht konfiguriert und wurde nicht ausgeführt.');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }
}
