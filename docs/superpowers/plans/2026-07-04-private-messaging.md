# Private Messaging (Student ↔ Instructor, Live) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 1-to-1 private messaging between students and instructors — find-or-create conversations per {student, instructor} pair, live message delivery via Reverb, and unread surfacing via a dedicated Messages badge plus the existing notification bell.

**Architecture:** `conversations(student_id, instructor_id, last_message_at)` + `messages(conversation_id, sender_id, body, read_at)`. Thin `MessageController` authorizes via `ConversationPolicy` (participant) and delegates to `StartConversation` / `SendMessage` actions; `SendMessage` broadcasts `MessageSent` on `conversations.{id}` and notifies the recipient with a `NewMessage` (database + broadcast) notification. Frontend: inbox + chat thread Vue pages with Echo, a `MessagesBadge`, and a profile "Message" button.

**Tech Stack:** Laravel 13, PHP 8.4, Reverb + Echo, Inertia v3 + Vue 3, Pest 4, MariaDB (`lms_v2_testing`) + DatabaseTruncation, spatie/laravel-permission, lorisleiva/laravel-actions.

## Global Constraints

- Variables `snake_case`; methods/functions `camelCase`; classes `TitleCase`.
- PHP: explicit return types + param type hints; curly braces on all control structures; PHPDoc over inline comments; array-shape PHPDoc.
- Thin controllers: `$this->authorize(...)` then delegate to an Action (`AsAction`, `::run()`); validation in FormRequests (`authorize(): bool { return true; }`).
- Policies auto-discovered; admins bypass via the existing `Gate::before`.
- User display shape is `UserSummaryResource` (`->resolve($request)` when embedding in an array). `role` on it is the first Spatie role name (e.g. `Student`/`Instructor`).
- Roles via `App\Enums\UserRole` (`Student`, `Instructor`, `Admin`); check with `$user->hasRole(UserRole::Student->value)`.
- New routes inside the existing `auth`+`verified` group in `routes/web.php`.
- Notifications carry `conversation_id` (frontend builds links); they do NOT call `route()`.
- Tests: `seed(RolePermissionSeeder::class)` in `beforeEach`; MariaDB + DatabaseTruncation; `QUEUE_CONNECTION=sync`. Fake `Event`/`Notification` in any test where a real broadcast would hit the (non-running) Reverb server. Browser tests are render-only (never send a message in-browser). Run tests by file PATH.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes.

---

### Task 1: Data model — conversations + messages

**Files:**
- Create: `database/migrations/2026_07_04_010000_create_conversations_table.php`, `database/migrations/2026_07_04_010100_create_messages_table.php`, `app/Models/Conversation.php`, `app/Models/Message.php`, `database/factories/ConversationFactory.php`, `database/factories/MessageFactory.php`
- Test: `tests/Feature/Messages/ConversationModelTest.php`

**Interfaces:**
- Produces: `Conversation` (`student()`, `instructor()`, `messages()`, `latestMessage()`, `hasParticipant(User): bool`, `otherParticipant(User): User`); `Message` (`conversation()`, `sender()`). Factories `Conversation::factory()`, `Message::factory()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Messages/ConversationModelTest.php`:

```php
<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

it('resolves the other participant relative to a given user', function () {
    $student = User::factory()->create();
    $instructor = User::factory()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);

    expect($conversation->otherParticipant($student)->id)->toBe($instructor->id)
        ->and($conversation->otherParticipant($instructor)->id)->toBe($student->id)
        ->and($conversation->hasParticipant($student))->toBeTrue()
        ->and($conversation->hasParticipant(User::factory()->create()))->toBeFalse();
});

it('exposes its latest message', function () {
    $conversation = Conversation::factory()->create();
    Message::factory()->for($conversation)->create(['body' => 'first']);
    $last = Message::factory()->for($conversation)->create(['body' => 'second']);

    expect($conversation->refresh()->latestMessage->id)->toBe($last->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Messages/ConversationModelTest.php`
Expected: FAIL — `Class "App\Models\Conversation" not found`.

- [ ] **Step 3: Create the migrations**

`database/migrations/2026_07_04_010000_create_conversations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'instructor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
```

`database/migrations/2026_07_04_010100_create_messages_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
```

- [ ] **Step 4: Create the models**

