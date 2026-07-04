<?php

namespace App\Services;

use App\Events\NotificationEvent;
use App\Jobs\SendPushNotificationJob;
use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\Post;
use App\Models\SocialNotification;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationService
{
    public static function notify(
        User $receiver,
        ?User $sender,
        string $type,
        string $title,
        string $message,
        ?int $referenceId = null,
        ?string $url = null,
    ): SocialNotification {
        if ($sender && $sender->id === $receiver->id) {
            throw new \InvalidArgumentException('Cannot notify yourself.');
        }

        $notification = SocialNotification::create([
            'sender_id' => $sender?->id,
            'receiver_id' => $receiver->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'reference_id' => $referenceId,
            'url' => $url,
            'is_read' => false,
        ]);

        $notification->load('sender');

        try {
            event(new NotificationEvent($notification));
        } catch (\Throwable $e) {
            report($e);
        }

        SendPushNotificationJob::dispatch($notification);

        return $notification;
    }

    public static function chatMessage(Conversation $conversation, User $sender, Message $message): void
    {
        $conversation->loadMissing('users');

        $preview = $message->body
            ? Str::limit($message->body, 80)
            : ($message->media_path ? 'Sent you a photo/video' : 'New message');

        $title = $conversation->isGroup()
            ? $sender->name.' in '.($conversation->name ?: 'Group')
            : $sender->name.' messaged you';

        foreach ($conversation->users as $receiver) {
            if ($receiver->id === $sender->id) {
                continue;
            }

            try {
                self::notify(
                    receiver: $receiver,
                    sender: $sender,
                    type: 'message',
                    title: $title,
                    message: $preview,
                    referenceId: $conversation->id,
                    url: route('chat.show', $conversation),
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public static function postInteraction(User $receiver, User $sender, Post $post, string $action): void
    {
        $messages = [
            'like' => $sender->name.' liked your post',
            'comment' => $sender->name.' commented on your post',
            'share' => $sender->name.' shared your post',
        ];

        $message = $messages[$action] ?? 'New activity on your post';

        self::notify(
            receiver: $receiver,
            sender: $sender,
            type: $action,
            title: $message,
            message: $message,
            referenceId: $post->id,
            url: route('feed.index').'#post-'.$post->id,
        );
    }

    public static function friendRequest(User $receiver, Friendship $friendship): void
    {
        $friendship->loadMissing('user');
        $sender = $friendship->user;
        $message = $sender->name.' sent you a friend request';

        self::notify(
            receiver: $receiver,
            sender: $sender,
            type: 'friend_request',
            title: $message,
            message: $message,
            referenceId: $friendship->id,
            url: route('friends.index'),
        );
    }
}
