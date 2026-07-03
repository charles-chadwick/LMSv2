# Lesson Viewing + Progress Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let enrolled users (and instructor/admin previewers) read a course's lessons, navigate prev/next, and mark lessons complete to drive `progress_percentage` via the existing `CompleteLesson` action.

**Architecture:** Thin controllers authorize a new `learn` ability on `CoursePolicy` and delegate; marking complete calls `CompleteLesson`. Learning order and completion state are computed server-side from the already-ordered `modules.flatMap(lessons)` and passed as explicit Inertia props. Lessons bind by slug.

**Tech Stack:** Laravel 13, Inertia Laravel v3, Vue 3, Inertia Vue v3, Tailwind v4, Spatie Permission, Loris Leiva Actions, Pest 4.

## Global Constraints

- PHP 8.4. Constructor property promotion; explicit return types and param type hints on every method.
- Naming: variables snake_case, methods camelCase, classes TitleCase. Enum keys TitleCase.
- Curly braces on all control structures, even single-line bodies.
- No new Composer/npm dependencies.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes, before each commit.
- Pest feature tests; run `php artisan test --compact`; output must be pristine.
- **Course/lesson tests must seed `RolePermissionSeeder`** in a `beforeEach` — `RefreshDatabase` does not seed.
- Prefer named routes and the `route()` helper. `Course` binds by slug; `Lesson` will bind by slug (added in Task 1).
- Enum values (TitleCase strings): `EnrollmentStatus` = Active/Completed/Dropped.
- Student-facing routes live inside the existing `auth` → `verified` nested group in `routes/web.php`.
- Vue components single root element; `@` alias (resolves to `resources/js`); match sibling pages.
- Lesson `content` is rendered as plain, whitespace-preserved text (no HTML injection).
- Commit message trailer: `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.

## File Structure

**New — PHP:**
- `app/Http/Controllers/CompleteLessonController.php` — invokable mark-complete endpoint
- `app/Http/Controllers/LessonController.php` — lesson view (show)

**New — Vue:**
- `resources/js/Pages/Lessons/Show.vue` — lesson reader (content, progress, mark-complete, prev/next)

**New — Tests:**
- `tests/Feature/Lessons/LearnPolicyTest.php` — `learn` ability
- `tests/Feature/Lessons/LessonCompletionTest.php` — mark-complete endpoint
- `tests/Feature/Lessons/LessonViewingTest.php` — lesson view

**Modified:**
- `app/Models/Lesson.php` — add `getRouteKeyName`
- `app/Policies/CoursePolicy.php` — add `learn`
- `app/Http/Controllers/CourseCatalogController.php` — extend `show` payload for the interactive syllabus
- `routes/web.php` — lesson routes
- `resources/js/Pages/Catalog/Show.vue` — interactive syllabus + "Continue learning"
- `tests/Feature/Catalog/CourseCatalogTest.php` — assertions for the extended `show` payload

---

### Task 1: `learn` ability + lesson slug binding

**Files:**
- Modify: `app/Models/Lesson.php`
- Modify: `app/Policies/CoursePolicy.php`
- Test: `tests/Feature/Lessons/LearnPolicyTest.php`

**Interfaces:**
- Produces: `CoursePolicy::learn(User, Course): bool` (enrolled OR course instructor; admins via `Gate::before`). `Lesson::getRouteKeyName()` returns `'slug'` so lesson routes bind by slug (relied on by Tasks 2-4).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Lessons/LearnPolicyTest.php`:

```php
<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('an enrolled user can learn a course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    expect($user->can('learn', $course))->toBeTrue();
});

test('the course instructor can learn without enrolling', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();

    expect($instructor->can('learn', $course))->toBeTrue();
});

test('an admin can learn any course', function (): void {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();

    expect($admin->can('learn', $course))->toBeTrue();
});

test('an unrelated user cannot learn a course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    expect($user->can('learn', $course))->toBeFalse();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=LearnPolicyTest`
Expected: FAIL — `learn` ability undefined.