`app/Models/Conversation.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'instructor_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function hasParticipant(User $user): bool
    {
        return $this->student_id === $user->id || $this->instructor_id === $user->id;
    }

    public function otherParticipant(User $user): User
    {
        return $this->student_id === $user->id ? $this->instructor : $this->student;
    }
}
```

`app/Models/Message.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
```

- [ ] **Step 5: Create the factories**

`database/factories/ConversationFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => User::factory()->student(),
            'instructor_id' => User::factory()->instructor(),
            'last_message_at' => now(),
        ];
    }
}
```

`database/factories/MessageFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'body' => fake()->sentence(),
            'read_at' => null,
        ];
    }
}
```

- [ ] **Step 6: Run tests + lint + commit**

Run: `php artisan test --compact tests/Feature/Messages/ConversationModelTest.php`
Expected: PASS (2 passing).

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Conversation.php app/Models/Message.php database/migrations database/factories/ConversationFactory.php database/factories/MessageFactory.php tests/Feature/Messages/ConversationModelTest.php
git commit -m "Add conversations + messages models, migrations, factories"
```

---

### Task 2: ConversationPolicy

**Files:**
- Create: `app/Policies/ConversationPolicy.php`
- Test: `tests/Feature/Messages/ConversationPolicyTest.php`

**Interfaces:**
- Produces: `ConversationPolicy::view(User, Conversation): bool`, `send(User, Conversation): bool` — both true iff the user is a participant.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Messages/ConversationPolicyTest.php`:

```php
<?php

use App\Models\Conversation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('allows only participants to view and send', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);
    $outsider = User::factory()->student()->create();

    expect($student->can('view', $conversation))->toBeTrue()
        ->and($instructor->can('send', $conversation))->toBeTrue()
        ->and($outsider->can('view', $conversation))->toBeFalse()
        ->and($outsider->can('send', $conversation))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Messages/ConversationPolicyTest.php`
Expected: FAIL.

- [ ] **Step 3: Create the policy**

`app/Policies/ConversationPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }
}
```

- [ ] **Step 4: Run tests + lint + commit**

Run: `php artisan test --compact tests/Feature/Messages/ConversationPolicyTest.php`
Expected: PASS.

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/ConversationPolicy.php tests/Feature/Messages/ConversationPolicyTest.php
git commit -m "Add ConversationPolicy (participant-only view/send)"
```

---

### Task 3: MessageResource + MessageSent event + channel

**Files:**
- Create: `app/Http/Resources/MessageResource.php`, `app/Events/MessageSent.php`
- Modify: `routes/channels.php`
- Test: `tests/Feature/Messages/MessageBroadcastTest.php`

**Interfaces:**
- Consumes: `UserSummaryResource`.
- Produces: `MessageResource` (shapes `id, conversation_id, body, sender, read_at, created_at`); `MessageSent` (`__construct(Message $message)`, broadcasts on `PrivateChannel('conversations.'.$message->conversation_id)`, `broadcastWith()` = shaped message).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Messages/MessageBroadcastTest.php`:

```php
<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('broadcasts a sent message on its conversation channel with a shaped payload', function () {
    $message = Message::factory()->create();

    $event = new MessageSent($message);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->broadcastOn()->name)->toBe('private-conversations.'.$message->conversation_id)
        ->and($event->broadcastWith())->toMatchArray([
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'body' => $message->body,
        ]);
    expect($event->broadcastWith()['sender'])->toHaveKey('id');
});

it('authorizes the conversation channel for a participant and denies an outsider', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);
    $outsider = User::factory()->student()->create();

    $this->actingAs($student)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-conversations.'.$conversation->id,
    ])->assertOk();

    $this->actingAs($outsider)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-conversations.'.$conversation->id,
    ])->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Messages/MessageBroadcastTest.php`
Expected: FAIL — `Class "App\Events\MessageSent" not found`.

- [ ] **Step 3: Create the resource**

