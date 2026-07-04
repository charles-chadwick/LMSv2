<?php

namespace App\Actions;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessage;
use Lorisleiva\Actions\Concerns\AsAction;

class SendMessage
{
    use AsAction;

    /**
     * @param  array{body: string}  $data
     */
    public function handle(Conversation $conversation, User $sender, array $data): Message
    {
        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body' => $data['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        broadcast(new MessageSent($message));

        $conversation->otherParticipant($sender)->notify(new NewMessage($message));

        return $message;
    }
}
