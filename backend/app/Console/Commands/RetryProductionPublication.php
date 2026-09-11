<?php

namespace App\Console\Commands;

use App\Models\ProductionPublication;
use App\Services\ProductionWorkflowDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Recovery of an existing immutable request, never creation of another snapshot. */
class RetryProductionPublication extends Command
{
    protected $signature = 'madlen:production:retry {publication} {--runner-stopped : GitHub-Lauf wurde überprüft und ist beendet}';
    protected $description = 'Fordert denselben unveränderlichen Auftrag nach einer geprüften Störung erneut an';

    public function handle(ProductionWorkflowDispatcher $dispatcher): int
    {
        if (! $this->option('runner-stopped')) {
            $this->error('Zuerst GitHub und current prüfen. Bestätigen Sie den beendeten Lauf mit --runner-stopped.');
            return self::FAILURE;
        }
        try {
            Cache::lock('madlen-production-publication', 180)->block(5, function () use ($dispatcher): void {
                $job = ProductionPublication::findOrFail($this->argument('publication'));
                if (! in_array($job->status, ['failed', 'prepared', 'queued', 'dispatch_unknown', 'building', 'uploading'], true)
                    || ProductionPublication::where('sequence', '>', $job->sequence)->exists()
                    || ! is_file($job->package_path ?? '')
                    || ! hash_equals($job->package_checksum, hash_file('sha256', $job->package_path))) {
                    throw new \RuntimeException('Der Auftrag kann nicht sicher wiederholt werden. Keine neuen Pakete wurden erstellt.');
                }
                $dispatcher->dispatch($job);
                $this->info('Derselbe Auftrag: '.$job->id.' / '.$job->fresh()->status);
            });
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }
}