`app/Http/Resources/MessageResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'body' => $this->body,
            'sender' => UserSummaryResource::make($this->sender)->resolve($request),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 4: Create the event**

`app/Events/MessageSent.php`:

```php
<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversations.'.$this->message->conversation_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender');

        return MessageResource::make($this->message)->resolve(request());
    }
}
```

- [ ] **Step 5: Add the channel authorization**

In `routes/channels.php`, add `use App\Models\Conversation;` to the top imports (beside `use App\Models\User;` and `use App\Models\Discussion;`), then append at the end of the file:

```php
Broadcast::channel('conversations.{conversation}', function (User $user, int $conversation): bool {
    $model = Conversation::find($conversation);

    return $model !== null && $user->can('view', $model);
});
```

- [ ] **Step 6: Run tests + lint + commit**

Run: `php artisan test --compact tests/Feature/Messages/MessageBroadcastTest.php`
Expected: PASS (2 passing).

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Resources/MessageResource.php app/Events/MessageSent.php routes/channels.php tests/Feature/Messages/MessageBroadcastTest.php
git commit -m "Add MessageResource + MessageSent broadcast + conversation channel auth"
```

---

### Task 4: NewMessage notification

**Files:**
- Create: `app/Notifications/NewMessage.php`
- Test: `tests/Feature/Messages/NewMessageNotificationTest.php`

