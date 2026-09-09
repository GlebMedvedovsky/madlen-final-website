<?php

namespace App\Console\Commands;

use App\Services\ReleasePublisher;
use Illuminate\Console\Command;

class PublishContent extends Command
{
    protected $signature = 'madlen:publish';
    protected $description = 'Erstellt und aktiviert lokal ein unveränderliches Astro-Release';

    public function handle(ReleasePublisher $publisher): int
    {
        try {
            $release = $publisher->publish();
            $this->info("Release {$release->version} ist aktiv: ".config('madlen.public_url'));
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }
}
