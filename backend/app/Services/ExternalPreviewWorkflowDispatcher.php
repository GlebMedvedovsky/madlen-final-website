<?php

namespace App\Services;

use App\Models\PreviewBuild;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExternalPreviewWorkflowDispatcher
{
    public function dispatch(PreviewBuild $preview): void
    {
        if (! config('madlen.external_preview_connected')
            || config('madlen.preview_execution') !== 'external'
            || config('madlen.preview_runner.driver') !== 'github-actions') {
            throw new RuntimeException('Die externe Vorschau ist nicht verbunden. Es wurde nichts übertragen.');
        }

        $repository = trim((string) config('madlen.preview_runner.github_repository'));
        $workflow = trim((string) config('madlen.preview_runner.github_workflow'));
        $ref = trim((string) config('madlen.preview_runner.github_ref'));
        $token = (string) config('madlen.preview_runner.github_token');
        if (! preg_match('#\A[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+\z#', $repository)
            || ! preg_match('/\A[a-zA-Z0-9_.-]+\z/', $workflow)
            || $ref === ''
            || $token === '') {
            throw new RuntimeException('Die GitHub-Verbindung für externe Vorschauen ist unvollständig konfiguriert.');
        }

        $response = Http::acceptJson()
            ->withToken($token)
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'Madlen-CMS-Preview',
            ])
            ->timeout(20)
            ->post("https://api.github.com/repos/{$repository}/actions/workflows/{$workflow}/dispatches", [
                'ref' => $ref,
                'inputs' => [
                    'preview_id' => $preview->id,
                    'package_sha256' => $preview->package_checksum,
                    'source_revision' => $preview->source_revision,
                    'expires_at' => $preview->expires_at->toIso8601String(),
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Der externe Vorschau-Build konnte nicht gestartet werden (HTTP {$response->status()}).");
        }

        $preview->update([
            'status' => 'queued',
            'progress_message' => 'Die Vorschau wartet auf den externen Build.',
            'error_message' => null,
        ]);
    }
}