**Interfaces:**
- Produces: `NewMessage(Message $message)`, `via()` = `['database','broadcast']`, `toArray()` returns `conversation_id, sender_name, excerpt, type` (`type` = `'new_message'`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Messages/NewMessageNotificationTest.php`:

```php
<?php

use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessage;

it('shapes the new-message notification for database + broadcast', function () {
    $message = Message::factory()->create(['body' => 'hello there friend']);
    $notification = new NewMessage($message);
    $data = $notification->toArray(new User);

    expect($notification->via(new User))->toBe(['database', 'broadcast'])
        ->and($data)->toHaveKeys(['conversation_id', 'sender_name', 'excerpt', 'type'])
        ->and($data['type'])->toBe('new_message')
        ->and($data['conversation_id'])->toBe($message->conversation_id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Messages/NewMessageNotificationTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the notification**

`app/Notifications/NewMessage.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Message $message) {}

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
            'conversation_id' => $this->message->conversation_id,
            'sender_name' => $this->message->sender->name,
            'excerpt' => Str::limit($this->message->body, 80),
            'type' => 'new_message',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
```

- [ ] **Step 4: Run tests + lint + commit**

Run: `php artisan test --compact tests/Feature/Messages/NewMessageNotificationTest.php`
Expected: PASS.

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/NewMessage.php tests/Feature/Messages/NewMessageNotificationTest.php
git commit -m "Add NewMessage notification (database + broadcast)"
```

---

### Task 5: StartConversation + MessageController (index/store/show) + routes

**Files:**
- Create: `app/Actions/StartConversation.php`, `app/Http/Requests/Message/StartConversationRequest.php`, `app/Http/Controllers/MessageController.php`, `resources/js/Pages/Messages/Index.vue` (placeholder), `resources/js/Pages/Messages/Show.vue` (placeholder)
- Modify: `routes/web.php`
- Test: `tests/Feature/Messages/ConversationManagementTest.php`

**Interfaces:**
- Consumes: `ConversationPolicy`, `MessageResource`, `UserSummaryResource`, `App\Enums\UserRole`.
- Produces: `StartConversation::run(User $initiator, User $target): Conversation` (opposite-role pairing, find-or-create; `abort(403)` on same-role/self); routes `conversations.index/store/show`.

> The Vue files are minimal valid placeholders so `Inertia::render` resolves; Task 8 builds the real UI.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Messages/ConversationManagementTest.php`:

```php
<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('find-or-creates a single conversation per student-instructor pair', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();

    $this->actingAs($student)->post(route('conversations.store'), ['user_id' => $instructor->id])->assertRedirect();
    $this->actingAs($instructor)->post(route('conversations.store'), ['user_id' => $student->id])->assertRedirect();

    expect(Conversation::where('student_id', $student->id)->where('instructor_id', $instructor->id)->count())->toBe(1);
});

it('rejects same-role or self conversations', function () {
    $studentA = User::factory()->student()->create();
    $studentB = User::factory()->student()->create();

    $this->actingAs($studentA)->post(route('conversations.store'), ['user_id' => $studentB->id])->assertForbidden();
    $this->actingAs($studentA)->post(route('conversations.store'), ['user_id' => $studentA->id])->assertForbidden();
});

it('shows a conversation to a participant and marks their unread messages read', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);
    Message::factory()->for($conversation)->create(['sender_id' => $instructor->id, 'read_at' => null]);

    $this->actingAs($student)
        ->get(route('conversations.show', $conversation))
        ->assertInertia(fn (Assert $page) => $page->component('Messages/Show')->where('conversation.id', $conversation->id));

    expect($conversation->messages()->whereNull('read_at')->count())->toBe(0);
});

it('forbids a non-participant from viewing a conversation', function () {
    $conversation = Conversation::factory()->create();
    $outsider = User::factory()->student()->create();

    $this->actingAs($outsider)->get(route('conversations.show', $conversation))->assertForbidden();
});

it('lists the user conversations ordered by last_message_at desc', function () {
    $student = User::factory()->student()->create();
    $older = Conversation::factory()->create(['student_id' => $student->id, 'last_message_at' => now()->subDay()]);
    $newer = Conversation::factory()->create(['student_id' => $student->id, 'last_message_at' => now()]);

    $this->actingAs($student)
        ->get(route('conversations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Messages/Index')
            ->has('conversations', 2)
            ->where('conversations.0.id', $newer->id));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Messages/ConversationManagementTest.php`
Expected: FAIL — route `conversations.store` not defined.

- [ ] **Step 3: Create the FormRequest**

`app/Http/Requests/Message/StartConversationRequest.php`:

```php
<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
```

- [ ] **Step 4: Create the action**

`app/Actions/StartConversation.php`:

```php
<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class StartConversation
{
    use AsAction;

    public function handle(User $initiator, User $target): Conversation
    {
        if ($initiator->is($target)) {
            abort(403);
        }

        [$student, $instructor] = $this->resolvePair($initiator, $target);

        return Conversation::firstOrCreate([
            'student_id' => $student->id,
            'instructor_id' => $instructor->id,
        ]);
    }

    /**
     * @return array{0: User, 1: User} [student, instructor]
     */
    private function resolvePair(User $a, User $b): array
    {
        $student = UserRole::Student->value;
        $instructor = UserRole::Instructor->value;

        if ($a->hasRole($student) && $b->hasRole($instructor)) {
            return [$a, $b];
        }

        if ($a->hasRole($instructor) && $b->hasRole($student)) {
            return [$b, $a];
        }

        abort(403);
    }
}
```

- [ ] **Step 5: Create the controller**

`app/Http/Controllers/MessageController.php`:

```php
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
```

- [ ] **Step 6: Register routes**

In `routes/web.php` add `use App\Http\Controllers\MessageController;` at the top with the other controller imports, and inside the `Route::middleware('verified')->group(...)` block:

```php
        Route::get('conversations', [MessageController::class, 'index'])->name('conversations.index');
        Route::post('conversations', [MessageController::class, 'store'])->name('conversations.store');
        Route::get('conversations/{conversation}', [MessageController::class, 'show'])->name('conversations.show');
```

- [ ] **Step 7: Create placeholder Vue pages**

`resources/js/Pages/Messages/Index.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    conversations: { type: Array, required: true },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Messages" />
        <h1>Messages</h1>
    </AuthenticatedLayout>
</template>
```

`resources/js/Pages/Messages/Show.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    conversation: { type: Object, required: true },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Conversation" />
        <h1>Conversation {{ conversation.id }}</h1>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 8: Run tests + lint + build + commit**

Run: `php artisan test --compact tests/Feature/Messages/ConversationManagementTest.php`
Expected: PASS (5 passing).

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add app/Actions/StartConversation.php app/Http/Requests/Message/StartConversationRequest.php app/Http/Controllers/MessageController.php routes/web.php resources/js/Pages/Messages tests/Feature/Messages/ConversationManagementTest.php
git commit -m "Add conversation index/store/show + StartConversation action"
```

---

### Task 6: SendMessage action + message posting + rate limit

**Files:**
- Create: `app/Actions/SendMessage.php`, `app/Http/Requests/Message/StoreMessageRequest.php`
- Modify: `app/Http/Controllers/MessageController.php`, `routes/web.php`
- Test: `tests/Feature/Messages/SendMessageTest.php`

**Interfaces:**
- Consumes: `ConversationPolicy@send`, `MessageSent` (Task 3), `NewMessage` (Task 4).
- Produces: `SendMessage::run(Conversation, User $sender, array{body:string}): Message` (creates message, bumps `last_message_at`, broadcasts `MessageSent`, notifies the other participant); route `messages.store` (throttle:30,1).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Messages/SendMessageTest.php`:

```php
<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\NewMessage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets a participant send a message, broadcasts it, and notifies the recipient not the sender', function () {
    Event::fake([MessageSent::class]);
    Notification::fake();
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id, 'last_message_at' => null]);

    $this->actingAs($student)
        ->post(route('messages.store', $conversation), ['body' => 'Hi professor'])
        ->assertRedirect();

    expect($conversation->fresh()->last_message_at)->not->toBeNull()
        ->and($conversation->messages()->where('body', 'Hi professor')->exists())->toBeTrue();
    Event::assertDispatched(MessageSent::class);
    Notification::assertSentTo($instructor, NewMessage::class);
    Notification::assertNotSentTo($student, NewMessage::class);
});

