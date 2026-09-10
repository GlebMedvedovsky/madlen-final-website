<?php

namespace App\Services;

use App\Models\PreviewBuild;
use RuntimeException;

class ExternalPreviewBuilder
{
    public function __construct(
        private ExternalPreviewPackager $packager,
        private ExternalPreviewWorkflowDispatcher $dispatcher,
        private ExternalPreviewStatus $statuses,
    ) {}

    public function build(): PreviewBuild
    {
        if (! config('madlen.external_preview_connected')
            || config('madlen.preview_runner.driver') !== 'github-actions') {
            throw new RuntimeException('Die externe Vorschau ist nicht verbunden. Die lokale Vorschau bleibt unverändert verfügbar.');
        }

        $preview = $this->packager->prepare();
        try {
            $this->dispatcher->dispatch($preview);

            return $preview->refresh();
        } catch (\Throwable $error) {
            $current = $preview->fresh();
            if ($current && in_array($current->status, ['building', 'ready'], true)) {
                return $current;
            }
            try {
                $this->statuses->failed($preview, $error->getMessage());
            } catch (\Throwable) {
                // A concurrent callback may already have advanced the job.
            }
            throw $error;
        }
    }
}
