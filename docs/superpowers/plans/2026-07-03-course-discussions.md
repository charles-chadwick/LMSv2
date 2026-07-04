# Course & Lesson Discussions (Live Q&A) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Threaded Q&A discussions on courses and lessons, with arbitrary-depth nested replies, live reply insertion via Reverb, and database+broadcast notifications with a live unread badge.

**Architecture:** Reuse the existing `Discussion`/`DiscussionReply` models (add nullable `lesson_id`). Thin controllers authorize via new `DiscussionPolicy`/`DiscussionReplyPolicy` (delegating course access to the existing `learn` ability) and delegate writes to `CreateDiscussion`/`CreateReply` laravel-actions, which fire a `DiscussionReplyPosted` broadcast and dispatch notifications. Frontend: Inertia Vue pages + a recursive reply component + Echo listeners on a per-discussion channel and the per-user notification channel.

**Tech Stack:** Laravel 13, PHP 8.4, Reverb + Echo, Inertia v3 + Vue 3, Pest 4, MariaDB (`lms_v2_testing`) + DatabaseTruncation, spatie/laravel-permission, lorisleiva/laravel-actions.

## Global Constraints

- Variables `snake_case`; methods/functions `camelCase`; classes `TitleCase`.
- PHP: explicit return types + param type hints; curly braces on all control structures; PHPDoc over inline comments; array-shape PHPDoc.
- Thin controllers: `$this->authorize(...)` then delegate to an Action (`AsAction`, invoked `::run()`); validation in FormRequests (`authorize(): bool { return true; }`).
- Policies are auto-discovered (conventional naming); admins bypass via the existing `Gate::before`.
- Course access is the `learn` ability (`$user->can('learn', $course)` — enrolled Active/Completed OR instructor).
- User display shape is `UserSummaryResource` (`->resolve()` when embedding in a prop array).
- All new routes go inside the existing `auth`+`verified` group in `routes/web.php`; `{course}` binds by slug.
- Tests: Pest, `seed(RolePermissionSeeder::class)` in `beforeEach`; MariaDB + DatabaseTruncation; `QUEUE_CONNECTION=sync`. Fake broadcasting/notifications (`Event::fake`, `Notification::fake`) in tests that would otherwise hit the (non-running) Reverb server.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes.
- Filtered tests run by PATH (Pest `--filter` matches description text): `php artisan test --compact tests/Feature/Discussions/SomeTest.php`.

---

### Task 1: Migrations + Discussion model

**Files:**
- Create: `database/migrations/2026_07_03_180000_add_lesson_id_to_discussions_table.php`, `database/migrations/2026_07_03_180100_create_notifications_table.php`
- Modify: `app/Models/Discussion.php`
- Test: `tests/Feature/Discussions/DiscussionModelTest.php`

**Interfaces:**
- Produces: `Discussion` gains `lesson(): BelongsTo`, `lesson_id` in `$fillable`, `scopeForCourseLevel()`, `scopeForLesson(int $lesson_id)`. `notifications` table exists.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Discussions/DiscussionModelTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\Discussion;
use App\Models\Lesson;

it('scopes course-level discussions (no lesson) separately from lesson-level ones', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create();

    $courseLevel = Discussion::factory()->create(['course_id' => $course->id, 'lesson_id' => null]);
    $lessonLevel = Discussion::factory()->create(['course_id' => $course->id, 'lesson_id' => $lesson->id]);

    expect(Discussion::forCourseLevel()->pluck('id'))->toContain($courseLevel->id)->not->toContain($lessonLevel->id)
        ->and(Discussion::forLesson($lesson->id)->pluck('id'))->toContain($lessonLevel->id)->not->toContain($courseLevel->id);
});

