<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class CreateBackup extends Command
{
    protected $signature = 'madlen:backup';
    protected $description = 'Sichert Madlen-Datenbank und Medien gemeinsam';

    public function handle(BackupService $service): int
    {
        $backup = $service->create();
        $this->info("Backup {$backup->id} erstellt.");
        return self::SUCCESS;
    }
}
