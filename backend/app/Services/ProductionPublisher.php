<?php

namespace App\Services;

use App\Models\ProductionPublication;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class ProductionPublisher
{
    public function __construct(
        private ProductionPublicationPackager $packager,
        private ProductionWorkflowDispatcher $dispatcher,
    ) {}

    public function publish(): ProductionPublication
    {
        if (! config('madlen.production_connected') || config('madlen.production_publisher') !== 'github-actions') {
            throw new RuntimeException('Der Produktiv-Publisher ist nicht konfiguriert. Die öffentliche Website wurde nicht verändert.');
        }

        $lock = Cache::lock('madlen-production-publication', 180);
        if (! $lock->get()) {
            throw new RuntimeException('Eine Produktiv-Veröffentlichung läuft bereits.');
        }

        $publication = null;
        try {
            $publication = $this->packager->prepare();
            $this->dispatcher->dispatch($publication);

            return $publication->refresh();
        } catch (\Throwable $error) {
            if ($publication?->exists && ! in_array($publication->status, ['active', 'failed'], true)) {
                $publication->update([
                    'status' => 'failed',
                    'progress_message' => 'Die Übergabe ist fehlgeschlagen; die öffentliche Website blieb unverändert.',
                    'error_message' => mb_substr($error->getMessage(), 0, 60000),
                ]);
            }
            throw $error;
        } finally {
            $lock->release();
        }
    }
}
