<?php

namespace App\Notifications;

use App\Models\Discussion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewDiscussionQuestion extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Discussion $discussion) {}

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
        return [
            'discussion_id' => $this->discussion->id,
            'course_slug' => $this->discussion->course->slug,
            'type' => 'new_question',
            'actor_name' => $this->discussion->author->name,
            'excerpt' => Str::limit($this->discussion->title, 80),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
