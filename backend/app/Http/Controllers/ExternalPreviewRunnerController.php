<?php

namespace App\Http\Controllers;

use App\Models\PreviewBuild;
use App\Services\ExternalPreviewResultImporter;
use App\Services\ExternalPreviewStatus;
use App\Services\PreviewCleanupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExternalPreviewRunnerController extends Controller
{
    public function package(Request $request, PreviewBuild $preview, PreviewCleanupService $cleanup): BinaryFileResponse
    {
        $this->authorizeRunner($request);
        abort_unless($preview->execution_mode === 'external', 404);
        if ($preview->expires_at->isPast() || $preview->status === 'expired') {
            $cleanup->expire($preview);
            abort(410, 'Die Vorschau ist abgelaufen.');
        }
        abort_unless(in_array($preview->status, ['prepared', 'queued', 'building'], true), 409);
        abort_unless($preview->package_path && is_file($preview->package_path), 404);

        $response = response()->download(
            $preview->package_path,
            basename($preview->package_path),
            [
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function status(
        Request $request,
        PreviewBuild $preview,
        ExternalPreviewStatus $statuses,
        ExternalPreviewResultImporter $results,
        PreviewCleanupService $cleanup,
    ): JsonResponse {
        $this->authorizeRunner($request);
        abort_unless($preview->execution_mode === 'external', 404);
        if ($preview->expires_at->isPast() || $preview->status === 'expired') {
            $cleanup->expire($preview);
            abort(410, 'Die Vorschau ist abgelaufen.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:building,ready,failed'],
            'error' => ['nullable', 'string', 'max:5000'],
            'result_archive' => ['nullable', 'string', 'max:255'],
            'result_sha256' => ['nullable', 'string', 'regex:/\A[0-9a-fA-F]{64}\z/'],
        ]);

        try {
            $updated = match ($validated['status']) {
                'building' => $statuses->building($preview),
                'failed' => $statuses->failed($preview, $validated['error'] ?? null),
                'ready' => $results->accept(
                    $preview,
                    (string) ($validated['result_archive'] ?? ''),
                    strtolower((string) ($validated['result_sha256'] ?? '')),
                ),
            };
        } catch (RuntimeException $error) {
            abort(409, $error->getMessage());
        }

        return response()->json([
            'id' => $updated->id,
            'status' => $updated->status,
            'expires_at' => $updated->expires_at->toIso8601String(),
        ]);
    }

    private function authorizeRunner(Request $request): void
    {
        abort_unless(config('madlen.external_preview_connected')
            && config('madlen.preview_execution') === 'external'
            && config('madlen.preview_runner.driver') === 'github-actions', 404);
        $expected = (string) config('madlen.preview_runner.api_token');
        $provided = (string) $request->bearerToken();
        abort_unless($expected !== '' && $provided !== '' && hash_equals($expected, $provided), 401);
    }
}
