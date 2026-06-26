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

        $relativePath = self::extractRelativePath($path);
        $publicBase = self::publicBaseUrl();

        if ($publicBase) {
            return rtrim($publicBase, '/').'/'.ltrim($relativePath, '/');
        }

        $awsUrl = config('filesystems.disks.s3.url');
        if ($awsUrl) {
            return rtrim($awsUrl, '/').'/'.ltrim($relativePath, '/');
        }

        return Storage::disk('public')->url($relativePath);
    }

    public static function store(UploadedFile $file, string $folder): string
    {
        if (ImageCompressor::shouldCompress($file)) {
            $file = ImageCompressor::compress($file);
        }

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
        if (! $path) {
            return;
        }

        $relativePath = self::extractRelativePath($path);

        foreach ([config('filesystems.media_disk', 's3'), 'public'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($relativePath)) {
                    Storage::disk($disk)->delete($relativePath);
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

    private static function publicBaseUrl(): ?string
    {
        $configured = config('filesystems.media_public_url');
        if ($configured) {
            return rtrim($configured, '/');
        }

        if (config('filesystems.media_disk') !== 's3') {
            return null;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl === '' || self::isLocalUrl($appUrl)) {
            return null;
        }

        return $appUrl.'/media';
    }

    private static function isLocalUrl(string $url): bool
    {
        return str_contains($url, 'localhost')
            || str_contains($url, '127.0.0.1')
            || str_contains($url, '[::1]');
    }

    private static function extractRelativePath(string $path): string
    {
        if (! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
            return ltrim($path, '/');
        }

        $awsUrl = rtrim((string) config('filesystems.disks.s3.url'), '/');
        if ($awsUrl !== '' && str_starts_with($path, $awsUrl)) {
            return ltrim(substr($path, strlen($awsUrl)), '/');
        }

        $bucket = (string) config('filesystems.disks.s3.bucket');
        if ($bucket !== '' && preg_match('#/'.preg_quote($bucket, '#').'/(.+)$#', $path, $matches)) {
            return $matches[1];
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '' && str_starts_with($path, $appUrl.'/media/')) {
            return ltrim(substr($path, strlen($appUrl.'/media/')), '/');
        }

        return ltrim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
    }
}
