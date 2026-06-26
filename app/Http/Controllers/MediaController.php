<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        $path = ltrim($path, '/');

        foreach ([config('filesystems.media_disk', 's3'), 'public'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    return Storage::disk($disk)->response($path, null, [
                        'Cache-Control' => 'public, max-age=31536000',
                    ]);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        abort(404);
    }
}
