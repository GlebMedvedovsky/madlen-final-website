<?php

namespace App\Http\Controllers;

use App\Models\PreviewBuild;
use App\Services\ExternalPreviewStorage;
use App\Services\PreviewCleanupService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;

class PreviewController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        PreviewCleanupService $cleanup,
        ExternalPreviewStorage $externalStorage,
        ?string $path = null,
    ): Response {
        $preview = PreviewBuild::query()
            ->where('token', $token)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $relative = trim((string) $path, '/');
        $isStatusPath = $relative === ''
            || (filled($preview->target_path) && hash_equals((string) $preview->target_path, $relative));
        if ($preview->expires_at->isPast() || $preview->status === 'expired') {
            try {
                $preview = $cleanup->expire($preview);
            } catch (\Throwable $error) {
                report($error);
                $preview->update([
                    'status' => 'expired',
                    'progress_message' => 'Die geschützte Vorschau ist abgelaufen.',
                ]);
            }

            abort_unless($isStatusPath, 404);

            return $this->statusPage($preview, 410);
        }

        if ($preview->status !== 'ready') {
            abort_unless($isStatusPath, 404);

            return $this->statusPage($preview, $preview->status === 'failed' ? 422 : 202);
        }

        try {
            $buildPath = $preview->execution_mode === 'external'
                ? $externalStorage->resolve(
                    (string) $preview->build_path,
                    'result_root',
                    'builds/'.$preview->token,
                )
                : (string) $preview->build_path;
        } catch (\RuntimeException) {
            abort(404);
        }
        $root = realpath($buildPath);
        abort_unless($root, 404);
        $candidate = $root.'/'.($relative ?: 'index.html');
        if (is_dir($candidate)) {
            $candidate .= '/index.html';
        }
        $resolved = realpath($candidate);
        abort_unless($resolved && str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) && is_file($resolved), 404);

        $extension = pathinfo($resolved, PATHINFO_EXTENSION);
        $mime = MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream';
        $response = response()->file($resolved, [
            'Content-Type' => $mime,
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function statusPage(PreviewBuild $preview, int $status): Response
    {
        $label = match ($preview->status) {
            'preparing', 'prepared', 'queued' => 'Vorschau wartet',
            'building' => 'Vorschau wird erstellt',
            'failed' => 'Vorschau fehlgeschlagen',
            'expired' => 'Vorschau abgelaufen',
            default => 'Vorschau wird vorbereitet',
        };
        $message = $preview->progress_message ?: 'Bitte warten Sie einen Moment.';
        $refresh = in_array($preview->status, ['preparing', 'prepared', 'queued', 'building'], true)
            ? '<meta http-equiv="refresh" content="4">'
            : '';
        $html = '<!doctype html><html lang="de"><head><meta charset="utf-8">'
            .$refresh
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<meta name="robots" content="noindex,nofollow,noarchive">'
            .'<title>'.e($label).' · Madlen</title>'
            .'<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#fffaf5;color:#111;font:16px/1.55 Arial,sans-serif}main{width:min(34rem,calc(100% - 2rem));padding:2rem;border:1px solid #eadde0;background:#fff;box-shadow:0 1rem 3rem rgba(50,30,35,.08)}h1{margin:0 0 1rem;color:#0338da;font:700 clamp(1.8rem,6vw,2.7rem)/1.05 Georgia,serif}p{margin:.5rem 0}.hint{color:#6b5b5e;font-size:.9rem}</style>'
            .'</head><body><main><h1>'.e($label).'</h1><p>'.e($message).'</p>'
            .($status === 202 ? '<p class="hint">Diese Seite aktualisiert sich automatisch.</p>' : '')
            .'</main></body></html>';

        return response($html, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ]);
    }
}
