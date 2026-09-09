<?php

namespace App\Console\Commands;

use App\Services\PreviewCleanupService;
use Illuminate\Console\Command;

class CleanupPreviews extends Command
{
    protected $signature = 'madlen:previews:cleanup';

    protected $description = 'Entfernt ausschließlich abgelaufene private Vorschau-Dateien';

    public function handle(PreviewCleanupService $cleanup): int
    {
        $count = $cleanup->cleanupExpired();
        $this->info("{$count} abgelaufene Vorschau(en) wurden bereinigt.");

        return self::SUCCESS;
    }
}