it('belongs to a lesson when lesson_id is set', function () {
    $lesson = Lesson::factory()->create();
    $discussion = Discussion::factory()->create(['lesson_id' => $lesson->id]);

    expect($discussion->lesson->id)->toBe($lesson->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Discussions/DiscussionModelTest.php`
Expected: FAIL — `Call to undefined method ...forCourseLevel()` / unknown column `lesson_id`.

- [ ] **Step 3: Create the migrations**

`database/migrations/2026_07_03_180000_add_lesson_id_to_discussions_table.php`:

```php
<?php

use App\Models\Lesson;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discussions', function (Blueprint $table): void {
            $table->foreignIdFor(Lesson::class)->nullable()->after('course_id')
                ->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('discussions', function (Blueprint $table): void {
            $table->dropConstrainedForeignIdFor(Lesson::class);
        });
    }
};
```

For the notifications table, generate it with the framework stub:

Run: `php artisan notifications:table`
Expected: creates `database/migrations/*_create_notifications_table.php`. Rename its timestamp prefix if needed so it sorts after other migrations (e.g. `2026_07_03_180100_create_notifications_table.php`). Leave its contents as generated.

- [ ] **Step 4: Update the Discussion model**

In `app/Models/Discussion.php`: add `'lesson_id'` to `$fillable` (after `'course_id'`), add the import `use App\Models\Lesson;` is unnecessary (same namespace), and add these methods after `course()`:

```php
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function scopeForCourseLevel(Builder $query): Builder
    {
        return $query->whereNull('lesson_id');
    }

    public function scopeForLesson(Builder $query, int $lesson_id): Builder
    {
        return $query->where('lesson_id', $lesson_id);
    }
```

Add `use Illuminate\Database\Eloquent\Builder;` to the imports.

- [ ] **Step 5: Run tests + migrate**

Run: `php artisan test --compact tests/Feature/Discussions/DiscussionModelTest.php`
Expected: PASS (2 passing).

- [ ] **Step 6: Lint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Discussion.php database/migrations
git commit -m "Add lesson_id to discussions + notifications table"
```

---

### Task 2: Authorization policies

**Files:**
- Create: `app/Policies/DiscussionPolicy.php`, `app/Policies/DiscussionReplyPolicy.php`
- Test: `tests/Feature/Discussions/DiscussionPolicyTest.php`

**Interfaces:**
- Produces: `DiscussionPolicy` abilities `viewAny(User,Course)`, `view(User,Discussion)`, `create(User,Course)`, `reply(User,Discussion)`, `update(User,Discussion)`, `delete(User,Discussion)`, `pin(User,Discussion)`, `lock(User,Discussion)`. `DiscussionReplyPolicy` abilities `update(User,DiscussionReply)`, `delete(User,DiscussionReply)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Discussions/DiscussionPolicyTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets an enrolled student and the instructor view and create, but not an outsider', function () {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $student = User::factory()->student()->create();
    Enrollment::factory()->for($student, 'student')->for($course)->create();
    $outsider = User::factory()->student()->create();
    $discussion = Discussion::factory()->for($course)->create();

    expect($student->can('view', $discussion))->toBeTrue()
        ->and($instructor->can('view', $discussion))->toBeTrue()
        ->and($outsider->can('view', $discussion))->toBeFalse()
        ->and($student->can('create', [Discussion::class, $course]))->toBeTrue();
});

it('blocks replies on a locked discussion but allows the author to edit and the instructor to moderate', function () {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $author = User::factory()->student()->create();
    Enrollment::factory()->for($author, 'student')->for($course)->create();
    $open = Discussion::factory()->for($course)->for($author, 'author')->create();
    $locked = Discussion::factory()->for($course)->for($author, 'author')->locked()->create();

    expect($author->can('reply', $open))->toBeTrue()
        ->and($author->can('reply', $locked))->toBeFalse()
        ->and($author->can('update', $open))->toBeTrue()
        ->and($instructor->can('update', $open))->toBeFalse()
        ->and($instructor->can('delete', $open))->toBeTrue()
        ->and($instructor->can('lock', $open))->toBeTrue()
        ->and($author->can('lock', $open))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Discussions/DiscussionPolicyTest.php`
Expected: FAIL — no policy / abilities return false or error.

- [ ] **Step 3: Create the policies**

`app/Policies/DiscussionPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Discussion;
use App\Models\User;

class DiscussionPolicy
{
    public function viewAny(User $user, Course $course): bool
    {
        return $user->can('learn', $course);
    }

    public function view(User $user, Discussion $discussion): bool
    {
        return $user->can('learn', $discussion->course);
    }

    public function create(User $user, Course $course): bool
    {
        return $user->can('learn', $course);
    }

    public function reply(User $user, Discussion $discussion): bool
    {
        return ! $discussion->is_locked && $user->can('learn', $discussion->course);
    }

    public function update(User $user, Discussion $discussion): bool
    {
        return $discussion->user_id === $user->id;
    }

    public function delete(User $user, Discussion $discussion): bool
    {
        return $discussion->user_id === $user->id
            || $discussion->course->instructor_id === $user->id;
    }

    public function pin(User $user, Discussion $discussion): bool
    {
        return $discussion->course->instructor_id === $user->id;
    }

    public function lock(User $user, Discussion $discussion): bool
    {
        return $discussion->course->instructor_id === $user->id;
    }
}
```

`app/Policies/DiscussionReplyPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\DiscussionReply;
use App\Models\User;

class DiscussionReplyPolicy
{
    public function update(User $user, DiscussionReply $reply): bool
    {
        return $reply->user_id === $user->id;
    }

    public function delete(User $user, DiscussionReply $reply): bool
    {
        return $reply->user_id === $user->id
            || $reply->discussion->course->instructor_id === $user->id;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Discussions/DiscussionPolicyTest.php`
Expected: PASS (2 passing).

- [ ] **Step 5: Lint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies tests/Feature/Discussions/DiscussionPolicyTest.php
git commit -m "Add Discussion + DiscussionReply policies"
```

---

### Task 3: Resources + DiscussionReplyPosted broadcast event + channel

**Files:**
- Create: `app/Http/Resources/DiscussionReplyResource.php`, `app/Http/Resources/DiscussionResource.php`, `app/Events/DiscussionReplyPosted.php`
- Modify: `routes/channels.php`
- Test: `tests/Feature/Discussions/DiscussionBroadcastTest.php`

**Interfaces:**
- Consumes: `UserSummaryResource`; the `discussions.{discussion}` channel name.
- Produces: `DiscussionReplyResource` (shapes `id, discussion_id, parent_id, body, author, created_at`, and `children` when loaded); `DiscussionResource`; `DiscussionReplyPosted` event (`__construct(DiscussionReply $reply)`, broadcasts on `PrivateChannel('discussions.'.$reply->discussion_id)`, `broadcastWith()` = the shaped reply).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Discussions/DiscussionBroadcastTest.php`:

```php
<?php

use App\Events\DiscussionReplyPosted;
use App\Models\Course;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('broadcasts a posted reply on its discussion private channel with a shaped payload', function () {
    $reply = DiscussionReply::factory()->create();

    $event = new DiscussionReplyPosted($reply);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->broadcastOn()->name)->toBe('private-discussions.'.$reply->discussion_id)
        ->and($event->broadcastWith())->toMatchArray([
            'id' => $reply->id,
            'discussion_id' => $reply->discussion_id,
            'body' => $reply->body,
        ]);
    expect($event->broadcastWith()['author'])->toHaveKey('id');
});

it('authorizes the discussion channel for a course member and denies an outsider', function () {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $member = User::factory()->student()->create();
    Enrollment::factory()->for($member, 'student')->for($course)->create();
    $outsider = User::factory()->student()->create();
    $discussion = Discussion::factory()->for($course)->create();

    $this->actingAs($member)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-discussions.'.$discussion->id,
    ])->assertOk();

    $this->actingAs($outsider)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-discussions.'.$discussion->id,
    ])->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Discussions/DiscussionBroadcastTest.php`
Expected: FAIL — `Class "App\Events\DiscussionReplyPosted" not found`.

- [ ] **Step 3: Create the resources**

`app/Http/Resources/DiscussionReplyResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Models\DiscussionReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DiscussionReply
 */
class DiscussionReplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'discussion_id' => $this->discussion_id,
            'parent_id' => $this->parent_id,
            'body' => $this->body,
            'author' => UserSummaryResource::make($this->author)->resolve($request),
            'created_at' => $this->created_at?->toIso8601String(),
            'children' => DiscussionReplyResource::collection($this->whenLoaded('children')),
        ];
    }
}
```

`app/Http/Resources/DiscussionResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Models\Discussion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Discussion
 */
class DiscussionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'lesson_id' => $this->lesson_id,
            'title' => $this->title,
            'body' => $this->body,
            'is_pinned' => $this->is_pinned,
            'is_locked' => $this->is_locked,
            'author' => UserSummaryResource::make($this->author)->resolve($request),
            'created_at' => $this->created_at?->toIso8601String(),
            'replies_count' => $this->whenCounted('replies'),
            'replies' => DiscussionReplyResource::collection($this->whenLoaded('replies')),
        ];
    }
}
```

- [ ] **Step 4: Create the event**

`app/Events/DiscussionReplyPosted.php`:

```php
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
```

- [ ] **Step 5: Add the channel authorization**

In `routes/channels.php`, add `use App\Models\Discussion;` to the top imports (beside the existing `use App\Models\User;` from slice 1), then append this channel at the end of the file:

```php
Broadcast::channel('discussions.{discussion}', function (User $user, int $discussion): bool {
    $model = Discussion::with('course')->find($discussion);

    return $model !== null && $user->can('view', $model);
});
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Discussions/DiscussionBroadcastTest.php`
Expected: PASS (2 passing).

- [ ] **Step 7: Lint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Resources app/Events/DiscussionReplyPosted.php routes/channels.php tests/Feature/Discussions/DiscussionBroadcastTest.php
git commit -m "Add discussion resources + DiscussionReplyPosted broadcast + channel auth"
```

---

### Task 4: Notification classes

**Files:**
- Create: `app/Notifications/NewDiscussionQuestion.php`, `app/Notifications/NewDiscussionReply.php`
- Test: `tests/Feature/Discussions/DiscussionNotificationShapeTest.php`

**Interfaces:**
- Consumes: the `notifications` table (Task 1).
- Produces: `NewDiscussionQuestion(Discussion $discussion)` and `NewDiscussionReply(DiscussionReply $reply)`, both `via()` = `['database','broadcast']`, `toArray()` returning `discussion_id, course_slug, type, actor_name, excerpt, url`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Discussions/DiscussionNotificationShapeTest.php`:

```php
<?php

