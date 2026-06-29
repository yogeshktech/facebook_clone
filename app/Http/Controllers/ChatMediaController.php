<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Support\ChatEncryption;
use App\Support\MediaStorage;
use Symfony\Component\HttpFoundation\Response;

class ChatMediaController extends Controller
{
    public function show(Message $message): Response
    {
        $user = auth()->user();
        $inConversation = $message->conversation()
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->exists();

        if (! $user->isAdmin() && ! $inConversation) {
            abort(403);
        }

        if (! $message->media_path || ! ChatEncryption::isEncryptedMedia($message->media_path)) {
            abort(404);
        }

        $contents = MediaStorage::readEncrypted($message->media_path);

        if ($contents === null) {
            abort(404);
        }

        $mime = match ($message->media_type) {
            'video' => 'video/mp4',
            'image' => 'image/jpeg',
            default => 'application/octet-stream',
        };

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
