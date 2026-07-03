# Auto-complete Lessons on View Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mark a lesson complete automatically when an enrolled student views it, and remove the manual "Mark as Complete" button and its now-unused POST endpoint.

**Architecture:** Completion becomes a side effect of the `GET lessons.show` request. `LessonController@show` calls the existing idempotent `CompleteLesson` action when (and only when) the viewer has an enrollment for the course. The `lessons.complete` POST route + `CompleteLessonController` are deleted. The Vue page drops the button and its handler.

**Tech Stack:** Laravel 13 (PHP 8.4), Inertia v3, Vue 3, Pest v4. Lesson completion is tracked by `LessonCompletion` (unique per `enrollment_id` + `lesson_id`); the `CompleteLesson` action (`Lorisleiva\Actions`) upserts the row and recomputes `Enrollment::progress_percentage`.

## Global Constraints

- Naming (per CLAUDE.md): `snake_case` variables, `camelCase` methods, `TitleCase` classes.
- PHP: curly braces on all control structures; explicit return types + param type hints.
- Authorization is unchanged: `authorize('learn', $course)` still gates viewing; a viewer with no enrollment (previewing instructor) or a dropped student must never produce a `LessonCompletion`.
- Run `vendor/bin/pint --dirty --format agent` after PHP edits.
- Run tests with `php artisan test --compact --filter=...`.

---

### Task 1: Auto-complete on view in LessonController

**Files:**
- Modify: `app/Http/Controllers/LessonController.php:26` (after the `$enrollment` lookup)
- Rewrite: `tests/Feature/Lessons/LessonCompletionTest.php`

**Interfaces:**
- Consumes: `CompleteLesson::run(Enrollment $enrollment, Lesson $lesson): Enrollment` (existing, unchanged).
- Produces: viewing `lessons.show` as an enrolled student creates a `LessonCompletion` and updates `Enrollment::progress_percentage`.

- [ ] **Step 1: Rewrite the test file to assert on-view behavior**

Replace the entire contents of `tests/Feature/Lessons/LessonCompletionTest.php` with:

```php
<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('viewing a lesson marks it complete and updates progress', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create(['position' => 0]);
    $lesson_a = Lesson::factory()->for($module)->create(['position' => 0]);
    Lesson::factory()->for($module)->create(['position' => 1]);
    $enrollment = $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson_a]))->assertOk();

    expect(LessonCompletion::where(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson_a->id])->exists())->toBeTrue()
        ->and($enrollment->fresh()->progress_percentage)->toBe(50);
});

test('viewing a lesson twice is idempotent', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();
    $enrollment = $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson]));
    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson]));

    expect(LessonCompletion::where(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id])->count())->toBe(1)
        ->and($enrollment->fresh()->progress_percentage)->toBe(100);
});

test('viewing every lesson drives progress to 100 percent', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create(['position' => 0]);
    $lesson_a = Lesson::factory()->for($module)->create(['position' => 0]);
    $lesson_b = Lesson::factory()->for($module)->create(['position' => 1]);
    $enrollment = $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson_a]));
    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson_b]));

    expect($enrollment->fresh()->progress_percentage)->toBe(100);
});

test('a previewing instructor does not generate a completion', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($instructor)->get(route('lessons.show', [$course, $lesson]))->assertOk();

    expect(LessonCompletion::count())->toBe(0);
});

test('an unrelated user cannot view a lesson and creates no completion', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson]))->assertForbidden();

    expect(LessonCompletion::count())->toBe(0);
});

test('a dropped student cannot view a lesson and creates no completion', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();
    Enrollment::factory()->for($user, 'student')->for($course)->dropped()->create();

    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson]))->assertForbidden();

    expect(LessonCompletion::count())->toBe(0);
});

test('viewing a lesson that belongs to another course 404s', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);
    $other_course = Course::factory()->published()->create();
    $other_module = Module::factory()->for($other_course)->create();
    $foreign_lesson = Lesson::factory()->for($other_module)->create();

    $this->actingAs($user)->get(route('lessons.show', [$course, $foreign_lesson]))->assertNotFound();
});

test('a guest is redirected to login', function (): void {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->get(route('lessons.show', [$course, $lesson]))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=LessonCompletion`
Expected: FAIL — the "marks it complete" / "idempotent" / "100 percent" tests fail because viewing does not yet create completions. (The forbidden/404/guest tests may already pass.)

- [ ] **Step 3: Call CompleteLesson from the show handler**

In `app/Http/Controllers/LessonController.php`, add the import near the other `use` statements at the top:

```php
use App\Actions\CompleteLesson;
```

Then, immediately after the existing `$enrollment` assignment (line 26), before `$completed_lesson_ids`, insert:

```php
        if ($enrollment !== null) {
            CompleteLesson::run($enrollment, $lesson);
        }
```

The surrounding block now reads:

```php
        $enrollment = $request->user()->enrollments()->where('course_id', $course->id)->first();

        if ($enrollment !== null) {
            CompleteLesson::run($enrollment, $lesson);
        }

        $completed_lesson_ids = $enrollment
            ? $enrollment->lessonCompletions()->pluck('lesson_id')->all()
            : [];
```

