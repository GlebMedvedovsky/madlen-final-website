<?php

namespace App\Services;

use App\Models\ProductionPublication;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class ProductionPublisher
{
    private const IN_FLIGHT_STATUSES = ['preparing', 'prepared', 'queued', 'dispatch_unknown', 'building', 'uploading'];

    public function __construct(
        private ProductionPublicationPackager $packager,
        private ProductionWorkflowDispatcher $dispatcher,
    ) {}

    public function publish(?string $requestId = null, ?Project $project = null, string $operation = 'site'): ProductionPublication
    {
        if (! config('madlen.production_connected') || config('madlen.production_publisher') !== 'github-actions') {
            throw new RuntimeException('Der Produktiv-Publisher ist nicht konfiguriert. Die öffentliche Website wurde nicht verändert.');
        }

        $requestId ??= (string) Str::uuid();
        if (! Str::isUuid($requestId) || ! in_array($operation, ['site', 'publish', 'unpublish', 'delete'], true)
            || (($operation === 'site') !== ($project === null))) {
            throw new RuntimeException('Ungültiger Veröffentlichungsauftrag.');
        }

        $lock = Cache::lock('madlen-production-publication', 180);
        if (! $lock->get()) {
            throw new RuntimeException('Eine Veröffentlichung wird vorbereitet. Bitte wiederholen Sie denselben Vorgang.');
        }

        try {
            if ($existing = ProductionPublication::where('request_id', $requestId)->first()) {
                if ($existing->requested_by !== auth()->id() || $existing->project_id !== $project?->id || $existing->operation !== $operation) {
                    throw new RuntimeException('Dieser Auftrag gehört zu einem anderen Vorgang.');
                }
                return $existing;
            }
            if ($this->inFlight()) {
                throw new RuntimeException('Ein anderer Produktiv-Auftrag läuft bereits. Warten Sie dessen Ergebnis ab.');
            }
            $publication = $this->packager->prepare($requestId, $project, $operation);
            try {
                $this->dispatcher->dispatch($publication);
            } catch (\Throwable $error) {
                $publication->update(['status' => 'failed', 'error_message' => 'Die Workflow-Konfiguration ist unvollständig.',
                    'progress_message' => 'Nichts wurde übertragen. Der gespeicherte Auftrag kann nach der Korrektur erneut gestartet werden.']);
                throw $error;
            }

            return $publication->refresh();
        } finally {
            $lock->release();
        }
    }

    public function inFlight(): ?ProductionPublication
    {
        return ProductionPublication::query()
            ->whereIn('status', self::IN_FLIGHT_STATUSES)
            ->orderByDesc('sequence')
            ->first();
    }
}