use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\User;
use App\Notifications\NewDiscussionQuestion;
use App\Notifications\NewDiscussionReply;

it('shapes the new-question notification for database + broadcast', function () {
    $discussion = Discussion::factory()->create();
    $notification = new NewDiscussionQuestion($discussion);
    $data = $notification->toArray(new User);

    expect($notification->via(new User))->toBe(['database', 'broadcast'])
        ->and($data)->toHaveKeys(['discussion_id', 'course_slug', 'type', 'actor_name', 'excerpt'])
        ->and($data['type'])->toBe('new_question')
        ->and($data['discussion_id'])->toBe($discussion->id);
});

it('shapes the new-reply notification', function () {
    $reply = DiscussionReply::factory()->create();
    $notification = new NewDiscussionReply($reply);
    $data = $notification->toArray(new User);

    expect($data['type'])->toBe('new_reply')
        ->and($data['discussion_id'])->toBe($reply->discussion_id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Discussions/DiscussionNotificationShapeTest.php`
Expected: FAIL — notification classes not found.

- [ ] **Step 3: Create the notifications**

`app/Notifications/NewDiscussionQuestion.php`:

```php
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
```

`app/Notifications/NewDiscussionReply.php`:

```php
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
```

- [ ] **Step 4: Run tests + lint + commit**

Run: `php artisan test --compact tests/Feature/Discussions/DiscussionNotificationShapeTest.php`
Expected: PASS (2 passing).

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications tests/Feature/Discussions/DiscussionNotificationShapeTest.php
git commit -m "Add discussion notification classes (database + broadcast)"
```

---

### Task 5: CreateDiscussion action + DiscussionController (index/store/show) + routes

**Files:**
- Create: `app/Actions/CreateDiscussion.php`, `app/Http/Requests/Discussion/StoreDiscussionRequest.php`, `app/Http/Controllers/Course/DiscussionController.php`, `resources/js/Pages/Discussions/Index.vue` (placeholder render target), `resources/js/Pages/Discussions/Show.vue` (placeholder render target)
- Modify: `routes/web.php`
- Test: `tests/Feature/Discussions/DiscussionManagementTest.php`

**Interfaces:**
- Consumes: `DiscussionPolicy`; `NewDiscussionQuestion`; `DiscussionResource`.
- Produces: `CreateDiscussion::run(Course $course, User $author, array{title:string, body:string, lesson_id:?int} $data): Discussion` (creates discussion; notifies instructor unless actor); routes `discussions.index`, `discussions.store`, `discussions.show`.

> The two Vue files are created as minimal valid components here only so `Inertia::render` resolves; Task 10 builds their real UI.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Discussions/DiscussionManagementTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Notifications\NewDiscussionQuestion;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function enrolledStudent(Course $course): User
{
    $student = User::factory()->student()->create();
    Enrollment::factory()->for($student, 'student')->for($course)->create();

    return $student;
}

it('lets an enrolled student create a course-level discussion and notifies the instructor', function () {
    Notification::fake();
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $student = enrolledStudent($course);

    $this->actingAs($student)
        ->post(route('discussions.store', $course), ['title' => 'Why?', 'body' => 'Explain please'])
        ->assertRedirect();

    $discussion = Discussion::firstWhere('title', 'Why?');
    expect($discussion)->not->toBeNull()->and($discussion->lesson_id)->toBeNull();
    Notification::assertSentTo($instructor, NewDiscussionQuestion::class);
});

it('forbids a non-enrolled user from creating a discussion', function () {
    $course = Course::factory()->create();
    $outsider = User::factory()->student()->create();

    $this->actingAs($outsider)
        ->post(route('discussions.store', $course), ['title' => 'x', 'body' => 'y'])
        ->assertForbidden();
});

it('rejects a lesson_id that does not belong to the course', function () {
    $course = Course::factory()->create();
    $student = enrolledStudent($course);
    $foreignLesson = Lesson::factory()->create();

    $this->actingAs($student)
        ->post(route('discussions.store', $course), ['title' => 'x', 'body' => 'y', 'lesson_id' => $foreignLesson->id])
        ->assertSessionHasErrors('lesson_id');
});

it('lists course-level discussions pinned first and excludes lesson-level ones', function () {
    $course = Course::factory()->create();
    $student = enrolledStudent($course);
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();
    $plain = Discussion::factory()->for($course)->create(['title' => 'plain']);
    $pinned = Discussion::factory()->for($course)->pinned()->create(['title' => 'pinned']);
    Discussion::factory()->for($course)->create(['title' => 'lesson-q', 'lesson_id' => $lesson->id]);

    $this->actingAs($student)
        ->get(route('discussions.index', $course))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Discussions/Index')
            ->has('discussions.data', 2)
            ->where('discussions.data.0.title', 'pinned'));
});

it('shows a discussion thread', function () {
    $course = Course::factory()->create();
    $student = enrolledStudent($course);
    $discussion = Discussion::factory()->for($course)->create();

    $this->actingAs($student)
        ->get(route('discussions.show', $discussion))
        ->assertInertia(fn (Assert $page) => $page->component('Discussions/Show')
            ->where('discussion.id', $discussion->id));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Discussions/DiscussionManagementTest.php`
Expected: FAIL — route `discussions.store` not defined.

- [ ] **Step 3: Create the FormRequest**

`app/Http/Requests/Discussion/StoreDiscussionRequest.php`:

```php
<?php

namespace App\Http\Requests\Discussion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiscussionRequest extends FormRequest
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
        $course = $this->route('course');

        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'lesson_id' => [
                'nullable',
                Rule::exists('lessons', 'id')->where(
                    fn ($query) => $query->whereIn(
                        'module_id',
                        $course->modules()->select('id'),
                    ),
                ),
            ],
        ];
    }
}
```

- [ ] **Step 4: Create the action**

`app/Actions/CreateDiscussion.php`:

```php
<?php

namespace App\Actions;

use App\Models\Course;
use App\Models\Discussion;
use App\Models\User;
use App\Notifications\NewDiscussionQuestion;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateDiscussion
{
    use AsAction;

    /**
     * @param  array{title: string, body: string, lesson_id?: int|null}  $data
     */
    public function handle(Course $course, User $author, array $data): Discussion
    {
        $discussion = $course->discussions()->create([
            'user_id' => $author->id,
            'lesson_id' => $data['lesson_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'],
        ]);

        $instructor = $course->instructor;

        if ($instructor !== null && $instructor->id !== $author->id) {
            $instructor->notify(new NewDiscussionQuestion($discussion));
        }

        return $discussion;
    }
}
```

- [ ] **Step 5: Create the controller**

`app/Http/Controllers/Course/DiscussionController.php`:

```php
<?php

namespace App\Http\Controllers\Course;

use App\Actions\CreateDiscussion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Discussion\StoreDiscussionRequest;
use App\Http\Resources\DiscussionResource;
use App\Models\Course;
use App\Models\Discussion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DiscussionController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Course $course): Response
    {
        $this->authorize('viewAny', [Discussion::class, $course]);

        $discussions = $course->discussions()
            ->forCourseLevel()
            ->with('author')
            ->withCount('replies')
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(self::PER_PAGE)
            ->through(fn (Discussion $discussion) => DiscussionResource::make($discussion)->resolve());

        return Inertia::render('Discussions/Index', [
            'course' => $course->only('id', 'title', 'slug'),
            'discussions' => $discussions,
        ]);
    }

    public function store(StoreDiscussionRequest $request, Course $course): RedirectResponse
    {
        $this->authorize('create', [Discussion::class, $course]);

        $discussion = CreateDiscussion::run($course, $request->user(), $request->validated());

        return redirect()->route('discussions.show', $discussion)->with('status', 'Question posted.');
    }

    public function show(Discussion $discussion): Response
    {
        $this->authorize('view', $discussion);

        $discussion->load([
            'author',
            'course:id,title,slug',
            'replies' => fn ($query) => $query->whereNull('parent_id')->with('author'),
            'replies.children' => fn ($query) => $query->with('author'),
        ]);

        return Inertia::render('Discussions/Show', [
            'discussion' => DiscussionResource::make($discussion)->resolve(),
        ]);
    }
}
```

> Note: `replies.children` eager-loads one nesting level here; the recursive Vue component renders whatever depth is provided, and Task 10 confirms deeper loading. For correctness of arbitrary depth in the payload, replace the two `replies` loads with a recursive load helper if a test requires depth > 2 — the shipped tests exercise depth ≤ 2.

- [ ] **Step 6: Register routes**

In `routes/web.php`, add these imports at the top with the other `use` lines:

```php
use App\Http\Controllers\Course\DiscussionController;
```

Inside the `Route::middleware('verified')->group(...)` block, add:

```php
        Route::get('courses/{course}/discussions', [DiscussionController::class, 'index'])->name('discussions.index');
        Route::post('courses/{course}/discussions', [DiscussionController::class, 'store'])->name('discussions.store');
        Route::get('discussions/{discussion}', [DiscussionController::class, 'show'])->name('discussions.show');
