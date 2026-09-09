<?php

namespace App\Services;

use App\Models\PreviewBuild;
use RuntimeException;

class ExternalPreviewBuilder
{
    public function __construct(
        private ExternalPreviewPackager $packager,
        private ExternalPreviewWorkflowDispatcher $dispatcher,
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
            $preview->update([
                'status' => 'failed',
                'progress_message' => 'Die externe Vorschau konnte nicht gestartet werden.',
                'error_message' => mb_substr($error->getMessage(), 0, 60000),
                'completed_at' => now(),
            ]);
            throw $error;
        }
    }
}
