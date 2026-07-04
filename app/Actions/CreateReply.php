<?php

namespace App\Actions;

use App\Events\DiscussionReplyPosted;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\User;
use App\Notifications\NewDiscussionReply;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateReply
{
    use AsAction;

    /**
     * @param  array{body: string, parent_id?: int|null}  $data
     */
    public function handle(Discussion $discussion, User $author, array $data): DiscussionReply
    {
        $reply = $discussion->replies()->create([
            'user_id' => $author->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
        ]);

        broadcast(new DiscussionReplyPosted($reply));

        $parent = $reply->parent_id !== null ? $reply->parent : null;

        $recipients = collect([
            $discussion->author,
            $parent?->author,
            $discussion->course->instructor,
        ])
            ->filter()
            ->reject(fn (User $user): bool => $user->id === $author->id)
            ->unique('id')
            ->values();

        Notification::send($recipients, new NewDiscussionReply($reply));

        return $reply;
    }
}