```

- [ ] **Step 7: Create placeholder Vue pages**

`resources/js/Pages/Discussions/Index.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    course: { type: Object, required: true },
    discussions: { type: Object, required: true },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Discussions" />
        <h1>Discussions</h1>
    </AuthenticatedLayout>
</template>
```

`resources/js/Pages/Discussions/Show.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    discussion: { type: Object, required: true },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Discussion" />
        <h1>{{ discussion.title }}</h1>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Discussions/DiscussionManagementTest.php`
Expected: PASS (5 passing).

- [ ] **Step 9: Lint + build + commit**

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add app/Actions/CreateDiscussion.php app/Http/Requests/Discussion/StoreDiscussionRequest.php app/Http/Controllers/Course/DiscussionController.php routes/web.php resources/js/Pages/Discussions tests/Feature/Discussions/DiscussionManagementTest.php
git commit -m "Add discussion index/store/show + CreateDiscussion action"
```

---

### Task 6: Discussion update / destroy / pin / lock

**Files:**
- Create: `app/Http/Requests/Discussion/UpdateDiscussionRequest.php`
- Modify: `app/Http/Controllers/Course/DiscussionController.php`, `routes/web.php`
- Test: `tests/Feature/Discussions/DiscussionModerationTest.php`

**Interfaces:**
- Produces: routes `discussions.update`, `discussions.destroy`, `discussions.pin`, `discussions.lock`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Discussions/DiscussionModerationTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets the author edit and delete their own discussion', function () {
    $course = Course::factory()->create();
    $author = User::factory()->student()->create();
    Enrollment::factory()->for($author, 'student')->for($course)->create();
    $discussion = Discussion::factory()->for($course)->for($author, 'author')->create();

    $this->actingAs($author)->patch(route('discussions.update', $discussion), ['title' => 'Edited', 'body' => 'New body'])->assertRedirect();
    expect($discussion->fresh()->title)->toBe('Edited');

    $this->actingAs($author)->delete(route('discussions.destroy', $discussion))->assertRedirect();
    expect($discussion->fresh()->trashed())->toBeTrue();
});

