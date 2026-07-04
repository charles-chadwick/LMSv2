# Course & Lesson Discussions (Live Q&A) — Design

**Date:** 2026-07-03
**Branch:** feature/course-discussions
**Status:** Approved

## Summary

Build threaded Q&A discussions attached to courses and lessons. Enrolled students and the
course instructor can post questions and nested replies. New replies appear live in an open
thread via Reverb, and reply/question activity generates database + broadcast notifications
with a live unread badge. This is slice 2 of 3 in the discussions+messaging feature
(slice 1, the Reverb foundation, is shipped).

## Goals

- Course-level and lesson-level ("page") discussions, reusing the existing `Discussion`/
  `DiscussionReply` models.
- Arbitrary-depth nested replies.
- Live reply insertion in an open thread (Reverb, per-discussion private channel).
- Notifications (database + broadcast) on new replies and new questions, with a live
  unread badge and a notifications page.
- Authorization anchored on the existing `CoursePolicy::learn` ability.

## Non-Goals

- Private messaging (slice 3).
- Live-inserting brand-new questions into the list (only replies are live; lists update on
  navigation).
- Rich text / attachments in posts (plain text bodies for now; reuse existing patterns if
  added later).
- Email notifications (database + broadcast only).

## Decisions (from brainstorming)

- **Lesson attachment:** nullable `lesson_id` on `discussions`; `course_id` remains the
  anchor. `lesson_id = null` → course-level; set → lesson-level.
- **Threading:** full nesting via existing `DiscussionReply.parent_id`.
- **Live surface:** replies live in an open thread only.
- **Notifications:** on new reply → question author + parent-reply author (if nested) +
  course instructor; on new question → course instructor. Always deduplicated and never
  notifying the actor of their own action.
- **Title:** required for all discussions (course- and lesson-level), for a uniform model.

## Architecture

### 1. Data model

Migration `add_lesson_id_to_discussions_table`:

```php
Schema::table('discussions', function (Blueprint $table): void {
    $table->foreignId('lesson_id')->nullable()->after('course_id')
        ->constrained()->cascadeOnDelete();
});
```

`Discussion` model changes: add `lesson_id` to `$fillable`; add `lesson(): BelongsTo`
(`belongsTo(Lesson::class)`). Existing `course()`, `author()`, `replies()` unchanged.
Query helpers: `scopeForCourseLevel` (`whereNull('lesson_id')`) and
`scopeForLesson($lesson_id)` for the two list contexts.

`DiscussionReply` unchanged (has `parent()`/`children()` for nesting).

Notifications: `php artisan notifications:table` + migrate (creates the `notifications`
table used by `Notifiable`).

### 2. Authorization

New `DiscussionPolicy` (auto-discovered), delegating course access to the `learn` ability:

- `viewAny(User, Course)` / `view(User, Discussion)` / `create(User, Course)`:
  `$user->can('learn', $course)` (enrolled Active/Completed **or** instructor).
- `reply(User, Discussion)`: `$user->can('learn', $discussion->course) && ! $discussion->is_locked`.
- `update(User, Discussion)`: `$discussion->user_id === $user->id` (author edits own).
- `delete(User, Discussion)`: author **or** `$discussion->course->instructor_id === $user->id`.
- `pin(User, Discussion)` / `lock(User, Discussion)`: `$discussion->course->instructor_id === $user->id`.

New `DiscussionReplyPolicy`:

- `update(User, DiscussionReply)`: author.
- `delete(User, DiscussionReply)`: author **or** the reply's `discussion->course->instructor_id === $user->id`.

Admins bypass everything via the existing `Gate::before`. Controllers call
`$this->authorize(...)` per action, matching app convention.

### 3. Endpoints, controllers, actions

Routes (inside the existing `auth` + `verified` group; `{course}` bound by slug):

```
GET    courses/{course}/discussions            discussions.index    Course\DiscussionController@index
POST   courses/{course}/discussions            discussions.store    Course\DiscussionController@store
GET    discussions/{discussion}                discussions.show     Course\DiscussionController@show
PATCH  discussions/{discussion}                discussions.update   Course\DiscussionController@update
DELETE discussions/{discussion}                discussions.destroy  Course\DiscussionController@destroy
POST   discussions/{discussion}/pin            discussions.pin      Course\DiscussionController@pin
POST   discussions/{discussion}/lock           discussions.lock     Course\DiscussionController@lock
POST   discussions/{discussion}/replies        discussion-replies.store    DiscussionReplyController@store
PATCH  discussion-replies/{reply}              discussion-replies.update   DiscussionReplyController@update
DELETE discussion-replies/{reply}              discussion-replies.destroy  DiscussionReplyController@destroy
GET    notifications                           notifications.index         NotificationController@index
POST   notifications/{notification}/read       notifications.read          NotificationController@read
POST   notifications/read-all                  notifications.read-all      NotificationController@readAll
```

