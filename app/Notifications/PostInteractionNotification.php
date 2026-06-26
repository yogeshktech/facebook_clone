<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PostInteractionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $actor,
        public Post $post,
        public string $action
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $messages = [
            'like' => $this->actor->name.' liked your post',
            'comment' => $this->actor->name.' commented on your post',
            'share' => $this->actor->name.' shared your post',
        ];

        return [
            'type' => 'post_'.$this->action,
            'message' => $messages[$this->action] ?? 'New activity on your post',
            'post_id' => $this->post->id,
            'user' => [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
                'avatar_url' => $this->actor->avatar_url,
            ],
            'url' => route('feed.index').'#post-'.$this->post->id,
        ];
    }
}
