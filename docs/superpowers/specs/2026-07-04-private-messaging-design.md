# Private Messaging (Student ↔ Instructor, Live) — Design

**Date:** 2026-07-04
**Branch:** feature/private-messaging
**Status:** Approved

## Summary

One-to-one private messaging between students and instructors: find-or-create conversations
per {student, instructor} pair, live message delivery via Reverb, and unread surfacing through
both a dedicated Messages badge and the existing notification bell. This is slice 3 of 3 in the
discussions+messaging feature (slices 1 Reverb foundation and 2 discussions are shipped).

## Goals

- 1-to-1 conversations, one per unique {student, instructor} pair, reused over time.
- Any student may message any instructor and vice versa (not enrollment-gated).
- Live message insertion in an open conversation (Reverb, per-conversation channel).
- Two unread surfaces: a dedicated Messages badge (unread messages) AND a notification-center
  entry per message (the existing bell).
- Reuse the notifications table, per-user channel, `UserSummaryResource`, and the
  bell/notification-center pattern built in slice 2.

## Non-Goals

- Message editing/deletion, attachments, rich text.
- Group conversations (strictly 1-to-1).
- Typing indicators / presence / online status.
- Student↔student or instructor↔instructor messaging.
- Admins as conversation participants (admins may view for moderation via `Gate::before`, but
  do not occupy a student/instructor slot and cannot start conversations).

## Decisions (from brainstorming)

- **Who can message:** any student ↔ any instructor (each conversation is exactly one Student +
  one Instructor participant). Not enrollment-gated.
- **Conversation granularity:** one per unordered {student, instructor} pair (not per-course).
- **Alerts:** both a dedicated Messages badge and a `NewMessage` notification-center entry.
- **Rate limiting:** message sending is `throttle:30,1` (open messaging is an abuse surface —
  same lesson as the discussions slice).

## Architecture

### 1. Data model

Migration `create_conversations_table`:

```php
Schema::create('conversations', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
    $table->timestamp('last_message_at')->nullable();
    $table->timestamps();
    $table->unique(['student_id', 'instructor_id']);
});
```

Migration `create_messages_table`:

```php
Schema::create('messages', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
    $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
    $table->text('body');
    $table->timestamp('read_at')->nullable();
    $table->timestamps();
});
```

Since every conversation is exactly one student + one instructor, the two typed columns enforce
the pairing and (with the unique index) uniqueness — no participant pivot needed. Each message
has exactly one recipient (the non-sender participant), so a single `read_at` captures read state.

Models:
- `Conversation` — `belongsTo student` (User), `belongsTo instructor` (User), `hasMany messages`.
  Helpers: `otherParticipant(User $user): User` (returns the participant who is not `$user`),
  `hasParticipant(User $user): bool`.
- `Message` — `belongsTo conversation`, `belongsTo sender` (User).

Factories + seeders for both.

### 2. Authorization — `ConversationPolicy`

- `view(User, Conversation)` / `send(User, Conversation)`:
  `$conversation->student_id === $user->id || $conversation->instructor_id === $user->id`.
- Conversation creation is gated in the `StartConversation` action (below), not a model policy
  ability, since no `Conversation` exists yet: the initiator and target must be one Student and
  one Instructor (opposite role categories) — same-role or self → 403.
- Admins bypass via the existing `Gate::before`.

### 3. Endpoints, controllers, actions

Routes (inside the `auth` + `verified` group):

```
GET    conversations                          conversations.index   MessageController@index
POST   conversations                          conversations.store    MessageController@store
GET    conversations/{conversation}           conversations.show     MessageController@show
POST   conversations/{conversation}/messages  messages.store         MessageController@sendMessage  (throttle:30,1)
```

- `index`: the user's conversations (as student or instructor), with the other participant +
  last message + per-conversation unread count, ordered by `last_message_at` desc. Renders
  `Messages/Index`.
- `store`: `StartConversationRequest` (target `user_id` required, exists). Delegates to
  `StartConversation::run($initiator, $target)`; redirects to `conversations.show`. The action
  resolves student/instructor slots by role, rejects same-role/self (403 via a domain check →
  `abort(403)`), and find-or-creates the pair's conversation.
- `show`: authorizes `view`; loads messages (with sender) oldest→newest; marks the viewer's
  unread messages read and the related unread `new_message` notifications read; renders
  `Messages/Show`.
- `sendMessage`: `StoreMessageRequest` (body required). authorizes `send`. Delegates to
  `SendMessage::run($conversation, $sender, $data)`; redirects back.

Actions (`App\Actions\*`, `::run()`):
- `StartConversation::run(User $initiator, User $target): Conversation` — validates opposite
  roles, orders slots (student→`student_id`, instructor→`instructor_id`), `firstOrCreate` on the
  pair.
- `SendMessage::run(Conversation $conversation, User $sender, array{body:string} $data): Message`
  — creates the message, sets `conversation.last_message_at = now()`, broadcasts `MessageSent`,
  and `Notification::send($conversation->otherParticipant($sender), new NewMessage($message))`.

### 4. Real-time (Reverb)

- `App\Events\MessageSent implements ShouldBroadcast`, `__construct(Message $message)`,
  `broadcastOn()` → `new PrivateChannel('conversations.'.$this->message->conversation_id)`,
  `broadcastWith()` returns the shaped message (`id`, `conversation_id`, `body`, `sender`
  [UserSummaryResource array], `created_at` ISO).
- `routes/channels.php`:

```php
use App\Models\Conversation;

Broadcast::channel('conversations.{conversation}', function (User $user, int $conversation): bool {
    $model = Conversation::find($conversation);

    return $model !== null && $user->can('view', $model);
});
```