- [ ] **Step 3: Add `getRouteKeyName` to Lesson**

In `app/Models/Lesson.php`, add the method (after the `casts` method or near the relations — match the class style):

```php
public function getRouteKeyName(): string
{
    return 'slug';
}
```

- [ ] **Step 4: Add `learn` to CoursePolicy**

In `app/Policies/CoursePolicy.php`, add after the `enroll` method:

```php
public function learn(User $user, Course $course): bool
{
    return $user->enrollments()->where('course_id', $course->id)->exists()
        || $course->instructor_id === $user->id;
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=LearnPolicyTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Lesson.php app/Policies/CoursePolicy.php tests/Feature/Lessons/LearnPolicyTest.php
git commit -m "Add learn ability and lesson slug binding

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Mark-complete endpoint

**Files:**
- Create: `app/Http/Controllers/CompleteLessonController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Lessons/LessonCompletionTest.php`

**Interfaces:**
- Consumes: `CoursePolicy::learn` (Task 1); `Lesson` slug binding (Task 1); `CompleteLesson::run(Enrollment, Lesson): Enrollment` (existing action).
- Produces: named route `lessons.complete` (POST `learn/{course}/{lesson}/complete`); `CompleteLessonController::__invoke(Request, Course, Lesson): RedirectResponse`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Lessons/LessonCompletionTest.php`:

```php
<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('an enrolled user can mark a lesson complete and progress updates', function (): void {
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

    $this->actingAs($user)->post(route('lessons.complete', [$course, $lesson_a]))->assertRedirect();

    expect(LessonCompletion::where(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson_a->id])->exists())->toBeTrue()
        ->and($enrollment->fresh()->progress_percentage)->toBe(50);
});

test('marking a lesson complete twice is idempotent', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();
    $enrollment = $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)->post(route('lessons.complete', [$course, $lesson]));
    $this->actingAs($user)->post(route('lessons.complete', [$course, $lesson]));

    expect(LessonCompletion::where(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id])->count())->toBe(1)
        ->and($enrollment->fresh()->progress_percentage)->toBe(100);
});

test('a previewing instructor cannot mark a lesson complete', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($instructor)->post(route('lessons.complete', [$course, $lesson]))->assertForbidden();

    expect(LessonCompletion::count())->toBe(0);
});

test('an unrelated user cannot mark a lesson complete', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($user)->post(route('lessons.complete', [$course, $lesson]))->assertForbidden();
});

test('marking a lesson that belongs to another course 404s', function (): void {
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

    $this->actingAs($user)->post(route('lessons.complete', [$course, $foreign_lesson]))->assertNotFound();
});

test('a guest cannot mark a lesson complete', function (): void {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->post(route('lessons.complete', [$course, $lesson]))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=LessonCompletionTest`
Expected: FAIL — route `lessons.complete` not defined.

- [ ] **Step 3: Create CompleteLessonController**

Create `app/Http/Controllers/CompleteLessonController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Actions\CompleteLesson;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompleteLessonController extends Controller
{
    public function __invoke(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        abort_unless($lesson->module->course_id === $course->id, 404);

        $this->authorize('learn', $course);

        $enrollment = $request->user()->enrollments()->where('course_id', $course->id)->first();

        abort_unless($enrollment !== null, 403);

        CompleteLesson::run($enrollment, $lesson);

        return back()->with('status', 'Lesson completed.');
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\CompleteLessonController;
```

Inside the existing `Route::middleware('verified')->group(...)` block, add:

