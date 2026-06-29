<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        $path = ltrim($path, '/');
        $candidates = array_unique([
            $path,
            rawurldecode($path),
            urldecode($path),
        ]);

        foreach ($candidates as $candidate) {
            $response = $this->tryServe($candidate);
            if ($response) {
                return $response;
            }
        }

        Log::warning('Media file not found', ['path' => $path]);

        abort(404);
    }

    private function tryServe(string $path): ?StreamedResponse
    {
        foreach ([config('filesystems.media_disk', 's3'), 'public'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    return Storage::disk($disk)->response($path, null, [
                        'Cache-Control' => 'public, max-age=31536000',
                        'Access-Control-Allow-Origin' => '*',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Media disk read failed', [
                    'disk' => $disk,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }
}
