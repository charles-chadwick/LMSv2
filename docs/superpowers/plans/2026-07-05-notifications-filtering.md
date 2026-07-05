# Notifications Filtering Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add read/unread, type, and date-range filtering (with pagination) to the Notifications list page, reusing the existing `Filterable` trait + `FilterBar.vue`.

**Architecture:** A custom `App\Models\Notification` extends Laravel's `DatabaseNotification` and carries the `Filterable` trait; `User::notifications()` returns it so the relation is filterable. A `NotificationType` enum centralizes the three stored type strings and feeds both the notification classes and the filter's select options. The controller applies `withFilters()` + `paginate()`; the Vue page gains a `FilterBar` and pagination.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3 + Vue 3, Pest v4. Tests run on MariaDB (`lms_v2_testing`, DatabaseTruncation) — JSON `where` clauses on `data->type` are supported there.

## Global Constraints

- Naming: variables `snake_case`, methods `camelCase`, classes `TitleCase`.
- Prefer OOP; reuse existing Filter strategy classes (`ExactFilter`, `CallbackFilter`, `RangeFilter`) — do not write new ones.
- Curly braces on all control structures; explicit return types and param type hints; PHPDoc array shapes.
- Use factories for test model creation.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes.
- Run affected tests with `php artisan test --compact --filter=...`.
- Filter-only page: `FilterBar` gets `:searchable="false"`. No search box.
- Stored notification types: `new_question`, `new_reply`, `new_message` only (`UserInvitation` is mail-only, never stored).

---

### Task 1: `NotificationType` enum

**Files:**
- Create: `app/Enums/NotificationType.php`
- Test: `tests/Unit/Enums/NotificationTypeTest.php`

**Interfaces:**
- Produces: `NotificationType` backed enum with cases `NewQuestion => 'new_question'`, `NewReply => 'new_reply'`, `NewMessage => 'new_message'`; `label(): string`; `static options(): list<array{value: string, label: string}>` returning friendly labels.

> Note: unlike `CourseStatus` etc. (which use `HasSelectOptions` where value==label), this enum defines its own `options()` because the snake_case case values make poor UI labels.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\NotificationType;

