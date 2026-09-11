<?php

namespace App\Http\Controllers;

use App\Models\ProductionPublication;
use App\Services\ProductionPublicationStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublisherPublicationController extends Controller
{
    public function package(Request $request, ProductionPublication $publication): BinaryFileResponse
    {
        $this->authorizeRunner($request);
        abort_unless(in_array($publication->status, ['prepared', 'queued', 'dispatch_unknown', 'building', 'uploading'], true), 409);
        abort_unless($publication->package_path && is_file($publication->package_path), 404);

        return response()->download(
            $publication->package_path,
            basename($publication->package_path),
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }

    public function status(Request $request, ProductionPublication $publication, ProductionPublicationStatus $statuses): array
    {
        $this->authorizeRunner($request);
        $validated = $request->validate([
            'status' => ['required', 'in:building,uploading,active,failed'],
            'error' => ['nullable', 'string', 'max:5000'],
            'target_release' => ['nullable', 'string', 'max:255', 'regex:/\A[a-zA-Z0-9._-]+\z/'],
            'runner_id' => ['required', 'string', 'regex:/\A[0-9]+-[0-9]+\z/', 'max:100'],
        ]);
        return Cache::lock('madlen-production-publication',180)->block(5, fn () => DB::transaction(function () use ($publication, $validated, $statuses): array {
        $publication = ProductionPublication::lockForUpdate()->findOrFail($publication->id);
        abort_unless($publication->runner_id === $validated['runner_id'], 409);
        if ($validated['status'] === 'active') {
            abort_unless($publication->status === 'active' && $publication->target_release === ($validated['target_release'] ?? null), 409);
            return ['id' => $publication->id, 'status' => 'active'];
        }
        // A lost SSH response is not evidence that activation failed.
        if ($publication->status === 'active') return ['id' => $publication->id, 'status' => 'active'];
        abort_if(in_array($publication->status, ['superseded', 'rolled_back'], true), 409);

        $updated = $statuses->update(
            $publication,
            $validated['status'],
            $validated['error'] ?? null,
            $validated['target_release'] ?? null,
        );

        return ['id' => $updated->id, 'status' => $updated->status];
        }));
    }

    public function claim(Request $request, ProductionPublication $publication): array
    {
        $this->authorizeRunner($request);
        $data = $request->validate(['runner_id' => ['required', 'string', 'regex:/\A[0-9]+-[0-9]+\z/', 'max:100']]);
        return Cache::lock('madlen-production-publication',180)->block(5, fn () => DB::transaction(function () use ($publication, $data): array {
            $publication = ProductionPublication::lockForUpdate()->findOrFail($publication->id);
            if (in_array($publication->status, ['active', 'superseded', 'rolled_back'], true)) {
                return ['claimed' => false, 'status' => $publication->status];
            }
            if ($publication->runner_id) {
                $old = array_map('intval', explode('-', $publication->runner_id));
                $next = array_map('intval', explode('-', $data['runner_id']));
                if ($next < $old || ($next > $old && ! in_array($publication->status, ['failed', 'queued', 'dispatch_unknown'], true))
                    || ($next === $old && $publication->status !== 'building')) {
                    return ['claimed' => false, 'status' => $publication->status];
                }
            }
            abort_unless($publication->package_checksum && is_file($publication->package_path ?? ''), 409);
            $publication->update(['runner_id' => $data['runner_id'], 'status' => 'building', 'error_message' => null,
                'progress_message' => 'Die zweisprachige Website wird aus dem unveränderlichen Stand gebaut.']);
            return ['claimed' => true, 'status' => 'building'];
        }));
    }

    private function authorizeRunner(Request $request): void
    {
        abort_unless(config('madlen.production_connected') && config('madlen.production_publisher') === 'github-actions', 404);
        $expected = (string) config('madlen.publisher.api_token');
        $provided = (string) $request->bearerToken();
        abort_unless($expected !== '' && $provided !== '' && hash_equals($expected, $provided), 401);
    }
}
