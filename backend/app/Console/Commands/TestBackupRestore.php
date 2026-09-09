<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Console\Command;

class TestBackupRestore extends Command
{
    protected $signature = 'madlen:backup:restore-test {backup : UUID des Backups}';
    protected $description = 'Prüft ein Backup ausschließlich in der getrennten _restore_test-Datenbank';

    public function handle(BackupService $service): int
    {
        $backup = Backup::query()->findOrFail($this->argument('backup'));
        $target = $service->restoreIntoTest($backup);
        $this->info("Wiederherstellungstest erfolgreich: {$target}");
        return self::SUCCESS;
    }
}