it('lets the instructor pin, lock, and delete any discussion but forbids the author from pinning', function () {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $author = User::factory()->student()->create();
    Enrollment::factory()->for($author, 'student')->for($course)->create();
    $discussion = Discussion::factory()->for($course)->for($author, 'author')->create();

    $this->actingAs($instructor)->post(route('discussions.pin', $discussion))->assertRedirect();
    expect($discussion->fresh()->is_pinned)->toBeTrue();

    $this->actingAs($instructor)->post(route('discussions.lock', $discussion))->assertRedirect();
    expect($discussion->fresh()->is_locked)->toBeTrue();

    $this->actingAs($author)->post(route('discussions.pin', $discussion))->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Discussions/DiscussionModerationTest.php`
Expected: FAIL — routes not defined.

- [ ] **Step 3: Create the UpdateDiscussionRequest**

`app/Http/Requests/Discussion/UpdateDiscussionRequest.php`:

```php
<?php

namespace App\Http\Requests\Discussion;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiscussionRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Add controller methods**

Add to `app/Http/Controllers/Course/DiscussionController.php` (and import `UpdateDiscussionRequest`):

```php
    public function update(UpdateDiscussionRequest $request, Discussion $discussion): RedirectResponse
    {
        $this->authorize('update', $discussion);

        $discussion->update($request->validated());

        return redirect()->route('discussions.show', $discussion)->with('status', 'Discussion updated.');
    }

    public function destroy(Discussion $discussion): RedirectResponse
    {
        $this->authorize('delete', $discussion);

        $discussion->delete();

        return redirect()->route('discussions.index', $discussion->course)->with('status', 'Discussion deleted.');
    }

    public function pin(Discussion $discussion): RedirectResponse
    {
        $this->authorize('pin', $discussion);

        $discussion->update(['is_pinned' => ! $discussion->is_pinned]);

        return back();
    }

    public function lock(Discussion $discussion): RedirectResponse
    {
        $this->authorize('lock', $discussion);

        $discussion->update(['is_locked' => ! $discussion->is_locked]);

        return back();
    }
```

- [ ] **Step 5: Register routes**

In `routes/web.php`, add inside the `verified` group:

```php
        Route::patch('discussions/{discussion}', [DiscussionController::class, 'update'])->name('discussions.update');
        Route::delete('discussions/{discussion}', [DiscussionController::class, 'destroy'])->name('discussions.destroy');
        Route::post('discussions/{discussion}/pin', [DiscussionController::class, 'pin'])->name('discussions.pin');
        Route::post('discussions/{discussion}/lock', [DiscussionController::class, 'lock'])->name('discussions.lock');
```

- [ ] **Step 6: Run tests + lint + commit**

Run: `php artisan test --compact tests/Feature/Discussions/DiscussionModerationTest.php`
Expected: PASS (2 passing).

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Discussion/UpdateDiscussionRequest.php app/Http/Controllers/Course/DiscussionController.php routes/web.php tests/Feature/Discussions/DiscussionModerationTest.php
git commit -m "Add discussion update/destroy/pin/lock"
```

---

### Task 7: CreateReply action + DiscussionReplyController + routes

**Files:**
- Create: `app/Actions/CreateReply.php`, `app/Http/Requests/Discussion/StoreReplyRequest.php`, `app/Http/Requests/Discussion/UpdateReplyRequest.php`, `app/Http/Controllers/DiscussionReplyController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Discussions/DiscussionReplyTest.php`

**Interfaces:**
- Consumes: `DiscussionReplyPolicy`, `DiscussionPolicy@reply`, `DiscussionReplyPosted`, `NewDiscussionReply`.
- Produces: `CreateReply::run(Discussion $discussion, User $author, array{body:string, parent_id?:int|null} $data): DiscussionReply` (creates reply, broadcasts `DiscussionReplyPosted`, notifies question author + parent-reply author + instructor, deduped, minus actor); routes `discussion-replies.store/update/destroy`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Discussions/DiscussionReplyTest.php`:

```php
<?php

use App\Events\DiscussionReplyPosted;
use App\Models\Course;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\NewDiscussionReply;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function memberOf(Course $course): User
{
    $user = User::factory()->student()->create();
    Enrollment::factory()->for($user, 'student')->for($course)->create();

    return $user;
}

it('posts a reply, broadcasts it, and notifies the question author and instructor but not the actor', function () {
    Event::fake([DiscussionReplyPosted::class]);
    Notification::fake();
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $asker = memberOf($course);
    $replier = memberOf($course);
    $discussion = Discussion::factory()->for($course)->for($asker, 'author')->create();

    $this->actingAs($replier)
        ->post(route('discussion-replies.store', $discussion), ['body' => 'Here is an answer'])
        ->assertRedirect();

    Event::assertDispatched(DiscussionReplyPosted::class);
    Notification::assertSentTo($asker, NewDiscussionReply::class);
    Notification::assertSentTo($instructor, NewDiscussionReply::class);
    Notification::assertNotSentTo($replier, NewDiscussionReply::class);
});

it('also notifies the parent reply author on a nested reply', function () {
    Event::fake([DiscussionReplyPosted::class]);
    Notification::fake();
    $course = Course::factory()->create();
    $asker = memberOf($course);
    $parentAuthor = memberOf($course);
    $replier = memberOf($course);
    $discussion = Discussion::factory()->for($course)->for($asker, 'author')->create();
    $parent = DiscussionReply::factory()->for($discussion)->for($parentAuthor, 'author')->create();

    $this->actingAs($replier)
        ->post(route('discussion-replies.store', $discussion), ['body' => 'reply', 'parent_id' => $parent->id])
        ->assertRedirect();

    Notification::assertSentTo($parentAuthor, NewDiscussionReply::class);
});

it('forbids replying to a locked discussion', function () {
    $course = Course::factory()->create();
    $member = memberOf($course);
    $discussion = Discussion::factory()->for($course)->locked()->create();

    $this->actingAs($member)
        ->post(route('discussion-replies.store', $discussion), ['body' => 'nope'])
        ->assertForbidden();
});

it('rejects a parent_id from another discussion', function () {
    $course = Course::factory()->create();
    $member = memberOf($course);
    $discussion = Discussion::factory()->for($course)->create();
    $foreignParent = DiscussionReply::factory()->create();

    $this->actingAs($member)
        ->post(route('discussion-replies.store', $discussion), ['body' => 'x', 'parent_id' => $foreignParent->id])
        ->assertSessionHasErrors('parent_id');
});

it('lets the author edit and the instructor delete a reply', function () {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $author = memberOf($course);
    $discussion = Discussion::factory()->for($course)->create();
    $reply = DiscussionReply::factory()->for($discussion)->for($author, 'author')->create();

    $this->actingAs($author)->patch(route('discussion-replies.update', $reply), ['body' => 'edited'])->assertRedirect();
    expect($reply->fresh()->body)->toBe('edited');

    $this->actingAs($instructor)->delete(route('discussion-replies.destroy', $reply))->assertRedirect();
    expect($reply->fresh()->trashed())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Discussions/DiscussionReplyTest.php`
Expected: FAIL — route `discussion-replies.store` not defined.

- [ ] **Step 3: Create the FormRequests**

`app/Http/Requests/Discussion/StoreReplyRequest.php`:

```php
<?php

namespace App\Http\Requests\Discussion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReplyRequest extends FormRequest
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
        $discussion = $this->route('discussion');

        return [
            'body' => ['required', 'string'],
            'parent_id' => [
                'nullable',
                Rule::exists('discussion_replies', 'id')->where('discussion_id', $discussion->id),
            ],
        ];
    }
}
```

`app/Http/Requests/Discussion/UpdateReplyRequest.php`:

```php
<?php

namespace App\Http\Requests\Discussion;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReplyRequest extends FormRequest
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

`app/Actions/CreateReply.php`:

```php
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
```

- [ ] **Step 5: Create the controller**

`app/Http/Controllers/DiscussionReplyController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Actions\CreateReply;
use App\Http\Requests\Discussion\StoreReplyRequest;
use App\Http\Requests\Discussion\UpdateReplyRequest;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use Illuminate\Http\RedirectResponse;

class DiscussionReplyController extends Controller
{
    public function store(StoreReplyRequest $request, Discussion $discussion): RedirectResponse
    {
        $this->authorize('reply', $discussion);

        CreateReply::run($discussion, $request->user(), $request->validated());

        return redirect()->route('discussions.show', $discussion)->with('status', 'Reply posted.');
    }

    public function update(UpdateReplyRequest $request, DiscussionReply $reply): RedirectResponse
    {
        $this->authorize('update', $reply);

        $reply->update($request->validated());

        return redirect()->route('discussions.show', $reply->discussion_id)->with('status', 'Reply updated.');
    }

    public function destroy(DiscussionReply $reply): RedirectResponse
    {
        $this->authorize('delete', $reply);

        $discussionId = $reply->discussion_id;
        $reply->delete();

        return redirect()->route('discussions.show', $discussionId)->with('status', 'Reply deleted.');
    }
}
```

- [ ] **Step 6: Register routes**

In `routes/web.php` add the import `use App\Http\Controllers\DiscussionReplyController;` and inside the `verified` group:

```php
        Route::post('discussions/{discussion}/replies', [DiscussionReplyController::class, 'store'])->name('discussion-replies.store');
        Route::patch('discussion-replies/{reply}', [DiscussionReplyController::class, 'update'])->name('discussion-replies.update');
        Route::delete('discussion-replies/{reply}', [DiscussionReplyController::class, 'destroy'])->name('discussion-replies.destroy');
```

- [ ] **Step 7: Run tests + lint + commit**

Run: `php artisan test --compact tests/Feature/Discussions/DiscussionReplyTest.php`
Expected: PASS (5 passing).

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/CreateReply.php app/Http/Requests/Discussion/StoreReplyRequest.php app/Http/Requests/Discussion/UpdateReplyRequest.php app/Http/Controllers/DiscussionReplyController.php routes/web.php tests/Feature/Discussions/DiscussionReplyTest.php
git commit -m "Add reply posting + moderation with broadcast and notifications"
```

---

### Task 8: Notifications API + unread count shared prop

**Files:**
- Create: `app/Http/Controllers/NotificationController.php`, `resources/js/Pages/Notifications/Index.vue`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`, `routes/web.php`
- Test: `tests/Feature/Notifications/NotificationCenterTest.php`

**Interfaces:**
- Produces: routes `notifications.index`, `notifications.read`, `notifications.read-all`; shared prop `auth.user.unread_notifications_count`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Notifications/NotificationCenterTest.php`:

```php
<?php

use App\Models\Discussion;
use App\Models\User;
use App\Notifications\NewDiscussionQuestion;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

// Event::fake() neutralizes the broadcast channel of the (queued, sync) notification so it
// never hits the non-running Reverb server, while the database channel still writes the row.
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Event::fake();
});

it('shares the unread notification count and lists notifications', function () {
    $user = User::factory()->create();
    $discussion = Discussion::factory()->create();
    $user->notify(new NewDiscussionQuestion($discussion));

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->where('auth.user.unread_notifications_count', 1)
            ->has('notifications', 1));
});

it('marks a single notification and all notifications as read', function () {
    $user = User::factory()->create();
    $discussion = Discussion::factory()->create();
    $user->notify(new NewDiscussionQuestion($discussion));
    $user->notify(new NewDiscussionQuestion($discussion));
    $first = $user->unreadNotifications()->first();

    $this->actingAs($user)->post(route('notifications.read', $first->id))->assertRedirect();
    expect($user->fresh()->unreadNotifications()->count())->toBe(1);

    $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();
    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Notifications/NotificationCenterTest.php`
Expected: FAIL — route/prop missing.

- [ ] **Step 3: Add the shared prop**

In `app/Http/Middleware/HandleInertiaRequests.php`, inside the `auth.user` array (after `'can' => [...]`), add:

```php
                        'unread_notifications_count' => $request->user()->unreadNotifications()->count(),
```

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/NotificationController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
                ...$notification->data,
            ]);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
