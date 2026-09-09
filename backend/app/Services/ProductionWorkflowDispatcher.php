<?php

namespace App\Services;

use App\Models\ProductionPublication;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProductionWorkflowDispatcher
{
    public function dispatch(ProductionPublication $publication): void
    {
        if (! config('madlen.production_connected') || config('madlen.production_publisher') !== 'github-actions') {
            throw new RuntimeException('Der Produktiv-Publisher ist nicht verbunden. Es wurde nichts übertragen.');
        }

        $repository = trim((string) config('madlen.publisher.github_repository'));
        $workflow = trim((string) config('madlen.publisher.github_workflow'));
        $ref = trim((string) config('madlen.publisher.github_ref'));
        $token = (string) config('madlen.publisher.github_token');
        if (! preg_match('#\A[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+\z#', $repository)
            || ! preg_match('/\A[a-zA-Z0-9_.-]+\z/', $workflow)
            || $ref === ''
            || $token === '') {
            throw new RuntimeException('Die GitHub-Workflow-Verbindung ist unvollständig konfiguriert.');
        }

        $response = Http::acceptJson()
            ->withToken($token)
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'Madlen-CMS-Publisher',
            ])
            ->timeout(20)
            ->post("https://api.github.com/repos/{$repository}/actions/workflows/{$workflow}/dispatches", [
                'ref' => $ref,
                'inputs' => [
                    'publication_id' => $publication->id,
                    'sequence' => (string) $publication->sequence,
                    'package_sha256' => $publication->package_checksum,
                    'source_revision' => $publication->source_revision,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Der externe Build konnte nicht gestartet werden (HTTP {$response->status()}).");
        }

        $publication->update([
            'status' => 'queued',
            'progress_message' => 'Der unveränderliche Stand wurde an den externen Build übergeben.',
            'error_message' => null,
        ]);
    }
}