```php
Route::post('learn/{course}/{lesson}/complete', CompleteLessonController::class)->name('lessons.complete');
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=LessonCompletionTest`
Expected: PASS (6 tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/CompleteLessonController.php routes/web.php tests/Feature/Lessons/LessonCompletionTest.php
git commit -m "Add lesson mark-complete endpoint

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Lesson view page

**Files:**
- Create: `app/Http/Controllers/LessonController.php`
- Modify: `routes/web.php`
- Create: `resources/js/Pages/Lessons/Show.vue`
- Test: `tests/Feature/Lessons/LessonViewingTest.php`

**Interfaces:**
- Consumes: `CoursePolicy::learn` (Task 1); `lessons.complete` route (Task 2) for the mark-complete button.
- Produces: named route `lessons.show` (GET `learn/{course}/{lesson}`). `show` passes `course` (`{title, slug}`), `lesson` (`{id, slug, title, content}`), `prev`/`next` (`{title, slug}|null`), `is_complete` (bool), `can_complete` (bool), `progress_percentage` (int).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Lessons/LessonViewingTest.php`:

```php
<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('an enrolled user can view a lesson', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create(['title' => 'Intro']);
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('lessons.show', [$course, $lesson]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Lessons/Show')
            ->where('lesson.title', 'Intro')
            ->where('is_complete', false)
            ->where('can_complete', true)
        );
});

test('prev and next are computed across module boundaries', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module_one = Module::factory()->for($course)->create(['position' => 0]);
    $module_two = Module::factory()->for($course)->create(['position' => 1]);
    $lesson_a = Lesson::factory()->for($module_one)->create(['position' => 0]);
    $lesson_b = Lesson::factory()->for($module_two)->create(['position' => 0]);
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('lessons.show', [$course, $lesson_a]))
        ->assertInertia(fn ($page) => $page
            ->where('prev', null)
            ->where('next.slug', $lesson_b->slug)
        );

    $this->actingAs($user)
        ->get(route('lessons.show', [$course, $lesson_b]))
        ->assertInertia(fn ($page) => $page
            ->where('prev.slug', $lesson_a->slug)
            ->where('next', null)
        );
});

test('a lesson from another course 404s under this course', function (): void {
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

test('a non-enrolled user cannot view a lesson', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson]))->assertForbidden();
});