- `Messages/Show.vue` subscribes to `conversations.{id}` and appends new messages live.

### 5. Notifications & unread badges

- `App\Notifications\NewMessage` — `via() = ['database', 'broadcast']`, `toArray()` +
  `toBroadcast()` carry `conversation_id`, `sender_name`, `excerpt`, `type: 'new_message'`.
  Broadcast on `App.Models.User.{id}` (recipient), as in slice 2.
- **Messages badge:** `HandleInertiaRequests` shares `auth.user.unread_messages_count` =
  count of messages in the user's conversations where `sender_id !== user->id` and
  `read_at` is null. `MessagesBadge.vue` (envelope icon) in `AuthenticatedLayout` links to
  `conversations.index` and increments on the per-user channel's `.notification` events where
  `type === 'new_message'`.
- **Bell:** the existing `NotificationBell` already increments on any `.notification`; the
  `NewMessage` DB notification appears in `Notifications/Index.vue`, which gains a `new_message`
  label + link to `route('conversations.show', notification.conversation_id)`.
- Opening a conversation marks its messages read (decrements the messages badge on next Inertia
  load) and marks that conversation's unread `new_message` notifications read (keeps the bell
  count honest).

### 6. Initiation UI

- `users.show` (profile) shows a "Message" button when the viewer and profile user are opposite
  role categories (one student, one instructor) and not the same user. It POSTs
  `conversations.store` with the profile user's `user_id`. The controller passes a
  `can_message` boolean to the profile page to gate the button.

## Data Flow (send message)

```
POST conversations/{id}/messages
  → authorize('send', $conversation)  (participant only)
  → SendMessage::run(...)
      → Message::create(...)
      → $conversation->update(['last_message_at' => now()])
      → broadcast(new MessageSent($message))  → Reverb → conversations.{id} listeners
      → Notification::send($otherParticipant, new NewMessage($message))  (db + broadcast)
  → redirect back to conversations.show
Open thread clients: Echo → append message live.
Recipient nav: bell + messages badge increment on the per-user channel.
```

## Error / Edge Handling

- Same-role or self conversation start → `abort(403)` in `StartConversation`.
- `firstOrCreate` on the unique (student_id, instructor_id) pair prevents duplicate conversations
  under a race (unique index is the backstop).
- Non-participant send/view → 403 (policy); non-participant channel subscribe → denied.
- Message send is `throttle:30,1` per user.
- Broadcasting/notifications are queued; failures never block the HTTP response.
- Recipient is always the non-sender participant; the sender is never notified of their own message.

## Testing

Feature (Pest, MariaDB + DatabaseTruncation, `seed(RolePermissionSeeder::class)`; fake
`Event`/`Notification` where a real broadcast would otherwise hit the down Reverb server):

1. `StartConversation` find-or-creates one conversation per pair (second start returns the same
   conversation, no duplicate); orders student/instructor slots correctly.
2. Role pairing enforced: student→instructor OK; student→student and instructor→instructor and
   self → 403.
3. `sendMessage`: participant can send (creates message, bumps `last_message_at`); non-participant
   → 403.
4. `MessageSent` broadcasts on `conversations.{id}` with the shaped payload; channel auth allows a
   participant and denies a non-participant (`/broadcasting/auth`).
5. `NewMessage` (Notification::fake) is sent to the recipient, not the sender; `type` is
   `new_message`.
6. Unread: `unread_messages_count` shared prop reflects unread; opening `conversations.show` marks
   the viewer's messages read and the related `new_message` notifications read.
7. Inbox ordering by `last_message_at` desc; per-conversation unread counts.
8. Rate limit: 30 message sends succeed, the 31st returns 429.
9. Profile `can_message` gate: true for student viewing instructor (and vice versa), false for
   same-role or self.

Browser (render-only — sending broadcasts to a down Reverb server would 500): inbox renders a
conversation; conversation thread renders existing messages with no JS errors (Echo subscribe is
locally authorized, WS failure handled by pusher-js).

## Files

**New:**
- `database/migrations/*_create_conversations_table.php`, `*_create_messages_table.php`
- `app/Models/Conversation.php`, `app/Models/Message.php`
- `database/factories/ConversationFactory.php`, `MessageFactory.php`
- `app/Policies/ConversationPolicy.php`
- `app/Http/Controllers/MessageController.php`
- `app/Http/Requests/Message/StartConversationRequest.php`, `StoreMessageRequest.php`
- `app/Actions/StartConversation.php`, `app/Actions/SendMessage.php`
- `app/Events/MessageSent.php`
- `app/Notifications/NewMessage.php`
- `app/Http/Resources/ConversationResource.php`, `MessageResource.php`
- `resources/js/Pages/Messages/Index.vue`, `Show.vue`
- `resources/js/Components/MessagesBadge.vue`
- `tests/Feature/Messages/*`, `tests/Browser/MessagingTest.php`

**Edit:**
- `routes/web.php` (conversation/message routes)
- `routes/channels.php` (`conversations.{conversation}` channel)
- `app/Http/Middleware/HandleInertiaRequests.php` (`unread_messages_count`)
- `resources/js/Layouts/AuthenticatedLayout.vue` (mount `MessagesBadge`)
- `resources/js/Pages/Notifications/Index.vue` (`new_message` label + conversation link)
- `app/Http/Controllers/UserProfileController.php` + `resources/js/Pages/Profile/Show.vue`
  (`can_message` + Message button)

## Follow-on

Completes the discussions+messaging feature. Deferred Reverb minor still open (tighten
`config/reverb.php` `allowed_origins` when the Reverb server actually deploys).
