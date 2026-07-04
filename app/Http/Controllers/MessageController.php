<?php

namespace App\Http\Controllers;

use App\Actions\StartConversation;
use App\Http\Requests\Message\StartConversationRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->where(fn ($query) => $query->where('student_id', $user->id)->orWhere('instructor_id', $user->id))
            ->with(['student', 'instructor', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($query) => $query
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (Conversation $conversation): array => [
                'id' => $conversation->id,
                'other' => UserSummaryResource::make($conversation->otherParticipant($user))->resolve($request),
                'last_message' => $conversation->latestMessage !== null
                    ? Str::limit($conversation->latestMessage->body, 60)
                    : null,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'unread_count' => $conversation->unread_count,
            ]);

        return Inertia::render('Messages/Index', [
            'conversations' => $conversations,
        ]);
    }

    public function store(StartConversationRequest $request): RedirectResponse
    {
        $target = User::findOrFail($request->validated()['user_id']);

        $conversation = StartConversation::run($request->user(), $target);

        return redirect()->route('conversations.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorize('view', $conversation);

        $user = $request->user();

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $conversation->load(['messages' => fn ($query) => $query->with('sender')->oldest()]);

        return Inertia::render('Messages/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'other' => UserSummaryResource::make($conversation->otherParticipant($user))->resolve($request),
                'messages' => MessageResource::collection($conversation->messages)->resolve($request),
            ],
        ]);
    }
}
