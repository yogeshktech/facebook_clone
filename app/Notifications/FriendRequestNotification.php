<?php

namespace App\Notifications;

use App\Events\LiveNotification;
use App\Models\Friendship;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FriendRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public Friendship $friendship) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->friendship->load('user');

        return [
            'type' => 'friend_request',
            'message' => $this->friendship->user->name.' sent you a friend request',
            'friendship_id' => $this->friendship->id,
            'user' => [
                'id' => $this->friendship->user->id,
                'name' => $this->friendship->user->name,
                'avatar_url' => $this->friendship->user->avatar_url,
            ],
            'url' => route('friends.index'),
        ];
    }

    public function toBroadcast(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
