<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Mime\MimeTypes;

class AdminMediaController extends Controller
{
    public function __invoke(MediaAsset $mediaAsset): BinaryFileResponse
    {
        abort_if($mediaAsset->trashed(), 404);

        if ($mediaAsset->source_managed) {
            $root = realpath(rtrim(config('madlen.repository_root'), '/').'/public');
            $candidate = $root.'/'.ltrim($mediaAsset->path, '/');
        } else {
            $root = realpath(Storage::disk('local')->path(''));
            $candidate = Storage::disk('local')->path(
                $mediaAsset->kind === 'image'
                    ? ($mediaAsset->derivative_path ?: $mediaAsset->path)
                    : $mediaAsset->path,
            );
        }

        $resolved = realpath($candidate);
        abort_unless(
            $root && $resolved && is_file($resolved) && str_starts_with($resolved, $root.DIRECTORY_SEPARATOR),
            404,
        );

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($resolved)
            ?: (MimeTypes::getDefault()->guessMimeType($resolved) ?? 'application/octet-stream');

        return response()->file($resolved, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