Note: `$completed_lesson_ids` is computed *after* the completion call, so `is_complete` and `progress_percentage` reflect the just-viewed lesson in the same response.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=LessonCompletion`
Expected: PASS (all 8 tests).

- [ ] **Step 5: Format**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no style errors.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/LessonController.php tests/Feature/Lessons/LessonCompletionTest.php
git commit -m "Auto-complete lessons on view"
```

---

### Task 2: Remove the manual Mark-as-Complete button

**Files:**
- Modify: `resources/js/Pages/Lessons/Show.vue`

**Interfaces:**
- Consumes: `is_complete` / `progress_percentage` props (still sent by the controller). Drops the `router.post` to `lessons.complete`.
- Produces: a page with no manual completion control; badge + progress bar + Prev/Next links remain.

- [ ] **Step 1: Simplify the `<script setup>` block**

Replace lines 1-27 of `resources/js/Pages/Lessons/Show.vue` (from `import` through the end of `markComplete`) with:

```js
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    course: { type: Object, required: true },
    lesson: { type: Object, required: true },
    prev: { type: Object, default: null },
    next: { type: Object, default: null },
    is_complete: { type: Boolean, required: true },
    progress_percentage: { type: Number, required: true },
});
</script>
```

This drops the `router` and `ref` imports, the `completing` ref, the `markComplete` handler, and the unused `can_complete` prop.

- [ ] **Step 2: Replace the completion block in the template**

In the same file, replace the whole `<div v-if="can_complete" class="mb-8">…</div>` block (originally lines 53-66) with a badge-only block:

```html
        <div v-if="is_complete" class="mb-8">
            <span class="rounded bg-green-100 px-4 py-2 text-sm font-medium text-green-700">
                Completed &check;
            </span>
        </div>
```

Leave the rest of the template (breadcrumb, progress bar, title, content, Prev/Next nav) unchanged.

- [ ] **Step 3: Build the frontend to verify it compiles**

Run: `npm run build`
Expected: build succeeds with no errors referencing `Show.vue`, `markComplete`, `router`, or `can_complete`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Lessons/Show.vue
git commit -m "Remove manual Mark as Complete button"
```

---

### Task 3: Remove the unused complete endpoint

**Files:**
- Modify: `routes/web.php` (delete import line 10 and route line 82)
- Delete: `app/Http/Controllers/CompleteLessonController.php`
- Modify: `app/Http/Controllers/LessonController.php` — remove the now-unused `can_complete` prop

**Interfaces:**
- Consumes: nothing new.
- Produces: the `lessons.complete` route name no longer exists; nothing references `CompleteLessonController`. `CompleteLesson` action stays (used by Task 1).

- [ ] **Step 1: Confirm nothing else references the route or controller**

Run: `grep -rn "lessons.complete\|CompleteLessonController" routes resources app tests`
Expected: only `routes/web.php:10`, `routes/web.php:82`, and the controller file itself. If anything else appears, stop and reassess.

- [ ] **Step 2: Delete the route and its import**

In `routes/web.php`, delete the import line:

```php
use App\Http\Controllers\CompleteLessonController;
```

and delete the route line:

```php
        Route::post('learn/{course}/{lesson}/complete', CompleteLessonController::class)->name('lessons.complete');
```

- [ ] **Step 3: Delete the controller**

```bash
git rm app/Http/Controllers/CompleteLessonController.php
```

- [ ] **Step 4: Drop the unused `can_complete` prop from the show response**

In `app/Http/Controllers/LessonController.php`, remove this line from the `Inertia::render` array (originally line 45):

```php
            'can_complete' => $enrollment !== null,
```

- [ ] **Step 5: Verify routes and the full lesson suite**

Run: `php artisan route:list --name=lessons`
Expected: `lessons.show` is present; `lessons.complete` is absent.

Run: `php artisan test --compact tests/Feature/Lessons`
Expected: PASS (LessonCompletionTest, LessonViewingTest, LearnPolicyTest all green).

- [ ] **Step 6: Format**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no style errors.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php app/Http/Controllers/LessonController.php
git commit -m "Remove unused lessons.complete endpoint"
```

---

## Self-Review

**Spec coverage:**
- On-view completion for enrolled students → Task 1. ✓
- Previewing instructor / non-enrolled / dropped create no completion → Task 1 tests (relies on `authorize('learn')` for view-blocking and null-enrollment skip). ✓
- Last lesson reaches 100% → Task 1 "viewing every lesson" test. ✓
- Remove button + handler → Task 2. ✓
- Remove `lessons.complete` route + `CompleteLessonController` → Task 3. ✓
- Keep progress bar, badge, Prev/Next → Task 2 preserves them. ✓
- Reuse `CompleteLesson` action unchanged → Task 1. ✓
- Rewrite test file, no dropped coverage → Task 1. ✓

**Placeholder scan:** No TBD/TODO; every code and command step shows exact content. ✓

**Type consistency:** `CompleteLesson::run(Enrollment, Lesson)` matches the action's `handle` signature. Props (`is_complete`, `progress_percentage`) match controller output after `can_complete` removal. `can_complete` removed in both the Vue props (Task 2) and the controller payload (Task 3). ✓
