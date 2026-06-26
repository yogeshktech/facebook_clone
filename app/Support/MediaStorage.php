<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaStorage
{
    public static function disk(): string
    {
        return config('filesystems.media_disk', 's3');
    }

    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        foreach (['s3', 'public'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    $configuredUrl = config('filesystems.disks.'.$disk.'.url');
                    if ($configuredUrl) {
                        return rtrim($configuredUrl, '/').'/'.ltrim($path, '/');
                    }

                    return Storage::disk($disk)->url($path);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $baseUrl = config('filesystems.disks.s3.url');
        if ($baseUrl) {
            return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
        }

        return Storage::disk('public')->url($path);
    }

    public static function store(UploadedFile $file, string $folder): string
    {
        $primaryDisk = self::disk();

        try {
            $path = Storage::disk($primaryDisk)->putFile($folder, $file, 'public');
            if ($path) {
                return $path;
            }
        } catch (\Throwable $e) {
            Log::warning('Primary media upload failed, using public disk.', [
                'disk' => $primaryDisk,
                'error' => $e->getMessage(),
            ]);
        }

        return $file->store($folder, ['disk' => 'public', 'visibility' => 'public']);
    }

    public static function delete(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        foreach (['s3', 'public'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            } catch (\Throwable) {
                continue;
            }
        }
    }

    public static function mediaType(UploadedFile $file): string
    {
        return str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image';
    }
}