it('forbids a non-participant from sending', function () {
    $conversation = Conversation::factory()->create();
    $outsider = User::factory()->student()->create();

    $this->actingAs($outsider)
        ->post(route('messages.store', $conversation), ['body' => 'nope'])
        ->assertForbidden();
});

it('rate-limits message sending to 30 per minute', function () {
    Event::fake([MessageSent::class]);
    Notification::fake();
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);

    foreach (range(1, 30) as $i) {
        $this->actingAs($student)->post(route('messages.store', $conversation), ['body' => "m$i"])->assertRedirect();
    }

    $this->actingAs($student)->post(route('messages.store', $conversation), ['body' => 'over'])->assertStatus(429);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Messages/SendMessageTest.php`
Expected: FAIL — route `messages.store` not defined.

- [ ] **Step 3: Create the FormRequest**

`app/Http/Requests/Message/StoreMessageRequest.php`:

```php
<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Create the action**

`app/Actions/SendMessage.php`:

```php
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
```

- [ ] **Step 5: Add the controller method**

Add to `app/Http/Controllers/MessageController.php` (and import `App\Actions\SendMessage` + `App\Http\Requests\Message\StoreMessageRequest`):

```php
    public function sendMessage(StoreMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('send', $conversation);

        SendMessage::run($conversation, $request->user(), $request->validated());

        return back();
    }
```

- [ ] **Step 6: Register the route (rate-limited)**

In `routes/web.php`, inside the `verified` group:

```php
        Route::post('conversations/{conversation}/messages', [MessageController::class, 'sendMessage'])
            ->middleware('throttle:30,1')
            ->name('messages.store');
```

- [ ] **Step 7: Run tests + lint + commit**

Run: `php artisan test --compact tests/Feature/Messages/SendMessageTest.php`
Expected: PASS (3 passing).

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/SendMessage.php app/Http/Requests/Message/StoreMessageRequest.php app/Http/Controllers/MessageController.php routes/web.php tests/Feature/Messages/SendMessageTest.php
git commit -m "Add message sending with broadcast, notification, and rate limit"
```

---

### Task 7: Unread-messages badge + notification integration

**Files:**
- Create: `resources/js/Components/MessagesBadge.vue`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/Layouts/AuthenticatedLayout.vue`, `resources/js/Pages/Notifications/Index.vue`, `app/Http/Controllers/MessageController.php` (mark related notifications read in `show`)
- Test: `tests/Feature/Messages/UnreadMessagesTest.php`

**Interfaces:**
- Consumes: `auth.user.unread_messages_count`; the per-user `App.Models.User.{id}` channel; `conversations.index`/`conversations.show` routes.
- Produces: shared prop `auth.user.unread_messages_count`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Messages/UnreadMessagesTest.php`:

```php
<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('shares the unread messages count for the current user', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);
    Message::factory()->for($conversation)->count(2)->create(['sender_id' => $instructor->id, 'read_at' => null]);
    Message::factory()->for($conversation)->create(['sender_id' => $student->id, 'read_at' => null]); // own message: not unread for student

    $this->actingAs($student)
        ->get(route('conversations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.unread_messages_count', 2));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Messages/UnreadMessagesTest.php`
Expected: FAIL — prop missing / null.

- [ ] **Step 3: Add the shared prop**

In `app/Http/Middleware/HandleInertiaRequests.php`, add `use App\Models\Message;` to the imports, and inside the `auth.user` array (after `unread_notifications_count`) add:

```php
                        'unread_messages_count' => Message::query()
                            ->whereHas('conversation', fn ($query) => $query
                                ->where('student_id', $request->user()->id)
                                ->orWhere('instructor_id', $request->user()->id))
                            ->where('sender_id', '!=', $request->user()->id)
                            ->whereNull('read_at')
                            ->count(),
```

- [ ] **Step 4: Mark related notifications read in `show`**

In `app/Http/Controllers/MessageController.php@show`, after marking messages read (before `$conversation->load(...)`), add:

```php
        $user->unreadNotifications()
            ->where('data->type', 'new_message')
            ->where('data->conversation_id', $conversation->id)
            ->get()
            ->each->markAsRead();
```

- [ ] **Step 5: Create the MessagesBadge component**

`resources/js/Components/MessagesBadge.vue` (shares the per-user channel with `NotificationBell`; does NOT call `Echo.leave` — the bell owns the channel lifecycle, so leaving here would tear down the bell's subscription too):

```vue
<script setup>
import { onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';

const page = usePage();
const count = ref(page.props.auth.user?.unread_messages_count ?? 0);
const userId = page.props.auth.user?.id;

onMounted(() => {
    if (!userId || !window.Echo) {
        return;
    }
    window.Echo.private(`App.Models.User.${userId}`)
        .notification((notification) => {
            if (notification.type === 'new_message') {
                count.value += 1;
            }
        });
});
</script>

<template>
    <Link :href="route('conversations.index')" class="relative inline-flex items-center" aria-label="Messages">
        <Mail class="size-5" />
        <span v-if="count > 0" class="absolute -right-2 -top-2 rounded-full bg-red-500 px-1.5 text-xs text-white">{{ count }}</span>
    </Link>
</template>
```

- [ ] **Step 6: Mount the badge in the layout**

In `resources/js/Layouts/AuthenticatedLayout.vue`, import `MessagesBadge` and place `<MessagesBadge />` next to the existing `<NotificationBell />`:

```js
import MessagesBadge from '@/Components/MessagesBadge.vue';
```

- [ ] **Step 7: Route notifications by type in the notification center**

In `resources/js/Pages/Notifications/Index.vue`, update `openNotification` to route by type, and add a `new_message` label. Replace the `openNotification` function and the label expression:

```js
const openNotification = (notification) => {
    router.post(route('notifications.read', notification.id), {}, {
        preserveScroll: true,
        onSuccess: () => router.visit(
            notification.type === 'new_message'
                ? route('conversations.show', notification.conversation_id)
                : route('discussions.show', notification.discussion_id),
        ),
    });
};

const notificationLabel = (type) => {
    if (type === 'new_question') {
        return 'asked a question';
    }
    if (type === 'new_message') {
        return 'sent a message';
    }
    return 'replied';
};
```

And in the template, change the label line to use it:

```vue
                    <p class="text-sm">{{ n.actor_name ?? n.sender_name }} · {{ notificationLabel(n.type) }}</p>
```

(Message notifications carry `sender_name`, discussion notifications carry `actor_name`.)

- [ ] **Step 8: Run tests + lint + build + commit**

Run: `php artisan test --compact tests/Feature/Messages/UnreadMessagesTest.php`
Expected: PASS.

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add app/Http/Middleware/HandleInertiaRequests.php app/Http/Controllers/MessageController.php resources/js/Components/MessagesBadge.vue resources/js/Layouts/AuthenticatedLayout.vue resources/js/Pages/Notifications/Index.vue tests/Feature/Messages/UnreadMessagesTest.php
git commit -m "Add unread messages badge + wire new_message into notification center"
```

---

### Task 8: Inbox + chat thread UI with live messages

**Files:**
- Modify: `resources/js/Pages/Messages/Index.vue`, `resources/js/Pages/Messages/Show.vue`
- Test: `tests/Browser/MessagingTest.php`

**Interfaces:**
- Consumes: `conversations.index`/`conversations.show`/`messages.store` routes; `conversations.{id}` Echo channel; `window.Echo`.

- [ ] **Step 1: Build the inbox**

Replace `resources/js/Pages/Messages/Index.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    conversations: { type: Array, required: true },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Messages" />
        <div class="mx-auto max-w-2xl p-4">
            <h1 class="mb-4 text-xl font-semibold">Messages</h1>
            <ul class="divide-y">
                <li v-for="c in conversations" :key="c.id">
                    <Link :href="route('conversations.show', c.id)" class="flex items-center gap-3 py-3">
                        <UserAvatar :user="c.other" class="size-10" />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ c.other.name }}</p>
                            <p class="truncate text-sm text-gray-500">{{ c.last_message ?? 'No messages yet' }}</p>
                        </div>
                        <span v-if="c.unread_count > 0" class="rounded-full bg-red-500 px-2 text-xs text-white">{{ c.unread_count }}</span>
                    </Link>
                </li>
                <li v-if="conversations.length === 0" class="py-6 text-center text-gray-500">No conversations yet.</li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 2: Build the chat thread**

Replace `resources/js/Pages/Messages/Show.vue`:

```vue
<script setup>
import { onMounted, onUnmounted, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    conversation: { type: Object, required: true },
});

const currentUserId = usePage().props.auth.user?.id;
const state = reactive({ messages: props.conversation.messages ?? [] });

const form = useForm({ body: '' });
const submit = () => form.post(route('messages.store', props.conversation.id), {
    preserveScroll: true,
    onSuccess: () => form.reset('body'),
});

const appendMessage = (message) => {
    if (!state.messages.some((m) => m.id === message.id)) {
        state.messages.push(message);
    }
};

onMounted(() => {
    if (window.Echo) {
        window.Echo.private(`conversations.${props.conversation.id}`)
            .listen('MessageSent', (message) => appendMessage(message));
    }
});
onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave(`conversations.${props.conversation.id}`);
    }
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Chat with ${conversation.other.name}`" />
        <div class="mx-auto flex max-w-2xl flex-col p-4">
            <div class="mb-4 flex items-center gap-2">
                <UserAvatar :user="conversation.other" class="size-9" />
                <h1 class="font-semibold">{{ conversation.other.name }}</h1>
            </div>

            <div class="space-y-2">
                <div
                    v-for="m in state.messages"
                    :key="m.id"
                    class="flex"
                    :class="m.sender.id === currentUserId ? 'justify-end' : 'justify-start'"
                >
                    <p
                        class="max-w-[75%] rounded-lg px-3 py-2 text-sm"
                        :class="m.sender.id === currentUserId ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-900'"
                    >
                        {{ m.body }}
                    </p>
                </div>
            </div>

            <form class="mt-4 flex gap-2" @submit.prevent="submit">
                <input v-model="form.body" type="text" placeholder="Type a message…" class="flex-1 rounded border p-2" />
                <button type="submit" class="rounded bg-amber-500 px-4 py-2 text-white hover:bg-amber-600" :disabled="form.processing">Send</button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 3: Build the frontend**

Run: `npm run build`
Expected: success.

- [ ] **Step 4: Write render-only browser tests**

Create `tests/Browser/MessagingTest.php` (mirror `tests/Browser/LoginTest.php`: `actingAs` before `visit`, `assertNoJavaScriptErrors()`. RENDER-ONLY — do NOT send a message, which would broadcast to the down Reverb server and 500):

```php
<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('renders the inbox and a conversation thread without JS errors', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);
    Message::factory()->for($conversation)->create(['sender_id' => $instructor->id, 'body' => 'Welcome to the course']);
    $this->actingAs($student);

    visit(route('conversations.index'))->assertNoJavaScriptErrors();

    visit(route('conversations.show', $conversation))
        ->assertSee('Welcome to the course')
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 5: Run tests + lint + commit**

