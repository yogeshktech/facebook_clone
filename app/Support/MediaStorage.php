<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        if ((str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) && ! self::isOurMediaUrl($path)) {
            return $path;
        }

        $relativePath = self::normalizeStoredPath($path);
        $publicBase = self::publicBaseUrl();

        if ($publicBase) {
            return rtrim($publicBase, '/').'/'.ltrim($relativePath, '/');
        }

        $awsUrl = config('filesystems.disks.s3.url');
        if ($awsUrl && ! self::mustUseProxy()) {
            $direct = rtrim($awsUrl, '/').'/'.ltrim($relativePath, '/');

            // Never serve insecure MinIO URLs on an HTTPS page (browser blocks mixed content).
            if (self::isInsecureUrl($direct) && self::requestIsSecure()) {
                return request()->getSchemeAndHttpHost().'/media/'.ltrim($relativePath, '/');
            }

            return $direct;
        }

        return Storage::disk('public')->url($relativePath);
    }

    public static function normalizeStoredPath(string $path): string
    {
        return self::extractRelativePath($path);
    }

    public static function isOurMediaUrl(string $path): bool
    {
        if (! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
            return true;
        }

        $awsUrl = rtrim((string) config('filesystems.disks.s3.url'), '/');
        if ($awsUrl !== '' && str_starts_with($path, $awsUrl)) {
            return true;
        }

        $endpoint = rtrim((string) config('filesystems.disks.s3.endpoint'), '/');
        if ($endpoint !== '' && str_starts_with($path, $endpoint)) {
            return true;
        }

        if (str_contains($path, '/fb-media/') || str_contains($path, '116.203.133.249:9000')) {
            return true;
        }

        if (preg_match('#/media/.+#', $path)) {
            return true;
        }

        return str_contains($path, '/storage/');
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

    public static function storeEncrypted(UploadedFile $file, string $folder): string
    {
        $contents = file_get_contents($file->getRealPath());
        $encrypted = ChatEncryption::encryptBytes($contents);
        $filename = trim($folder, '/').'/'.Str::uuid().'.enc';
        $primaryDisk = self::disk();

        try {
            Storage::disk($primaryDisk)->put($filename, $encrypted);
            if (Storage::disk($primaryDisk)->exists($filename)) {
                return $filename;
            }
        } catch (\Throwable $e) {
            Log::warning('Encrypted media upload failed on primary disk.', [
                'disk' => $primaryDisk,
                'error' => $e->getMessage(),
            ]);
        }

        Storage::disk('public')->put($filename, $encrypted);

        return $filename;
    }

    public static function readEncrypted(string $path): ?string
    {
        $relativePath = self::extractRelativePath($path);

        foreach ([config('filesystems.media_disk', 's3'), 'public'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($relativePath)) {
                    $encrypted = Storage::disk($disk)->get($relativePath);

                    return ChatEncryption::decryptBytes($encrypted);
                }
            } catch (\Throwable $e) {
                Log::warning('Encrypted media read failed', [
                    'disk' => $disk,
                    'path' => $relativePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
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

    /**
     * HTTPS proxy base for browser-facing image URLs (avoids mixed-content blocks).
     */
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
        if ($appUrl !== '' && ! self::isLocalUrl($appUrl)) {
            return $appUrl.'/media';
        }

        // APP_URL still localhost on live — use the actual HTTPS domain from the request.
        if (! app()->runningInConsole() && self::requestIsSecure()) {
            return request()->getSchemeAndHttpHost().'/media';
        }

        return null;
    }

    private static function mustUseProxy(): bool
    {
        if (config('filesystems.media_public_url')) {
            return true;
        }

        if (config('filesystems.media_disk') !== 's3') {
            return false;
        }

        $appUrl = (string) config('app.url', '');
        if ($appUrl !== '' && ! self::isLocalUrl($appUrl)) {
            return true;
        }

        return ! app()->runningInConsole() && self::requestIsSecure();
    }

    private static function requestIsSecure(): bool
    {
        if (app()->runningInConsole()) {
            return false;
        }

        $request = request();

        return $request->isSecure()
            || $request->header('X-Forwarded-Proto') === 'https'
            || $request->header('X-Forwarded-Ssl') === 'on';
    }

    private static function isLocalUrl(string $url): bool
    {
        return str_contains($url, 'localhost')
            || str_contains($url, '127.0.0.1')
            || str_contains($url, '[::1]');
    }

    private static function isInsecureUrl(string $url): bool
    {
        return str_starts_with($url, 'http://');
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

        if (preg_match('#/fb-media/(.+)$#', $path, $matches)) {
            return $matches[1];
        }

        if (preg_match('#/media/(.+)$#', $path, $matches)) {
            return $matches[1];
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '' && str_starts_with($path, $appUrl.'/media/')) {
            return ltrim(substr($path, strlen($appUrl.'/media/')), '/');
        }

        return ltrim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
    }
}
