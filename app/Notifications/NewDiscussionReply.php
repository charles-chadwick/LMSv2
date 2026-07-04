<?php

namespace App\Notifications;

use App\Models\DiscussionReply;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewDiscussionReply extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DiscussionReply $reply) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $discussion = $this->reply->discussion;

        return [
            'discussion_id' => $discussion->id,
            'course_slug' => $discussion->course->slug,
            'type' => 'new_reply',
            'actor_name' => $this->reply->author->name,
            'excerpt' => Str::limit($this->reply->body, 80),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