Run: `php artisan test --compact tests/Browser/MessagingTest.php`
Expected: PASS.

```bash
vendor/bin/pint --dirty --format agent
git add resources/js/Pages/Messages tests/Browser/MessagingTest.php
git commit -m "Build messaging inbox + chat thread UI with live messages"
```

---

### Task 9: Profile "Message" button

**Files:**
- Modify: `app/Http/Controllers/UserProfileController.php`, `resources/js/Pages/Profile/Show.vue`
- Test: `tests/Feature/Messages/ProfileMessageButtonTest.php`

**Interfaces:**
- Consumes: `conversations.store` route; `App\Enums\UserRole`.
- Produces: `Profile/Show` gains a `can_message` boolean prop.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Messages/ProfileMessageButtonTest.php`:

```php
<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('allows messaging between opposite roles but not same role or self', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $otherStudent = User::factory()->student()->create();

    $this->actingAs($student)->get(route('users.show', $instructor))
        ->assertInertia(fn (Assert $page) => $page->where('can_message', true));

    $this->actingAs($student)->get(route('users.show', $otherStudent))
        ->assertInertia(fn (Assert $page) => $page->where('can_message', false));

    $this->actingAs($student)->get(route('users.show', $student))
        ->assertInertia(fn (Assert $page) => $page->where('can_message', false));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Messages/ProfileMessageButtonTest.php`
Expected: FAIL — `can_message` prop missing.

- [ ] **Step 3: Add `can_message` in the controller**

In `app/Http/Controllers/UserProfileController.php`, add `use App\Enums\UserRole;`, and in `show()` compute and pass the prop. Add before the `return`:

```php
        $viewer = $request->user();
        $can_message = ! $is_own_profile && (
            ($viewer->hasRole(UserRole::Student->value) && $user->hasRole(UserRole::Instructor->value))
            || ($viewer->hasRole(UserRole::Instructor->value) && $user->hasRole(UserRole::Student->value))
        );
```

Add `'can_message' => $can_message,` to the `Inertia::render('Profile/Show', [...])` props array (keep all existing props).

- [ ] **Step 4: Add the Message button to the profile page**

Read `resources/js/Pages/Profile/Show.vue` first. Add `can_message: { type: Boolean, default: false }` to its `defineProps`, import `useForm` from `@inertiajs/vue3` if not already imported, and add a Message button in the profile header (near the name / avatar) that posts to `conversations.store`:

```vue
<script setup>
// ...existing imports; ensure useForm is imported from '@inertiajs/vue3'
const messageForm = useForm({ user_id: props.profile.id });
const startConversation = () => messageForm.post(route('conversations.store'));
</script>
```

In the template, where the profile header actions are:

```vue
        <button
            v-if="can_message"
            type="button"
            class="rounded bg-amber-500 px-3 py-1.5 text-sm text-white hover:bg-amber-600"
            @click="startConversation"
        >
            Message
        </button>
```

Match the page's existing prop name for the profile object (the controller passes `profile` = `UserSummaryResource`, which has `id`). Adjust `props.profile.id` if the page names it differently. Keep the existing page markup/logic intact.

- [ ] **Step 5: Run tests + lint + build + commit**

Run: `php artisan test --compact tests/Feature/Messages/ProfileMessageButtonTest.php`
Expected: PASS.

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add app/Http/Controllers/UserProfileController.php resources/js/Pages/Profile/Show.vue tests/Feature/Messages/ProfileMessageButtonTest.php
git commit -m "Add profile Message button gated on opposite roles"
```

---

### Task 10: Full regression + lint sweep

**Files:** none (verification only)

- [ ] **Step 1: Full suite**

Run: `php artisan test --compact`
Expected: all tests pass (prior 202 + the new messaging tests), no regressions.

- [ ] **Step 2: Lint sweep**

Run: `vendor/bin/pint --dirty --format agent`
Expected: clean.

- [ ] **Step 3: Final commit (only if lint changed anything)**

```bash
git add -A
git commit -m "Lint sweep for private messaging feature"
```

---

## Notes

- Live delivery (Reverb) is proven by the `MessageSent` broadcast test + channel-auth test; browser tests are render-only (no Reverb server in CI). Manual live check: open a conversation in two browsers, send from one, watch it appear in the other and the recipient's Messages badge + bell increment — same procedure as `docs/superpowers/reverb-manual-verification.md`.
- `MessageSent` broadcast and `NewMessage` are `ShouldBroadcast`/`ShouldQueue`; the `composer dev` queue worker delivers them. `QUEUE_CONNECTION=sync` runs them inline in tests (hence the `Event::fake`/`Notification::fake` in send tests).
- `MessagesBadge` intentionally shares the per-user `App.Models.User.{id}` channel with `NotificationBell` and does not tear it down; both components live in the persistent authenticated layout.
- No `ConversationResource` is used — the inbox and thread shape conversations inline in the controller because the "other participant" is viewer-relative. `MessageResource` handles individual messages.