test('an instructor can preview a lesson without enrolling', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($instructor)
        ->get(route('lessons.show', [$course, $lesson]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can_complete', false));
});

test('a guest is redirected to login from a lesson', function (): void {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->get(route('lessons.show', [$course, $lesson]))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=LessonViewingTest`
Expected: FAIL — route `lessons.show` not defined.

- [ ] **Step 3: Create LessonController**

Create `app/Http/Controllers/LessonController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    public function show(Request $request, Course $course, Lesson $lesson): Response
    {
        abort_unless($lesson->module->course_id === $course->id, 404);

        $this->authorize('learn', $course);

        $course->load('modules.lessons');
        $ordered_lessons = $course->modules->flatMap(fn ($module) => $module->lessons)->values();

        $index = $ordered_lessons->search(fn ($item): bool => $item->id === $lesson->id);
        $prev = $index > 0 ? $ordered_lessons[$index - 1] : null;
        $next = $index < $ordered_lessons->count() - 1 ? $ordered_lessons[$index + 1] : null;

        $enrollment = $request->user()->enrollments()->where('course_id', $course->id)->first();
        $completed_lesson_ids = $enrollment
            ? $enrollment->lessonCompletions()->pluck('lesson_id')->all()
            : [];

        return Inertia::render('Lessons/Show', [
            'course' => [
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'lesson' => [
                'id' => $lesson->id,
                'slug' => $lesson->slug,
                'title' => $lesson->title,
                'content' => $lesson->content,
            ],
            'prev' => $prev ? ['title' => $prev->title, 'slug' => $prev->slug] : null,
            'next' => $next ? ['title' => $next->title, 'slug' => $next->slug] : null,
            'is_complete' => in_array($lesson->id, $completed_lesson_ids, true),
            'can_complete' => $enrollment !== null,
            'progress_percentage' => $enrollment ? $enrollment->progress_percentage : 0,
        ]);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\LessonController;
```

Inside the `Route::middleware('verified')->group(...)` block, add (next to the `lessons.complete` route):

```php
Route::get('learn/{course}/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
```

- [ ] **Step 5: Create Lessons/Show.vue**

Create `resources/js/Pages/Lessons/Show.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    course: { type: Object, required: true },
    lesson: { type: Object, required: true },
    prev: { type: Object, default: null },
    next: { type: Object, default: null },
    is_complete: { type: Boolean, required: true },
    can_complete: { type: Boolean, required: true },
    progress_percentage: { type: Number, required: true },
});

const completing = ref(false);

const markComplete = () => {
    completing.value = true;
    router.post(route('lessons.complete', [props.course.slug, props.lesson.slug]), {}, {
        preserveScroll: true,
        onFinish: () => {
            completing.value = false;
        },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="lesson.title" />

        <div class="mb-4">
            <Link :href="route('catalog.show', course.slug)" class="text-sm text-blue-600 hover:underline">
                &larr; {{ course.title }}
            </Link>
        </div>

        <div class="mb-6">
            <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                <span>Progress</span>
                <span>{{ progress_percentage }}%</span>
            </div>
            <div class="h-2 w-full rounded-full bg-gray-200">
                <div class="h-2 rounded-full bg-green-500" :style="{ width: progress_percentage + '%' }" />
            </div>
        </div>

        <h1 class="mb-4 text-2xl font-semibold">{{ lesson.title }}</h1>

        <div class="mb-8 whitespace-pre-line text-gray-700">{{ lesson.content }}</div>

        <div v-if="can_complete" class="mb-8">
            <span v-if="is_complete" class="rounded bg-green-100 px-4 py-2 text-sm font-medium text-green-700">
                Completed &check;
            </span>
            <button
                v-else
                type="button"
                :disabled="completing"
                class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                @click="markComplete"
            >
                Mark as complete
            </button>
        </div>

        <div class="flex items-center justify-between border-t pt-4 text-sm">
            <Link
                v-if="prev"
                :href="route('lessons.show', [course.slug, prev.slug])"
                class="text-blue-600 hover:underline"
            >
                &larr; {{ prev.title }}
            </Link>
            <span v-else />
            <Link
                v-if="next"
                :href="route('lessons.show', [course.slug, next.slug])"
                class="text-blue-600 hover:underline"
            >
                {{ next.title }} &rarr;
            </Link>
            <span v-else />
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=LessonViewingTest`
Expected: PASS (6 tests).

- [ ] **Step 7: Build the frontend**

Run: `npm run build`
Expected: build succeeds; `Lessons/Show` compiles with no unresolved imports.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/LessonController.php routes/web.php resources/js/Pages/Lessons tests/Feature/Lessons/LessonViewingTest.php
git commit -m "Add lesson view page with prev/next and progress

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Interactive syllabus on the catalog detail

**Files:**
- Modify: `app/Http/Controllers/CourseCatalogController.php`
- Modify: `resources/js/Pages/Catalog/Show.vue`
- Test: `tests/Feature/Catalog/CourseCatalogTest.php`

**Interfaces:**
- Consumes: `CoursePolicy::learn` (Task 1); `lessons.show` route (Task 3).
- Produces: extended `catalog.show` payload — `course.modules[].lessons[]` gains `id` + `slug`; new top-level props `can_learn` (bool), `completed_lesson_ids` (int[]), `first_incomplete_lesson_slug` (string|null).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Catalog/CourseCatalogTest.php` (the file already imports `Course`, `Lesson`, `Module`, `User`, `CourseStatus` and has the `beforeEach`; add `use App\Enums\EnrollmentStatus;` at the top if not already present):

```php
test('the course detail exposes learning data for an enrolled user', function (): void {
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
    $enrollment->lessonCompletions()->create(['lesson_id' => $lesson_a->id, 'completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('catalog.show', $course))
        ->assertInertia(fn ($page) => $page
            ->where('can_learn', true)
            ->where('course.modules.0.lessons.0.slug', $lesson_a->slug)
            ->where('completed_lesson_ids', [$lesson_a->id])
            ->where('first_incomplete_lesson_slug', $lesson_b->slug)
        );
});

test('the course detail marks can_learn false for a non-enrolled student', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    Module::factory()->for($course)->create();

    $this->actingAs($user)
        ->get(route('catalog.show', $course))
        ->assertInertia(fn ($page) => $page
            ->where('can_learn', false)
            ->where('completed_lesson_ids', [])
            ->where('first_incomplete_lesson_slug', null)
        );
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=CourseCatalogTest`
Expected: FAIL — the two new tests fail (`can_learn` etc. missing from payload).

- [ ] **Step 3: Extend CourseCatalogController::show**

In `app/Http/Controllers/CourseCatalogController.php`, replace the `show` method with:

```php
public function show(Request $request, Course $course): Response
{
    abort_unless($course->status === CourseStatus::Published, 404);

    $course->load(['instructor:id,name', 'modules.lessons']);

    $user = $request->user();
    $enrollment = $user->enrollments()->where('course_id', $course->id)->first();
    $completed_lesson_ids = $enrollment
        ? $enrollment->lessonCompletions()->pluck('lesson_id')->all()
        : [];

    $ordered_lessons = $course->modules->flatMap(fn ($module) => $module->lessons)->values();
    $first_incomplete = $ordered_lessons->first(
        fn ($lesson): bool => ! in_array($lesson->id, $completed_lesson_ids, true),
    );

    return Inertia::render('Catalog/Show', [
        'course' => [
            'title' => $course->title,
            'slug' => $course->slug,
            'summary' => $course->summary,
            'description' => $course->description,
            'level' => $course->level,
            'instructor' => $course->instructor->name,
            'modules' => $course->modules->map(fn ($module): array => [
                'title' => $module->title,
                'lessons' => $module->lessons->map(fn ($lesson): array => [
                    'id' => $lesson->id,
                    'slug' => $lesson->slug,
                    'title' => $lesson->title,
                ])->values(),
            ])->values(),
        ],
        'is_enrolled' => $enrollment !== null,
        'can_learn' => $user->can('learn', $course),
        'completed_lesson_ids' => $completed_lesson_ids,
        'first_incomplete_lesson_slug' => $first_incomplete?->slug,
    ]);
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=CourseCatalogTest`
Expected: PASS (all prior catalog tests plus the 2 new ones — the existing "syllabus in position order" test still passes since `title` remains and `is_enrolled` is unchanged).

- [ ] **Step 5: Update Catalog/Show.vue for the interactive syllabus**

Replace `resources/js/Pages/Catalog/Show.vue` with (adds `can_learn`, `completed_lesson_ids`, `first_incomplete_lesson_slug` props; makes syllabus lessons links with ✓ and a "Continue learning" button when `can_learn`; keeps the enroll button and read-only fallback):

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    course: { type: Object, required: true },
    is_enrolled: { type: Boolean, required: true },
    can_learn: { type: Boolean, default: false },
    completed_lesson_ids: { type: Array, default: () => [] },
    first_incomplete_lesson_slug: { type: String, default: null },
});

const enrolling = ref(false);

const enroll = () => {
    enrolling.value = true;
    router.post(route('courses.enroll', props.course.slug), {}, {
        preserveScroll: true,
        onFinish: () => {
            enrolling.value = false;
        },
    });
};

const isComplete = (lesson) => props.completed_lesson_ids.includes(lesson.id);
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="course.title" />

        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ course.title }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ course.instructor }} · <span class="capitalize">{{ course.level }}</span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Link
                    v-if="can_learn && first_incomplete_lesson_slug"
                    :href="route('lessons.show', [course.slug, first_incomplete_lesson_slug])"
                    class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white"
                >
                    Continue learning
                </Link>
                <span v-if="is_enrolled" class="rounded bg-green-100 px-4 py-2 text-sm font-medium text-green-700">
                    Enrolled
                </span>
                <button
                    v-else
                    type="button"
                    :disabled="enrolling"
                    class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                    @click="enroll"
                >
                    Enroll
                </button>
            </div>
        </div>

        <p v-if="course.summary" class="mb-4 text-gray-700">{{ course.summary }}</p>
        <p v-if="course.description" class="mb-8 whitespace-pre-line text-gray-600">{{ course.description }}</p>

        <h2 class="mb-3 text-lg font-semibold">Syllabus</h2>
        <div v-if="course.modules.length === 0" class="text-sm text-gray-500">
            No modules yet.
        </div>
        <ol v-else class="space-y-4">
            <li v-for="(module, index) in course.modules" :key="index" class="rounded border p-4">
                <h3 class="font-medium">{{ module.title }}</h3>
                <ul class="mt-2 space-y-1 pl-1 text-sm text-gray-600">
                    <li v-for="lesson in module.lessons" :key="lesson.id" class="flex items-center gap-2">
                        <span v-if="isComplete(lesson)" class="text-green-600">&check;</span>
                        <span v-else class="text-gray-300">&bull;</span>
                        <Link
                            v-if="can_learn"
                            :href="route('lessons.show', [course.slug, lesson.slug])"
                            class="text-blue-600 hover:underline"
                        >
                            {{ lesson.title }}
                        </Link>
                        <span v-else>{{ lesson.title }}</span>
                    </li>
                </ul>
            </li>
        </ol>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 6: Build the frontend**

Run: `npm run build`
Expected: build succeeds; `Catalog/Show` compiles.

- [ ] **Step 7: Run the full test suite**

Run: `php artisan test --compact`
Expected: PASS — all prior tests plus the new lesson and catalog tests.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/CourseCatalogController.php resources/js/Pages/Catalog/Show.vue tests/Feature/Catalog/CourseCatalogTest.php
git commit -m "Make course syllabus interactive with lesson links and progress

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review Notes

- **Spec coverage:** `learn` policy + lesson slug binding (Task 1); mark-complete endpoint with enrollment-required 403 + foreign-lesson 404 + idempotency (Task 2); lesson view with prev/next across modules + instructor preview + non-enrolled 403 (Task 3); interactive syllabus payload + Vue (Task 4). Every spec test maps to a task.
- **No throwaway stubs:** the only new Vue page (`Lessons/Show.vue`) is built in the same task (3) as the controller whose test asserts `component('Lessons/Show')`.
- **Route order:** mark-complete endpoint (Task 2) lands before the view page (Task 3), so `Lessons/Show.vue`'s `route('lessons.complete', ...)` resolves to a defined route; `lessons.show` and `lessons.complete` differ in method+path (no collision).
- **Ordering source:** learning order is derived from `modules.flatMap(lessons)` (both relations already `orderBy('position')`), avoiding hasManyThrough join-ordering. The one-line `flatMap` expression appears in both `LessonController` and `CourseCatalogController` — a trivial expression, not a duplicated logic block; extracting it is unwarranted (YAGNI).
- **Type consistency:** lesson payload includes `slug` (needed by `Lessons/Show.vue`'s mark-complete and by prev/next links); `completed_lesson_ids` is `int[]` in both controllers and consumed as such by `Catalog/Show.vue`'s `includes`. Route names `lessons.show`/`lessons.complete` produced in Tasks 3/2 and consumed in Task 4/3.
- **Existing-test safety:** Task 4 adds `id`/`slug` to catalog lesson payloads but keeps `title`, so the prior slice's "syllabus in position order" test still passes; `is_enrolled` semantics unchanged (`$enrollment !== null` ≡ the prior `exists()`).
- **`@` alias + slug binding:** proven in prior slices.
```
