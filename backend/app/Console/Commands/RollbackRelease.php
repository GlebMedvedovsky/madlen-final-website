<?php

namespace App\Console\Commands;

use App\Models\Release;
use App\Services\ReleasePublisher;
use Illuminate\Console\Command;

class RollbackRelease extends Command
{
    protected $signature = 'madlen:rollback {version : Nummer des bekannten guten Releases}';
    protected $description = 'Aktiviert ein vorhandenes vollständiges lokales Release erneut';

    public function handle(ReleasePublisher $publisher): int
    {
        $release = Release::query()->where('version', $this->argument('version'))->first();
        if (! $release) {
            $this->error('Das angegebene Release wurde nicht gefunden.');
            return self::FAILURE;
        }

        try {
            $publisher->rollback($release);
            $this->info("Release {$release->version} ist wieder aktiv.");
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }
}