- `Course\DiscussionController@index`: authorize `viewAny`; paginate course-level
  discussions (pinned first, then latest), each with author `UserSummaryResource` +
  `replies_count`; render `Discussions/Index`.
- `store`: `StoreDiscussionRequest` (title required, body required, optional `lesson_id`
  that must belong to `{course}`); authorize `create`; delegate to `CreateDiscussion` action;
  redirect to `discussions.show`.
- `show`: authorize `view`; eager-load the full nested reply tree (`replies.author`,
  recursive `children`) + author; render `Discussions/Show`.
- `update`/`destroy`: authorize `update`/`delete`; soft-delete on destroy.
- `pin`/`lock`: authorize `pin`/`lock`; toggle the boolean; redirect back.
- `DiscussionReplyController@store`: `StoreReplyRequest` (body required, optional
  `parent_id` that must belong to the same discussion); authorize `reply` on the discussion;
  delegate to `CreateReply` action.
- `update`/`destroy`: authorize; soft-delete on destroy.

Actions (`App\Actions\*`, invoked `::run()`):

- `CreateDiscussion::run(Course $course, User $author, array $data): Discussion` — creates
  the discussion, dispatches `NewDiscussionQuestion` to the instructor (minus actor).
- `CreateReply::run(Discussion $discussion, User $author, array $data): DiscussionReply` —
  creates the reply, broadcasts `DiscussionReplyPosted`, dispatches `NewDiscussionReply` to
  the deduped recipient set (question author + parent-reply author + instructor, minus actor).

### 4. Lesson integration

The lesson page (`learn/{course}/{lesson}`, `LessonController@show`) gains a
`lessonDiscussions` prop: the lesson-scoped discussions (`forLesson($lesson->id)`), each
with author + reply count. A `LessonDiscussions.vue` section renders the list + an "ask a
question" form that POSTs to `discussions.store` with `lesson_id` set. The thread view is
the shared `discussions.show` page.

### 5. Real-time (Reverb)

- Event `App\Events\DiscussionReplyPosted implements ShouldBroadcast`, constructor
  `(DiscussionReply $reply)`, `broadcastOn(): PrivateChannel` →
  `new PrivateChannel('discussions.'.$reply->discussion_id)`, `broadcastWith()` returns the
  shaped reply: `id`, `discussion_id`, `parent_id`, `body`, `author` (UserSummaryResource
  array), `created_at` (ISO string).
- `routes/channels.php`:

```php
Broadcast::channel('discussions.{discussion}', function (User $user, int $discussion): bool {
    $model = Discussion::with('course')->find($discussion);

    return $model !== null && $user->can('view', $model);
});
```

- `Discussions/Show.vue` subscribes via `Echo.private('discussions.'+id).listen('DiscussionReplyPosted', ...)`
  and inserts the reply into the nested tree (dedupe if the poster is the current user and
  already appended optimistically / on Inertia reload).

### 6. Notifications & unread UI

- `App\Notifications\NewDiscussionReply` and `App\Notifications\NewDiscussionQuestion`, both
  `via() = ['database', 'broadcast']`. `toArray()` (database) and `toBroadcast()` carry:
  `discussion_id`, `course_slug`, `type`, `actor_name`, `excerpt`, and `url`
  (`route('discussions.show', $discussion)`).
- Broadcast notifications go to `App.Models.User.{id}` (Laravel default for `Notifiable`) —
  the channel shipped in slice 1.
- `HandleInertiaRequests` shares `auth.user.unread_notifications_count`
  (`$user->unreadNotifications()->count()`).
- `AuthenticatedLayout.vue`: a bell icon + unread badge; Echo subscribes to the current
  user's private channel and increments the badge on `.notification` broadcasts.
- `Notifications/Index.vue` (`notifications.index`): lists recent notifications (read +
  unread) with actor/excerpt/link; clicking an item marks it read (`notifications.read`) and
  navigates to the discussion; "mark all read" (`notifications.read-all`).

## Data Flow (new reply)

