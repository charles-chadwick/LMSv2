<?php

namespace App\Events;

use App\Http\Resources\DiscussionReplyResource;
use App\Models\DiscussionReply;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscussionReplyPosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DiscussionReply $reply) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('discussions.'.$this->reply->discussion_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->reply->loadMissing('author');

        return DiscussionReplyResource::make($this->reply)->resolve(request());
    }
}