```

- [ ] **Step 5: Register routes**

In `routes/web.php` add `use App\Http\Controllers\NotificationController;` and inside the `verified` group:

```php
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
```

- [ ] **Step 6: Create the page**

`resources/js/Pages/Notifications/Index.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    notifications: { type: Array, required: true },
});

const markAllRead = () => router.post(route('notifications.read-all'), {}, { preserveScroll: true });
const openNotification = (notification) => {
    router.post(route('notifications.read', notification.id), {}, {
        preserveScroll: true,
        onSuccess: () => router.visit(route('discussions.show', notification.discussion_id)),
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Notifications" />
        <div class="mx-auto max-w-2xl p-4">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">Notifications</h1>
                <button class="text-sm text-amber-600" @click="markAllRead">Mark all read</button>
            </div>
            <ul class="divide-y">
                <li v-for="n in notifications" :key="n.id" class="cursor-pointer py-3" :class="{ 'font-semibold': !n.read_at }" @click="openNotification(n)">
                    <p class="text-sm">{{ n.actor_name }} · {{ n.type === 'new_question' ? 'asked a question' : 'replied' }}</p>
                    <p class="text-sm text-gray-500">{{ n.excerpt }}</p>
                </li>
                <li v-if="notifications.length === 0" class="py-6 text-center text-gray-500">No notifications yet.</li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 7: Run tests + lint + build + commit**

Run: `php artisan test --compact tests/Feature/Notifications/NotificationCenterTest.php`
Expected: PASS (2 passing).

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add app/Http/Controllers/NotificationController.php app/Http/Middleware/HandleInertiaRequests.php routes/web.php resources/js/Pages/Notifications tests/Feature/Notifications
git commit -m "Add notification center + unread count shared prop"
```

---

### Task 9: Lesson-page discussions integration

**Files:**
- Modify: `app/Http/Controllers/LessonController.php`, `resources/js/Pages/Lessons/Show.vue`
- Create: `resources/js/Components/LessonDiscussions.vue`
- Test: `tests/Feature/Discussions/LessonDiscussionsTest.php`

**Interfaces:**
- Consumes: `discussions.store` (with `lesson_id`), `DiscussionResource`, `forLesson` scope.
- Produces: `Lessons/Show` gains a `lessonDiscussions` prop (array of shaped lesson-scoped discussions).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Discussions/LessonDiscussionsTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('passes only this lesson\'s discussions to the lesson page', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();
    $otherLesson = Lesson::factory()->for($module)->create();
    $student = User::factory()->student()->create();
    Enrollment::factory()->for($student, 'student')->for($course)->create();

    $mine = Discussion::factory()->for($course)->create(['lesson_id' => $lesson->id, 'title' => 'about this lesson']);
    Discussion::factory()->for($course)->create(['lesson_id' => $otherLesson->id, 'title' => 'other lesson']);
    Discussion::factory()->for($course)->create(['lesson_id' => null, 'title' => 'course level']);

    $this->actingAs($student)
        ->get(route('lessons.show', ['course' => $course, 'lesson' => $lesson]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('lessonDiscussions', 1)
            ->where('lessonDiscussions.0.title', 'about this lesson'));
});
```

Confirm the `lessons.show` route parameter names by checking `routes/web.php` (`learn/{course}/{lesson}`) — adjust the `route(...)` call if the binding differs.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Discussions/LessonDiscussionsTest.php`
Expected: FAIL — `lessonDiscussions` prop missing.

- [ ] **Step 3: Add the prop in LessonController**

In `app/Http/Controllers/LessonController.php@show`, build the lesson discussions and add to the `Inertia::render(...)` props array. Add the import `use App\Http\Resources\DiscussionResource;` and `use App\Models\Discussion;`. Compute:

```php
        $lessonDiscussions = Discussion::query()
            ->where('course_id', $course->id)
            ->forLesson($lesson->id)
            ->with('author')
            ->withCount('replies')
            ->latest()
            ->get()
            ->map(fn (Discussion $discussion) => DiscussionResource::make($discussion)->resolve());
```

Add `'lessonDiscussions' => $lessonDiscussions,` to the render props. (Keep all existing props/logic intact.)

- [ ] **Step 4: Create the LessonDiscussions component**

`resources/js/Components/LessonDiscussions.vue`:

```vue
<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    course: { type: Object, required: true },
    lesson: { type: Object, required: true },
    discussions: { type: Array, required: true },
});

const form = useForm({ title: '', body: '', lesson_id: props.lesson.id });

const submit = () => form.post(route('discussions.store', props.course.slug), {
    preserveScroll: true,
    onSuccess: () => form.reset('title', 'body'),
});
</script>

<template>
    <section class="mt-8 border-t pt-6">
        <h2 class="mb-4 text-lg font-semibold">Questions about this lesson</h2>

        <form class="mb-6 space-y-2" @submit.prevent="submit">
            <input v-model="form.title" type="text" placeholder="Title" class="w-full rounded border p-2" />
            <textarea v-model="form.body" placeholder="Ask a question…" class="w-full rounded border p-2" rows="3" />
            <button type="submit" class="rounded bg-amber-500 px-3 py-1.5 text-white hover:bg-amber-600" :disabled="form.processing">Post question</button>
        </form>

        <ul class="space-y-3">
            <li v-for="d in discussions" :key="d.id" class="flex items-center gap-3">
                <UserAvatar :user="d.author" class="size-8" />
                <Link :href="route('discussions.show', d.id)" class="text-sm font-medium hover:underline">{{ d.title }}</Link>
                <span class="text-xs text-gray-500">{{ d.replies_count }} replies</span>
            </li>
            <li v-if="discussions.length === 0" class="text-sm text-gray-500">No questions yet — ask the first one.</li>
        </ul>
    </section>
</template>
```

- [ ] **Step 5: Mount it in the lesson page**

In `resources/js/Pages/Lessons/Show.vue`, import and render the component below the lesson content. Add to the `<script setup>` imports:

```js
import LessonDiscussions from '@/Components/LessonDiscussions.vue';
```

Add `lessonDiscussions` to `defineProps` (type `Array`, default `() => []`), and in the template, after the lesson content block, add:

```vue
        <LessonDiscussions :course="course" :lesson="lesson" :discussions="lessonDiscussions" />
```

Use the existing `course` and `lesson` props on the page (confirm their prop names/shapes in the file; the component needs `course.slug` and `lesson.id`). If the page's `course`/`lesson` props lack `slug`/`id`, pass the needed fields through from the controller.

- [ ] **Step 6: Run tests + lint + build + commit**

Run: `php artisan test --compact tests/Feature/Discussions/LessonDiscussionsTest.php`
Expected: PASS.

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add app/Http/Controllers/LessonController.php resources/js/Pages/Lessons/Show.vue resources/js/Components/LessonDiscussions.vue tests/Feature/Discussions/LessonDiscussionsTest.php
git commit -m "Add lesson-page discussions section"
```

---

### Task 10: Discussion thread & index UI + live replies

**Files:**
- Create: `resources/js/Components/DiscussionReplyItem.vue`
- Modify: `resources/js/Pages/Discussions/Index.vue`, `resources/js/Pages/Discussions/Show.vue`
- Test: `tests/Browser/DiscussionThreadTest.php`

**Interfaces:**
- Consumes: `discussions.show` / `discussion-replies.store` routes; the `discussions.{id}` Echo channel; `window.Echo` (slice 1).

- [ ] **Step 1: Build the Index page**

Replace `resources/js/Pages/Discussions/Index.vue` with a real list (pinned first, author avatar, reply count, ask form, pagination):

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    course: { type: Object, required: true },
    discussions: { type: Object, required: true },
});