it('maps stored type strings to friendly select options', function () {
    expect(NotificationType::NewQuestion->value)->toBe('new_question')
        ->and(NotificationType::NewReply->value)->toBe('new_reply')
        ->and(NotificationType::NewMessage->value)->toBe('new_message');

    expect(NotificationType::options())->toBe([
        ['value' => 'new_question', 'label' => 'Questions'],
        ['value' => 'new_reply', 'label' => 'Replies'],
        ['value' => 'new_message', 'label' => 'Messages'],
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=NotificationTypeTest`
Expected: FAIL with "Class App\Enums\NotificationType not found".

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Enums;

enum NotificationType: string
{
    case NewQuestion = 'new_question';
    case NewReply = 'new_reply';
    case NewMessage = 'new_message';

    public function label(): string
    {
        return match ($this) {
            self::NewQuestion => 'Questions',
            self::NewReply => 'Replies',
            self::NewMessage => 'Messages',
        };
    }

    /**
     * Value/label option pairs for the notifications type filter.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=NotificationTypeTest`
Expected: PASS.

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/NotificationType.php tests/Unit/Enums/NotificationTypeTest.php
git commit -m "feat: add NotificationType enum for notification filtering"
```

---

### Task 2: Custom `Notification` model, `User::notifications()` override, factory

**Files:**
- Create: `app/Models/Notification.php`
- Create: `database/factories/NotificationFactory.php`
- Modify: `app/Models/User.php` (add `notifications()` override; import `App\Models\Notification`, `Illuminate\Database\Eloquent\Relations\MorphMany`)
- Test: `tests/Feature/Notifications/NotificationModelFilterTest.php`

**Interfaces:**
- Consumes: `NotificationType` (Task 1); existing `Filterable` trait + `ExactFilter`, `CallbackFilter`, `RangeFilter`.
- Produces:
  - `App\Models\Notification extends Illuminate\Notifications\DatabaseNotification` using `Filterable`, `HasFactory`; declares `filterableFields()` with keys `type`, `read`, `created_at`; scope `withFilters(?array $filters)` inherited from `Filterable`.
  - `User::notifications(): MorphMany` returning `App\Models\Notification` ordered `latest()`.
  - `NotificationFactory` with states `ofType(NotificationType)`, `read()`, `unread()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Carbon;

it('filters a user\'s notifications by type, read state, and created_at', function () {
    $user = User::factory()->create();

    $question = Notification::factory()->for($user, 'notifiable')
        ->ofType(NotificationType::NewQuestion)->unread()
        ->create(['created_at' => Carbon::parse('2026-06-01')]);
    Notification::factory()->for($user, 'notifiable')
        ->ofType(NotificationType::NewMessage)->read()
        ->create(['created_at' => Carbon::parse('2026-06-20')]);

    expect($user->notifications()->withFilters(['type' => ['new_question']])->pluck('id')->all())
        ->toBe([$question->id]);
    expect($user->notifications()->withFilters(['read' => 'unread'])->pluck('id')->all())
        ->toBe([$question->id]);
    expect($user->notifications()->withFilters(['created_at' => ['to' => '2026-06-10']])->pluck('id')->all())
        ->toBe([$question->id]);
    expect($user->notifications()->withFilters([])->count())->toBe(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=NotificationModelFilterTest`
Expected: FAIL with "Class App\Models\Notification not found".

- [ ] **Step 3: Create the model**

`app/Models/Notification.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use App\Models\Concerns\Filters\CallbackFilter;
use App\Models\Concerns\Filters\ExactFilter;
use App\Models\Concerns\Filters\Filter;
use App\Models\Concerns\Filters\RangeFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    use Filterable, HasFactory;

    /**
     * Filterable fields for the notifications list.
     *
     * @return array<string, Filter>
     */
    protected function filterableFields(): array
    {
        return [
            'type' => new ExactFilter('data->type'),
            'read' => new CallbackFilter(function (Builder $query, mixed $value): void {
                $values = (array) $value;

                if (in_array('read', $values, true)) {
                    $query->whereNotNull('read_at');
                } elseif (in_array('unread', $values, true)) {
                    $query->whereNull('read_at');
                }
            }),
            'created_at' => new RangeFilter('created_at', asDate: true),
        ];
    }
}
```

- [ ] **Step 4: Create the factory**

`database/factories/NotificationFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $type = NotificationType::NewMessage;

        return [
            'id' => (string) Str::uuid(),
            'type' => \App\Notifications\NewMessage::class,
            'notifiable_type' => (new User)->getMorphClass(),
            'notifiable_id' => User::factory(),
            'data' => ['type' => $type->value],
            'read_at' => null,
        ];
    }

    public function ofType(NotificationType $type): static
    {
        return $this->state(fn (): array => ['data' => ['type' => $type->value]]);
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }

    public function unread(): static
    {
        return $this->state(fn (): array => ['read_at' => null]);
    }
}
```

- [ ] **Step 5: Override `notifications()` on the User model**

In `app/Models/User.php`, add the imports near the other `use` statements:

```php
use App\Models\Notification;
use Illuminate\Database\Eloquent\Relations\MorphMany;
```

Add this method (place it alongside the other relationship methods, e.g. just after `enrollments()`):

```php
/**
 * Use the app's filterable Notification model for all notification reads.
 */
public function notifications(): MorphMany
{
    return $this->morphMany(Notification::class, 'notifiable')->latest();
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=NotificationModelFilterTest`
Expected: PASS.

- [ ] **Step 7: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Notification.php database/factories/NotificationFactory.php app/Models/User.php tests/Feature/Notifications/NotificationModelFilterTest.php
git commit -m "feat: filterable Notification model with factory and User relation override"
```

---

### Task 3: Centralize type strings in notification classes + fix `broadcastType`

**Files:**
- Modify: `app/Notifications/NewMessage.php`
- Modify: `app/Notifications/NewDiscussionQuestion.php`
- Modify: `app/Notifications/NewDiscussionReply.php`
- Test: `tests/Feature/Notifications/NotificationBroadcastTypeTest.php`

**Interfaces:**
- Consumes: `NotificationType` (Task 1).
- Produces: each class's `toArray()['type']` equals its `NotificationType` value; each class has `broadcastType(): string` returning the same value.

> Why: broadcast payloads overwrite `data['type']` with the class name unless `broadcastType()` is overridden. Today only `NewMessage` overrides it; `NewDiscussionQuestion` and `NewDiscussionReply` are clobbered on the broadcast channel, so live and stored types disagree.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\NotificationType;
use App\Notifications\NewDiscussionQuestion;
use App\Notifications\NewDiscussionReply;
use App\Notifications\NewMessage;

it('exposes a stable broadcastType matching the stored type', function (string $class, NotificationType $type) {
    $reflection = new ReflectionClass($class);
    $instance = $reflection->newInstanceWithoutConstructor();

    expect($instance->broadcastType())->toBe($type->value);
})->with([
    [NewMessage::class, NotificationType::NewMessage],
    [NewDiscussionQuestion::class, NotificationType::NewQuestion],
    [NewDiscussionReply::class, NotificationType::NewReply],
]);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=NotificationBroadcastTypeTest`
Expected: FAIL — `NewDiscussionQuestion`/`NewDiscussionReply` have no `broadcastType()` method (Error: Call to undefined method).

- [ ] **Step 3: Update `NewMessage`**

In `app/Notifications/NewMessage.php`, add the import:

```php
use App\Enums\NotificationType;
```

Replace the literal in `toArray()`:

```php
'type' => NotificationType::NewMessage->value,
```

Replace the `broadcastType()` body:

```php
public function broadcastType(): string
{
    return NotificationType::NewMessage->value;
}
```

- [ ] **Step 4: Update `NewDiscussionQuestion`**

In `app/Notifications/NewDiscussionQuestion.php`, add the import:

```php
use App\Enums\NotificationType;
```

Replace the literal in `toArray()`:

```php
'type' => NotificationType::NewQuestion->value,
```

Add a `broadcastType()` method (next to `toBroadcast()`):

```php
public function broadcastType(): string
{
    return NotificationType::NewQuestion->value;
}
```

- [ ] **Step 5: Update `NewDiscussionReply`**

In `app/Notifications/NewDiscussionReply.php`, add the import:

```php
use App\Enums\NotificationType;
```

Replace the literal in `toArray()`:

```php
'type' => NotificationType::NewReply->value,
```

Add a `broadcastType()` method (next to `toBroadcast()`):

```php
public function broadcastType(): string
{
    return NotificationType::NewReply->value;
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=NotificationBroadcastTypeTest`
Expected: PASS.

- [ ] **Step 7: Run the existing notification/messaging tests to catch regressions**

Run: `php artisan test --compact --filter=Notification`
Expected: PASS (no type-string regressions).

- [ ] **Step 8: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/NewMessage.php app/Notifications/NewDiscussionQuestion.php app/Notifications/NewDiscussionReply.php tests/Feature/Notifications/NotificationBroadcastTypeTest.php
git commit -m "refactor: centralize notification type strings in enum, fix broadcastType"
```

---

### Task 4: Controller filtering, `filterOptions()`, pagination

**Files:**
- Modify: `app/Http/Controllers/NotificationController.php`
- Test: `tests/Feature/Notifications/NotificationFilterTest.php`

**Interfaces:**
- Consumes: `NotificationType` (Task 1); `User::notifications()->withFilters()` (Task 2).
- Produces: `notifications.index` renders `Notifications/Index` with props `notifications` (paginator with `data`/`total`), `filters` (array), `filterOptions` (3 descriptors: keys `type`, `read`, `created_at`).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('filters notifications by type', function () {
    $user = User::factory()->create();
    $question = Notification::factory()->for($user, 'notifiable')
        ->ofType(NotificationType::NewQuestion)->create();
    Notification::factory()->for($user, 'notifiable')
        ->ofType(NotificationType::NewMessage)->create();

    actingAs($user)->get(route('notifications.index', ['filters' => ['type' => ['new_question']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.total', 1)
            ->where('notifications.data.0.id', $question->id));
});

it('filters notifications by unread state', function () {
    $user = User::factory()->create();
    $unread = Notification::factory()->for($user, 'notifiable')->unread()->create();
    Notification::factory()->for($user, 'notifiable')->read()->create();

    actingAs($user)->get(route('notifications.index', ['filters' => ['read' => 'unread']]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.total', 1)
            ->where('notifications.data.0.id', $unread->id));
});

it('paginates notifications and exposes type, read, and date filter options', function () {
    $user = User::factory()->create();

    actingAs($user)->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications.data')
            ->has('notifications.total')
            ->has('filterOptions', 3)
            ->where('filterOptions.0.key', 'type')
            ->where('filterOptions.1.key', 'read')
            ->where('filterOptions.2.key', 'created_at'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=NotificationFilterTest`
Expected: FAIL — `filterOptions` prop missing / `notifications` is a flat array, not a paginator.

- [ ] **Step 3: Rewrite the controller**

Replace `app/Http/Controllers/NotificationController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Notifications shown per page.
     */
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()
            ->withFilters($request->input('filters'))
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn ($notification): array => [
                'id' => $notification->id,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
                ...$notification->data,
            ]);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filters' => $request->input('filters', []),
            'filterOptions' => $this->filterOptions(),
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

    /**
     * Declarative filter controls for the notifications list.
     *
     * @return list<array<string, mixed>>
     */
    private function filterOptions(): array
    {
        return [
            [
                'key' => 'type',
                'label' => 'Type',
                'type' => 'select',
                'multiple' => true,
                'options' => NotificationType::options(),
            ],
            [
                'key' => 'read',
                'label' => 'Status',
                'type' => 'select',
                'options' => [
                    ['value' => 'unread', 'label' => 'Unread'],
                    ['value' => 'read', 'label' => 'Read'],
                ],
            ],
            [
                'key' => 'created_at',
                'label' => 'Received',
                'type' => 'daterange',
            ],
        ];
    }
}
```

> Note: `read()` and `readAll()` are unchanged from the original — repeated here because the whole file is replaced.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=NotificationFilterTest`
Expected: PASS.

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/NotificationController.php tests/Feature/Notifications/NotificationFilterTest.php
git commit -m "feat: filter and paginate the notifications index"
```

---

### Task 5: Frontend — `FilterBar` + pagination on the notifications page

**Files:**
- Modify: `resources/js/Pages/Notifications/Index.vue`

**Interfaces:**
- Consumes: `notifications` (paginator: `{ data, total, links, ... }`), `filters` (Object), `filterOptions` (Array) from Task 4; existing `FilterBar.vue` and `Pagination.vue` components.

- [ ] **Step 1: Update the `<script setup>` props and imports**

Replace the top of `resources/js/Pages/Notifications/Index.vue` (the imports and `defineProps`) with:

```js
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FilterBar from '@/Components/FilterBar.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    notifications: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    filterOptions: { type: Array, default: () => [] },
});
```

Leave `markAllRead`, `openNotification`, and `notificationLabel` exactly as they are.

- [ ] **Step 2: Update the template to add `FilterBar`, iterate `notifications.data`, and add `Pagination`**

Replace the `<template>` block with:

```html
<template>
    <AuthenticatedLayout>
        <Head title="Notifications" />
        <div class="mx-auto max-w-2xl p-4">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">Notifications</h1>
                <button class="text-sm text-amber-600" @click="markAllRead">Mark all read</button>
            </div>

            <div class="mb-4">
                <FilterBar :filters="filters" :filter-options="filterOptions" :searchable="false" />
            </div>

            <ul class="divide-y">
                <li v-for="n in notifications.data" :key="n.id" class="cursor-pointer py-3" :class="{ 'font-semibold': !n.read_at }" @click="openNotification(n)">
                    <p class="text-sm">{{ n.actor_name ?? n.sender_name }} · {{ notificationLabel(n.type) }}</p>
                    <p class="text-sm text-gray-500">{{ n.excerpt }}</p>
                </li>
                <li v-if="notifications.total === 0" class="py-6 text-center text-gray-500">No notifications match these filters.</li>
            </ul>

            <Pagination :paginator="notifications" />
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 3: Build the frontend**

Run: `npm run build`
Expected: build succeeds with no errors referencing `Notifications/Index.vue`.

- [ ] **Step 4: Smoke-test the page renders (Pest browser test, if the suite has a smoke pattern)**

Run: `php artisan test --compact --filter=NotificationFilterTest`
Expected: PASS (server-side props unchanged; this confirms nothing broke). Manual check: visit `/notifications`, confirm the filter controls appear and selecting a type/status/date narrows the list and updates the URL query string.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Notifications/Index.vue
git commit -m "feat: add filter bar and pagination to notifications page"
```

---

### Task 6: Full-suite regression check

- [ ] **Step 1: Run the full notification-related suite**

Run: `php artisan test --compact --filter=Notification`
Expected: all PASS.

- [ ] **Step 2: Run the broader filter suite to confirm no cross-page regressions**

Run: `php artisan test --compact --filter=Filter`
Expected: all PASS (Course, Enrollment, Catalog, Notification filter tests green).

- [ ] **Step 3: Final format check**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no outstanding style issues.

---

## Self-Review

**Spec coverage:**
- `NotificationType` enum → Task 1. ✓
- Custom `Notification` model + `Filterable` + `User::notifications()` override → Task 2. ✓
- `type`/`read`/`created_at` filters via `ExactFilter('data->type')` / `CallbackFilter` / `RangeFilter(asDate)` → Task 2 (model), exercised in Task 4. ✓
- Controller `withFilters` + `filterOptions()` + pagination (replaces latest-50) → Task 4. ✓
- Notification classes use enum values + `broadcastType()` fix → Task 3. ✓
- `Pages/Notifications/Index.vue` FilterBar (`:searchable="false"`) + `notifications.data` + Pagination → Task 5. ✓
- `NotificationFilterTest` mirroring `EnrollmentFilterTest` → Task 4 (plus model-level Task 2, broadcast Task 3). ✓
- Out of scope (search box, type-management UI, dispatch changes, shared FilterRequest, filterOptions() centralization) → not planned. ✓

**Placeholder scan:** No TBD/TODO; every code step shows full code. ✓

**Type consistency:** `NotificationType::options()` shape `{value,label}` matches `filterOptions` select consumption; `withFilters(?array)` matches the `Filterable` scope signature; factory states `ofType/read/unread` are used consistently in Tasks 2 and 4; `notifications()` returns `MorphMany` of the custom model used by controller and `read()/readAll()`. ✓