```
POST discussions/{id}/replies
  → authorize('reply', $discussion)  (learn on course && not locked)
  → CreateReply::run(...)
      → DiscussionReply::create(...)
      → broadcast(new DiscussionReplyPosted($reply))  → Reverb → discussions.{id} listeners
      → Notification::send($recipients, new NewDiscussionReply(...))  (db + broadcast)
  → redirect back to discussions.show
Open thread clients: Echo receives DiscussionReplyPosted → insert into tree.
Recipient nav bells: Echo receives .notification → increment unread badge.
```

## Error Handling

- Locked discussion → `reply` policy denies (403); reply form hidden in UI.
- `lesson_id` / `parent_id` validated to belong to the course / discussion respectively
  (422 otherwise), preventing cross-course or cross-thread grafting.
- Deleting a discussion soft-deletes it and cascades to replies (FK `cascadeOnDelete` +
  soft deletes); the thread 404s afterward.
- Broadcasting/notifications are queued (`ShouldBroadcast`, `ShouldQueue` where relevant) —
  failures never block the HTTP response.
- Actor is always excluded from their own notifications; recipient set is deduped by user id.
- Non-course-members are denied channel authorization, so they cannot receive live replies.

## Testing

Feature (Pest, MariaDB + DatabaseTruncation; `seed(RolePermissionSeeder::class)` in
`beforeEach`):

1. Enrolled student and instructor can create a course-level discussion; non-enrolled → 403.
2. Lesson-scoped discussion creation sets `lesson_id`; `lesson_id` from another course → 422.
3. Index returns course-level discussions (pinned first) and excludes lesson-level ones;
   lesson page prop returns only that lesson's discussions.
4. Reply creation by a course member; locked discussion blocks replies (403); nested reply
   with `parent_id` assembles under its parent.
5. Author edits/deletes own discussion & reply; instructor deletes any and pin/locks; a
   non-author non-instructor is forbidden.
6. `DiscussionReplyPosted` broadcasts on `discussions.{id}` with the shaped payload; channel
   authorization allows a course member and denies an outsider (`/broadcasting/auth`).
7. `Notification::fake()`: a reply notifies question author + parent-reply author +
   instructor, deduped and excluding the actor; a new question notifies the instructor only.
8. `unread_notifications_count` shared prop; `notifications.read` / `read-all` mark
   notifications read.

Browser (pest-plugin-browser): a course member visits a thread, posts a reply, and sees it
render with no JS errors. (Live cross-client insertion is covered by the broadcast test, not
the browser — no Reverb server in CI.)

## Files

**New:**
- `database/migrations/*_add_lesson_id_to_discussions_table.php`
- `database/migrations/*_create_notifications_table.php`
- `app/Policies/DiscussionPolicy.php`, `app/Policies/DiscussionReplyPolicy.php`
- `app/Http/Controllers/Course/DiscussionController.php`
- `app/Http/Controllers/DiscussionReplyController.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Http/Requests/Discussion/StoreDiscussionRequest.php`, `UpdateDiscussionRequest.php`
- `app/Http/Requests/Discussion/StoreReplyRequest.php`, `UpdateReplyRequest.php`
- `app/Actions/CreateDiscussion.php`, `app/Actions/CreateReply.php`
- `app/Events/DiscussionReplyPosted.php`
- `app/Notifications/NewDiscussionReply.php`, `app/Notifications/NewDiscussionQuestion.php`
- `app/Http/Resources/DiscussionResource.php`, `DiscussionReplyResource.php` (shaping)
- `resources/js/Pages/Discussions/Index.vue`, `Show.vue`
- `resources/js/Components/DiscussionReplyItem.vue`, `LessonDiscussions.vue`
- `resources/js/Pages/Notifications/Index.vue`
- `resources/js/Components/NotificationBell.vue`
- `tests/Feature/Discussions/*`, `tests/Browser/DiscussionThreadTest.php`

**Edit:**
- `app/Models/Discussion.php` (lesson relation, fillable, scopes)
- `routes/web.php` (discussion/reply/notification routes)
- `routes/channels.php` (per-discussion `discussions.{discussion}` channel)
- `app/Http/Controllers/LessonController.php` (lessonDiscussions prop)
- `resources/js/Pages/Lessons/Show.vue` (mount `LessonDiscussions`)
- `app/Http/Middleware/HandleInertiaRequests.php` (`unread_notifications_count`)
- `resources/js/Layouts/AuthenticatedLayout.vue` (notification bell + Echo listener)

## Follow-on

Slice 3 (private messaging) reuses the per-user channel, the notifications table/UI, and the
`UserSummaryResource` shape established here.