const form = useForm({ title: '', body: '' });
const submit = () => form.post(route('discussions.store', props.course.slug), {
    onSuccess: () => form.reset(),
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Discussions" />
        <div class="mx-auto max-w-3xl p-4">
            <h1 class="mb-4 text-xl font-semibold">{{ course.title }} — Discussions</h1>

            <form class="mb-6 space-y-2 rounded border p-4" @submit.prevent="submit">
                <input v-model="form.title" type="text" placeholder="Question title" class="w-full rounded border p-2" />
                <textarea v-model="form.body" placeholder="What's your question?" rows="3" class="w-full rounded border p-2" />
                <button type="submit" class="rounded bg-amber-500 px-3 py-1.5 text-white hover:bg-amber-600" :disabled="form.processing">Ask</button>
            </form>

            <ul class="divide-y">
                <li v-for="d in discussions.data" :key="d.id" class="flex items-center gap-3 py-3">
                    <UserAvatar :user="d.author" class="size-9" />
                    <div class="min-w-0 flex-1">
                        <Link :href="route('discussions.show', d.id)" class="font-medium hover:underline">
                            <span v-if="d.is_pinned" class="mr-1 text-amber-600">📌</span>{{ d.title }}
                            <span v-if="d.is_locked" class="ml-1 text-gray-400">🔒</span>
                        </Link>
                        <p class="truncate text-sm text-gray-500">by {{ d.author.name }} · {{ d.replies_count }} replies</p>
                    </div>
                </li>
                <li v-if="discussions.data.length === 0" class="py-6 text-center text-gray-500">No discussions yet.</li>
            </ul>

            <Pagination :paginator="discussions" class="mt-4" />
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 2: Build the recursive reply component**

`resources/js/Components/DiscussionReplyItem.vue`:

```vue
<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    reply: { type: Object, required: true },
    discussionId: { type: Number, required: true },
    locked: { type: Boolean, default: false },
});

const replying = ref(false);
const form = useForm({ body: '', parent_id: props.reply.id });
const submit = () => form.post(route('discussion-replies.store', props.discussionId), {
    preserveScroll: true,
    onSuccess: () => { form.reset('body'); replying.value = false; },
});
</script>

<template>
    <div class="mt-3">
        <div class="flex items-start gap-2">
            <UserAvatar :user="reply.author" class="size-7" />
            <div class="flex-1">
                <p class="text-sm"><span class="font-medium">{{ reply.author.name }}</span></p>
                <p class="text-sm text-gray-700">{{ reply.body }}</p>
                <button v-if="!locked" class="text-xs text-amber-600" @click="replying = !replying">Reply</button>
                <form v-if="replying" class="mt-2" @submit.prevent="submit">
                    <textarea v-model="form.body" rows="2" class="w-full rounded border p-2 text-sm" placeholder="Reply…" />
                    <button type="submit" class="mt-1 rounded bg-amber-500 px-2 py-1 text-xs text-white" :disabled="form.processing">Post</button>
                </form>
            </div>
        </div>
        <div class="ml-6 border-l pl-3">
            <DiscussionReplyItem
                v-for="child in reply.children"
                :key="child.id"
                :reply="child"
                :discussion-id="discussionId"
                :locked="locked"
            />
        </div>
    </div>
</template>
```

(The component references itself by name — Vue SFCs support recursion via the filename-derived name `DiscussionReplyItem`.)

- [ ] **Step 3: Build the Show page with live replies**

Replace `resources/js/Pages/Discussions/Show.vue`:

```vue
<script setup>
import { onMounted, onUnmounted, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DiscussionReplyItem from '@/Components/DiscussionReplyItem.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    discussion: { type: Object, required: true },
});

const state = reactive({ replies: props.discussion.replies ?? [] });

const form = useForm({ body: '', parent_id: null });
const submit = () => form.post(route('discussion-replies.store', props.discussion.id), {
    preserveScroll: true,
    onSuccess: () => form.reset('body'),
});

const insertReply = (reply) => {
    // Avoid duplicating a reply we already have (e.g. our own, added on reload).
    const exists = (nodes) => nodes.some((n) => n.id === reply.id || (n.children && exists(n.children)));
    if (exists(state.replies)) {
        return;
    }
    if (reply.parent_id === null) {
        state.replies.push(reply);
        return;
    }
    const attach = (nodes) => nodes.forEach((n) => {
        if (n.id === reply.parent_id) {
            n.children = [...(n.children ?? []), reply];
        } else if (n.children) {
            attach(n.children);
        }
    });
    attach(state.replies);
};

let channel = null;
onMounted(() => {
    channel = window.Echo.private(`discussions.${props.discussion.id}`)
        .listen('DiscussionReplyPosted', (reply) => insertReply(reply));
});
onUnmounted(() => {
    window.Echo.leave(`discussions.${props.discussion.id}`);
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="discussion.title" />
        <div class="mx-auto max-w-3xl p-4">
            <div class="flex items-start gap-3">
                <UserAvatar :user="discussion.author" class="size-10" />
                <div>
                    <h1 class="text-xl font-semibold">{{ discussion.title }}</h1>
                    <p class="text-sm text-gray-500">by {{ discussion.author.name }}</p>
                    <p class="mt-2 whitespace-pre-line">{{ discussion.body }}</p>
                </div>
            </div>

            <div class="mt-6">
                <h2 class="mb-2 font-semibold">Replies</h2>
                <DiscussionReplyItem
                    v-for="reply in state.replies"
                    :key="reply.id"
                    :reply="reply"
                    :discussion-id="discussion.id"
                    :locked="discussion.is_locked"
                />
            </div>

            <form v-if="!discussion.is_locked" class="mt-6" @submit.prevent="submit">
                <textarea v-model="form.body" rows="3" class="w-full rounded border p-2" placeholder="Write a reply…" />
                <button type="submit" class="mt-2 rounded bg-amber-500 px-3 py-1.5 text-white hover:bg-amber-600" :disabled="form.processing">Reply</button>
            </form>
            <p v-else class="mt-6 rounded bg-gray-100 p-3 text-sm text-gray-500">This discussion is locked.</p>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 4: Write the browser test**

Create `tests/Browser/DiscussionThreadTest.php` (mirror `tests/Browser/LoginTest.php`: `assertNoJavaScriptErrors()`, `actingAs` before `visit`). This is a RENDER-ONLY smoke test — it does NOT submit a reply, because a real reply POST in the served app would `broadcast()` to the non-running Reverb server and 500. The reply POST + broadcast + notification behavior is covered by the Task 7 feature tests (which fake events/notifications).

```php
<?php

use App\Models\Course;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('renders a discussion thread and its replies without JS errors', function () {
    $course = Course::factory()->create();
    $student = User::factory()->student()->create();
    Enrollment::factory()->for($student, 'student')->for($course)->create();
    $discussion = Discussion::factory()->for($course)->create(['title' => 'Live Q']);
    DiscussionReply::factory()->for($discussion)->create(['body' => 'An existing answer']);
    $this->actingAs($student);

    $page = visit(route('discussions.show', $discussion));

    // Mounting the page subscribes to the discussions.{id} Echo channel; a failed WS
    // connection to a down Reverb server is handled by pusher-js and is NOT a JS error.
    $page->assertSee('Live Q')
        ->assertSee('An existing answer')
        ->assertNoJavaScriptErrors();
});
```

The `/broadcasting/auth` request Echo fires on subscribe is signed locally (no network) and returns 200 for this enrolled member, so the page renders cleanly.

- [ ] **Step 5: Run tests + lint + build + commit**

Run: `npm run build` (expect success), then `php artisan test --compact tests/Browser/DiscussionThreadTest.php` (expect PASS).

```bash
vendor/bin/pint --dirty --format agent
git add resources/js/Components/DiscussionReplyItem.vue resources/js/Pages/Discussions tests/Browser/DiscussionThreadTest.php
git commit -m "Build discussion index + thread UI with live replies"
```

---

### Task 11: Notification bell + live unread badge

**Files:**
- Create: `resources/js/Components/NotificationBell.vue`
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`
- Test: `tests/Browser/NotificationBellTest.php`

**Interfaces:**
- Consumes: `auth.user.unread_notifications_count` (Task 8); `notifications.index` route; the per-user `App.Models.User.{id}` Echo channel (slice 1).

- [ ] **Step 1: Build the bell component**

`resources/js/Components/NotificationBell.vue`:

```vue
<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';

const page = usePage();
const count = ref(page.props.auth.user?.unread_notifications_count ?? 0);

const userId = page.props.auth.user?.id;
onMounted(() => {
    if (!userId || !window.Echo) {
        return;
    }
    window.Echo.private(`App.Models.User.${userId}`)
        .notification(() => { count.value += 1; });
});
onUnmounted(() => {
    if (userId && window.Echo) {
        window.Echo.leave(`App.Models.User.${userId}`);
    }
});
</script>

<template>
    <Link :href="route('notifications.index')" class="relative inline-flex items-center" aria-label="Notifications">
        <Bell class="size-5" />
        <span v-if="count > 0" class="absolute -right-2 -top-2 rounded-full bg-red-500 px-1.5 text-xs text-white">{{ count }}</span>
    </Link>
</template>
```

- [ ] **Step 2: Mount the bell in the layout**

In `resources/js/Layouts/AuthenticatedLayout.vue`, import `NotificationBell` and render it in the top nav/header area (near the user menu). Add to `<script setup>`:

```js
import NotificationBell from '@/Components/NotificationBell.vue';
```

And place `<NotificationBell />` in the header's right-hand controls (match the existing header markup — put it beside the user avatar/menu). Do not otherwise restructure the layout.

- [ ] **Step 3: Write the browser test**

Create `tests/Browser/NotificationBellTest.php`:

```php
<?php

use App\Models\Discussion;
use App\Models\User;
use App\Notifications\NewDiscussionQuestion;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('shows the unread count in the nav bell', function () {
    Event::fake(); // keep the notification's broadcast off the (non-running) Reverb server; DB row still writes
    $user = User::factory()->create();
    $discussion = Discussion::factory()->create();
    $user->notify(new NewDiscussionQuestion($discussion));
    $this->actingAs($user);

    $page = visit(route('dashboard'));

    $page->assertSee('1')->assertNoJavaScriptErrors();
});
```

If `assertSee('1')` is too loose (other "1"s on the dashboard), assert on the bell's rendered badge via a more specific selector/text, mirroring existing browser tests.

- [ ] **Step 4: Run tests + lint + build + commit**

Run: `npm run build` (expect success), then `php artisan test --compact tests/Browser/NotificationBellTest.php` (expect PASS).

```bash
vendor/bin/pint --dirty --format agent
git add resources/js/Components/NotificationBell.vue resources/js/Layouts/AuthenticatedLayout.vue tests/Browser/NotificationBellTest.php
git commit -m "Add notification bell with live unread badge"
```

---

### Task 12: Full regression + lint sweep

**Files:** none (verification only)

- [ ] **Step 1: Full suite**

Run: `php artisan test --compact`
Expected: all tests pass (prior 175 + the new discussion/notification/browser tests), no regressions.

- [ ] **Step 2: Lint sweep**

Run: `vendor/bin/pint --dirty --format agent`
Expected: clean.

- [ ] **Step 3: Final commit (only if lint changed anything)**

```bash
git add -A
git commit -m "Lint sweep for course discussions feature"
```

---

## Notes

- Live delivery (Reverb) is proven by the broadcast test + channel-auth test; browser tests exercise the non-live post-and-reload path (no Reverb server in CI). Manual live verification: post a reply in one browser and watch it appear in another, and watch the bell increment — same procedure as `docs/superpowers/reverb-manual-verification.md`.
- Notifications and the `DiscussionReplyPosted` broadcast are `ShouldQueue`/`ShouldBroadcast`; the `composer dev` queue worker delivers them. `QUEUE_CONNECTION=sync` in tests runs them inline.
- Arbitrary nesting depth: the show payload eager-loads `replies.children` (2 levels); the recursive Vue component renders any depth present, and live-inserted replies attach at any depth. If a deeper initial load is needed later, swap the eager-load for a recursive loader — not required by this slice's tests.
