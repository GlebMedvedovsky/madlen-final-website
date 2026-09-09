<?php

namespace App\Http\Controllers;

use App\Models\ProductionPublication;
use App\Services\ProductionPublicationStatus;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublisherPublicationController extends Controller
{
    public function package(Request $request, ProductionPublication $publication): BinaryFileResponse
    {
        $this->authorizeRunner($request);
        abort_unless(in_array($publication->status, ['prepared', 'queued', 'building'], true), 409);
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
        ]);

        $updated = $statuses->update(
            $publication,
            $validated['status'],
            $validated['error'] ?? null,
            $validated['target_release'] ?? null,
        );

        return ['id' => $updated->id, 'status' => $updated->status];
    }

    private function authorizeRunner(Request $request): void
    {
        abort_unless(config('madlen.production_connected') && config('madlen.production_publisher') === 'github-actions', 404);
        $expected = (string) config('madlen.publisher.api_token');
        $provided = (string) $request->bearerToken();
        abort_unless($expected !== '' && $provided !== '' && hash_equals($expected, $provided), 401);
    }
}
