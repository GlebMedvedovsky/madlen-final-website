<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaProcessor
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    private const VIDEO_MIMES = ['video/mp4', 'video/webm'];

    public function __construct(private RuntimeFilesystem $files) {}

    public function inspect(MediaAsset $asset): void
    {
        $absolutePath = $this->absolutePath($asset);
        if (! is_file($absolutePath)) {
            throw ValidationException::withMessages(['path' => 'Die Mediendatei wurde nicht gefunden.']);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath) ?: 'application/octet-stream';
        $size = filesize($absolutePath) ?: 0;
        $allowed = array_merge(self::IMAGE_MIMES, self::VIDEO_MIMES);
        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages(['path' => 'Erlaubt sind JPEG, PNG, WebP, MP4 und WebM.']);
        }

        $limit = str_starts_with($mime, 'video/')
            ? config('madlen.media.max_video_kb') * 1024
            : config('madlen.media.max_image_kb') * 1024;
        if ($size > $limit) {
            throw ValidationException::withMessages(['path' => 'Die Datei überschreitet das konfigurierte Größenlimit.']);
        }

        $changes = [
            'mime_type' => $mime,
            'size_bytes' => $size,
            'checksum' => hash_file('sha256', $absolutePath),
            'original_name' => $asset->original_name ?: basename($asset->path),
            'kind' => str_starts_with($mime, 'video/') ? 'video' : 'image',
        ];

        if (str_starts_with($mime, 'image/')) {
            $dimensions = getimagesize($absolutePath);
            if (! $dimensions) {
                throw ValidationException::withMessages(['path' => 'Die Bilddatei ist beschädigt oder nicht lesbar.']);
            }
            [$width, $height] = $dimensions;
            if (max($width, $height) > config('madlen.media.max_dimension')) {
                throw ValidationException::withMessages(['path' => 'Die Bildabmessungen sind größer als erlaubt.']);
            }
            $changes['width'] = $width;
            $changes['height'] = $height;

            if (! $asset->source_managed) {
                $changes['derivative_path'] = $this->createWebDerivative($asset, $absolutePath, $mime);
            }
        }

        $asset->forceFill($changes)->saveQuietly();
    }

    private function absolutePath(MediaAsset $asset): string
    {
        if ($asset->source_managed) {
            return rtrim(config('madlen.repository_root'), '/').'/public/'.ltrim($asset->path, '/');
        }

        return Storage::disk('local')->path($asset->path);
    }

    private function createWebDerivative(MediaAsset $asset, string $absolutePath, string $mime): string
    {
        $image = imagecreatefromstring(file_get_contents($absolutePath));
        if (! $image) {
            throw ValidationException::withMessages(['path' => 'Das Bild konnte nicht verarbeitet werden.']);
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = @exif_read_data($absolutePath)['Orientation'] ?? 1;
            $image = match ($orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => $image,
            };
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxWidth = config('madlen.media.web_max_width');
        if ($width > $maxWidth) {
            $targetHeight = (int) round($height * ($maxWidth / $width));
            $resized = imagecreatetruecolor($maxWidth, $targetHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $targetHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        $relativePath = 'media/derivatives/'.$asset->id.'-'.substr(hash_file('sha256', $absolutePath), 0, 16).'.webp';
        $destination = Storage::disk('local')->path($relativePath);
        $this->files->ensureDirectory(dirname($destination));
        if (! imagewebp($image, $destination, 86) || ! is_file($destination)) {
            imagedestroy($image);
            throw ValidationException::withMessages(['path' => 'Die Web-Vorschau konnte nicht gespeichert werden.']);
        }
        imagedestroy($image);

        return $relativePath;
    }
}
