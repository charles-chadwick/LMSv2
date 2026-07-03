# Student Course Experience Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let any authenticated, verified user browse published courses, view a course's detail and read-only syllabus, self-enroll (via the existing `EnrollStudent` action), and see their enrollments in a "My Courses" list.

**Architecture:** Thin student-facing controllers (`CourseCatalogController`, `EnrollmentController`) render Inertia pages and delegate; enrolling is gated by a new `enroll` ability on the existing `CoursePolicy` (rule: course must be Published) and performed by `EnrollStudent::run`. Each Vue page is built alongside its controller so the Inertia `ensure_pages_exist` test check is satisfied without throwaway stubs.

**Tech Stack:** Laravel 13, Inertia Laravel v3, Vue 3, Inertia Vue v3, Tailwind v4, Spatie Permission, Loris Leiva Actions, Pest 4.

## Global Constraints

- PHP 8.4. Constructor property promotion; explicit return types and param type hints on every method.
- Naming: variables snake_case, methods camelCase, classes TitleCase. Enum keys TitleCase.
- Curly braces on all control structures, even single-line bodies.
- No new Composer/npm dependencies.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes, before each commit.
- Pest feature tests; run `php artisan test --compact`; output must be pristine.
- **Course tests must seed `RolePermissionSeeder`** in a `beforeEach` — `RefreshDatabase` does not seed, so factory roles otherwise carry no permissions (admin enroll relies on the Admin role + `Gate::before`).
- Prefer named routes and the `route()` helper. Course route-model binding is by SLUG.
- Enum values (TitleCase strings): `CourseStatus` = Draft/Published/Archived; `EnrollmentStatus` = Active/Completed/Dropped.
- Student-facing routes live inside the existing `auth` → `verified` nested group in `routes/web.php`.
- Vue components must have a single root element; use the `@` alias (resolves to `resources/js`); match sibling pages (Login.vue, Dashboard.vue, Courses/*.vue).
- Commit message trailer: `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.

## File Structure

**New — PHP:**
- `app/Http/Controllers/CourseCatalogController.php` — catalog index + course detail
- `app/Http/Controllers/EnrollmentController.php` — enroll (store) + My Courses (index)

**New — Vue:**
- `resources/js/Pages/Catalog/Index.vue` — published-course list
- `resources/js/Pages/Catalog/Show.vue` — course detail + syllabus + enroll button
- `resources/js/Pages/Enrollments/Index.vue` — My Courses list

**New — Tests:**
- `tests/Feature/Enrollments/EnrollmentTest.php` — enroll policy + endpoint + My Courses
- `tests/Feature/Catalog/CourseCatalogTest.php` — catalog index + detail

**Modified:**
- `app/Policies/CoursePolicy.php` — add `enroll`
- `routes/web.php` — catalog / enroll / my-courses routes
- `resources/js/Layouts/AuthenticatedLayout.vue` — "Browse Courses" + "My Courses" nav links

---

### Task 1: Enroll ability + enroll endpoint

**Files:**
- Modify: `app/Policies/CoursePolicy.php`
- Create: `app/Http/Controllers/EnrollmentController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Enrollments/EnrollmentTest.php`

**Interfaces:**
- Consumes: `EnrollStudent::run(User $student, Course $course): Enrollment` (existing action).
- Produces: `CoursePolicy::enroll(User, Course): bool`; named route `courses.enroll` (POST); `EnrollmentController::store(Request, Course): RedirectResponse`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Enrollments/EnrollmentTest.php`:

```php
<?php

use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the enroll ability allows any user on a published course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    expect($user->can('enroll', $course))->toBeTrue();
});

test('the enroll ability denies a draft course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->create(['status' => CourseStatus::Draft]);

    expect($user->can('enroll', $course))->toBeFalse();
});

test('a student can enroll in a published course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($user)->post(route('courses.enroll', $course))->assertRedirect();

    $enrollment = Enrollment::where(['user_id' => $user->id, 'course_id' => $course->id])->sole();
    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->enrolled_at)->not->toBeNull();
});

test('an instructor can self-enroll in a published course', function (): void {
    $user = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($user)->post(route('courses.enroll', $course))->assertRedirect();

    expect(Enrollment::where(['user_id' => $user->id, 'course_id' => $course->id])->exists())->toBeTrue();
});

test('an admin can self-enroll in a published course', function (): void {
    $user = User::factory()->admin()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($user)->post(route('courses.enroll', $course))->assertRedirect();

    expect(Enrollment::where(['user_id' => $user->id, 'course_id' => $course->id])->exists())->toBeTrue();
});

test('enrolling twice does not create a duplicate enrollment', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($user)->post(route('courses.enroll', $course));
    $this->actingAs($user)->post(route('courses.enroll', $course));

    expect(Enrollment::where(['user_id' => $user->id, 'course_id' => $course->id])->count())->toBe(1);
});

test('a user cannot enroll in a draft course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->create(['status' => CourseStatus::Draft]);

    $this->actingAs($user)->post(route('courses.enroll', $course))->assertForbidden();

    expect(Enrollment::where('course_id', $course->id)->exists())->toBeFalse();
});

test('a user cannot enroll in an archived course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->create(['status' => CourseStatus::Archived]);

    $this->actingAs($user)->post(route('courses.enroll', $course))->assertForbidden();
});

test('a guest cannot enroll', function (): void {
    $course = Course::factory()->published()->create();

    $this->post(route('courses.enroll', $course))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=EnrollmentTest`
Expected: FAIL — `enroll` ability undefined / route `courses.enroll` not defined.

- [ ] **Step 3: Add the `enroll` ability to CoursePolicy**

In `app/Policies/CoursePolicy.php`, add the import and the method (place `enroll` after `archive`):

Add to the top imports:

```php
use App\Enums\CourseStatus;
```

Add the method:

```php
public function enroll(User $user, Course $course): bool
{
    return $course->status === CourseStatus::Published;
}
```

- [ ] **Step 4: Create EnrollmentController**

Create `app/Http/Controllers/EnrollmentController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Actions\EnrollStudent;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('enroll', $course);

        EnrollStudent::run($request->user(), $course);

        return back()->with('status', 'Enrolled.');
    }
}
```

- [ ] **Step 5: Add the enroll route**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\EnrollmentController;
```

Inside the existing `Route::middleware('verified')->group(...)` block (the same one holding the `courses` resource), add:

```php
Route::post('courses/{course}/enroll', [EnrollmentController::class, 'store'])->name('courses.enroll');
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=EnrollmentTest`
Expected: PASS (9 tests).

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/CoursePolicy.php app/Http/Controllers/EnrollmentController.php routes/web.php tests/Feature/Enrollments/EnrollmentTest.php
git commit -m "Add course enroll ability and endpoint

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Course catalog — controller, routes, and Vue pages

**Files:**
- Create: `app/Http/Controllers/CourseCatalogController.php`
- Modify: `routes/web.php`
- Create: `resources/js/Pages/Catalog/Index.vue`, `resources/js/Pages/Catalog/Show.vue`
- Test: `tests/Feature/Catalog/CourseCatalogTest.php`

**Interfaces:**
- Consumes: `courses.enroll` route (Task 1) for the Show page's enroll button.
- Produces: named routes `catalog.index`, `catalog.show` (both GET). `index` passes `courses` = list of `{id, title, slug, summary, level, instructor, is_enrolled}`; `show` passes `course` = `{title, slug, summary, description, level, instructor, modules: [{title, lessons: [{title}]}]}` and `is_enrolled` (bool).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Catalog/CourseCatalogTest.php`:

```php
<?php

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the catalog lists only published courses', function (): void {
    $user = User::factory()->student()->create();
    Course::factory()->published()->create(['title' => 'Published One']);
    Course::factory()->create(['status' => CourseStatus::Draft, 'title' => 'Draft One']);
    Course::factory()->create(['status' => CourseStatus::Archived, 'title' => 'Archived One']);

    $this->actingAs($user)
        ->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Catalog/Index')
            ->has('courses', 1)
            ->where('courses.0.title', 'Published One')
        );
});

test('the catalog marks courses the user is already enrolled in', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => \App\Enums\EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('catalog.index'))
        ->assertInertia(fn ($page) => $page->where('courses.0.is_enrolled', true));
});

test('a guest is redirected to login from the catalog', function (): void {
    $this->get(route('catalog.index'))->assertRedirect(route('login'));
});

test('the course detail renders the syllabus in position order', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module_one = Module::factory()->for($course)->create(['title' => 'Module A', 'position' => 0]);
    $module_two = Module::factory()->for($course)->create(['title' => 'Module B', 'position' => 1]);
    Lesson::factory()->for($module_one)->create(['title' => 'Lesson A1', 'position' => 0]);
    Lesson::factory()->for($module_two)->create(['title' => 'Lesson B1', 'position' => 0]);

    $this->actingAs($user)
        ->get(route('catalog.show', $course))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Catalog/Show')
            ->where('course.modules.0.title', 'Module A')
            ->where('course.modules.1.title', 'Module B')
            ->where('course.modules.0.lessons.0.title', 'Lesson A1')
            ->where('is_enrolled', false)
        );
});

test('the course detail 404s for a draft course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->create(['status' => CourseStatus::Draft]);

    $this->actingAs($user)->get(route('catalog.show', $course))->assertNotFound();
});

test('the course detail 404s for an archived course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->create(['status' => CourseStatus::Archived]);

    $this->actingAs($user)->get(route('catalog.show', $course))->assertNotFound();
});

test('the course detail reflects enrollment state after enrolling', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => \App\Enums\EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('catalog.show', $course))
        ->assertInertia(fn ($page) => $page->where('is_enrolled', true));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=CourseCatalogTest`
Expected: FAIL — route `catalog.index` not defined.

- [ ] **Step 3: Create CourseCatalogController**

Create `app/Http/Controllers/CourseCatalogController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\CourseStatus;
use App\Models\Course;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $enrolled_course_ids = $request->user()->enrollments()->pluck('course_id');

        $courses = Course::query()
            ->where('status', CourseStatus::Published)
            ->with('instructor:id,name')
            ->latest('published_at')
            ->get()
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'summary' => $course->summary,
                'level' => $course->level,
                'instructor' => $course->instructor->name,
                'is_enrolled' => $enrolled_course_ids->contains($course->id),
            ]);

        return Inertia::render('Catalog/Index', [
            'courses' => $courses,
        ]);
    }

    public function show(Request $request, Course $course): Response
    {
        abort_unless($course->status === CourseStatus::Published, 404);

        $course->load(['instructor:id,name', 'modules.lessons']);

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
                        'title' => $lesson->title,
                    ])->values(),
                ])->values(),
            ],
            'is_enrolled' => $request->user()->enrollments()->where('course_id', $course->id)->exists(),
        ]);
    }
}
```

- [ ] **Step 4: Add the catalog routes**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\CourseCatalogController;
```

Inside the `Route::middleware('verified')->group(...)` block, add:

```php
Route::get('catalog', [CourseCatalogController::class, 'index'])->name('catalog.index');
Route::get('catalog/{course}', [CourseCatalogController::class, 'show'])->name('catalog.show');
```

- [ ] **Step 5: Create Catalog/Index.vue**

Create `resources/js/Pages/Catalog/Index.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    courses: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Browse courses" />

        <h1 class="mb-6 text-2xl font-semibold">Browse courses</h1>

        <div v-if="courses.length === 0" class="rounded border border-dashed p-8 text-center text-gray-500">
            No published courses yet.
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="course in courses"
                :key="course.id"
                :href="route('catalog.show', course.slug)"
                class="block rounded-lg border p-5 hover:shadow"
            >
                <div class="mb-2 flex items-center justify-between">
                    <span class="rounded-full bg-gray-100 px-2 py-1 text-xs">{{ course.level }}</span>
                    <span v-if="course.is_enrolled" class="text-xs font-medium text-green-600">Enrolled</span>
                </div>
                <h2 class="font-semibold">{{ course.title }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ course.summary }}</p>
                <p class="mt-3 text-xs text-gray-500">{{ course.instructor }}</p>
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 6: Create Catalog/Show.vue**

Create `resources/js/Pages/Catalog/Show.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    course: {
        type: Object,
        required: true,
    },
    is_enrolled: {
        type: Boolean,
        required: true,
    },
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

        <p v-if="course.summary" class="mb-4 text-gray-700">{{ course.summary }}</p>
        <p v-if="course.description" class="mb-8 whitespace-pre-line text-gray-600">{{ course.description }}</p>

        <h2 class="mb-3 text-lg font-semibold">Syllabus</h2>
        <div v-if="course.modules.length === 0" class="text-sm text-gray-500">
            No modules yet.
        </div>
        <ol v-else class="space-y-4">
            <li v-for="(module, index) in course.modules" :key="index" class="rounded border p-4">
                <h3 class="font-medium">{{ module.title }}</h3>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600">
                    <li v-for="(lesson, lessonIndex) in module.lessons" :key="lessonIndex">
                        {{ lesson.title }}
                    </li>
                </ul>
            </li>
        </ol>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=CourseCatalogTest`
Expected: PASS (7 tests).

- [ ] **Step 8: Build the frontend**

Run: `npm run build`
Expected: build succeeds; `Catalog/Index` and `Catalog/Show` compile with no unresolved imports.

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/CourseCatalogController.php routes/web.php resources/js/Pages/Catalog tests/Feature/Catalog/CourseCatalogTest.php
git commit -m "Add course catalog and detail pages

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: My Courses list

**Files:**
- Modify: `app/Http/Controllers/EnrollmentController.php` (add `index`)
- Modify: `routes/web.php`
- Create: `resources/js/Pages/Enrollments/Index.vue`
- Test: `tests/Feature/Enrollments/EnrollmentTest.php`

**Interfaces:**
- Consumes: the `EnrollmentController` from Task 1.
- Produces: named route `enrollments.index` (GET). `index` passes `enrollments` = list of `{course_title, course_slug, status, progress_percentage}`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Enrollments/EnrollmentTest.php`:

```php
test('my courses lists only the current users enrollments', function (): void {
    $user = User::factory()->student()->create();
    $other = User::factory()->student()->create();
    $mine = Course::factory()->published()->create(['title' => 'My Course']);
    $theirs = Course::factory()->published()->create(['title' => 'Their Course']);

    $user->enrollments()->create(['course_id' => $mine->id, 'status' => EnrollmentStatus::Active, 'enrolled_at' => now()]);
    $other->enrollments()->create(['course_id' => $theirs->id, 'status' => EnrollmentStatus::Active, 'enrolled_at' => now()]);

    $this->actingAs($user)
        ->get(route('enrollments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Enrollments/Index')
            ->has('enrollments', 1)
            ->where('enrollments.0.course_title', 'My Course')
        );
});

test('a guest is redirected to login from my courses', function (): void {
    $this->get(route('enrollments.index'))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=EnrollmentTest`
Expected: FAIL — route `enrollments.index` not defined.

- [ ] **Step 3: Add the `index` method to EnrollmentController**

In `app/Http/Controllers/EnrollmentController.php`, add the imports and the method:

Add to the top imports:

```php
use App\Models\Enrollment;
use Inertia\Inertia;
use Inertia\Response;
```

Add the method:

```php
public function index(Request $request): Response
{
    $enrollments = $request->user()->enrollments()
        ->with('course:id,title,slug')
        ->latest('enrolled_at')
        ->get()
        ->map(fn (Enrollment $enrollment): array => [
            'course_title' => $enrollment->course->title,
            'course_slug' => $enrollment->course->slug,
            'status' => $enrollment->status,
            'progress_percentage' => $enrollment->progress_percentage,
        ]);

    return Inertia::render('Enrollments/Index', [
        'enrollments' => $enrollments,
    ]);
}
```

- [ ] **Step 4: Add the my-courses route**

In `routes/web.php`, inside the `Route::middleware('verified')->group(...)` block, add:

```php
Route::get('my-courses', [EnrollmentController::class, 'index'])->name('enrollments.index');
```

- [ ] **Step 5: Create Enrollments/Index.vue**

Create `resources/js/Pages/Enrollments/Index.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    enrollments: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="My courses" />

        <h1 class="mb-6 text-2xl font-semibold">My courses</h1>

        <div v-if="enrollments.length === 0" class="rounded border border-dashed p-8 text-center text-gray-500">
            You haven't enrolled in any courses yet.
        </div>

        <table v-else class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="border-b text-gray-500">
                    <th class="py-2">Course</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Progress</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="enrollment in enrollments" :key="enrollment.course_slug" class="border-b">
                    <td class="py-3 font-medium">
                        <Link :href="route('catalog.show', enrollment.course_slug)" class="text-blue-600 hover:underline">
                            {{ enrollment.course_title }}
                        </Link>
                    </td>
                    <td class="py-3">
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs">{{ enrollment.status }}</span>
                    </td>
                    <td class="py-3">{{ enrollment.progress_percentage }}%</td>
                </tr>
            </tbody>
        </table>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=EnrollmentTest`
Expected: PASS (all Task 1 + Task 3 enrollment tests).

- [ ] **Step 7: Build the frontend**

Run: `npm run build`
Expected: build succeeds; `Enrollments/Index` compiles.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/EnrollmentController.php routes/web.php resources/js/Pages/Enrollments tests/Feature/Enrollments/EnrollmentTest.php
git commit -m "Add My Courses enrollment list

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Navigation links and full verification

**Files:**
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`

**Interfaces:**
- Consumes: named routes `catalog.index`, `enrollments.index`.

- [ ] **Step 1: Add the nav links**

In `resources/js/Layouts/AuthenticatedLayout.vue`, add "Browse Courses" and "My Courses" links for all authenticated users. The existing left side of the nav has the brand `Link` and a conditional "Courses" (instructor-only) link. Add the two new links right after the brand `Link`, so the left nav group reads:

```vue
<Link :href="route('dashboard')" class="font-semibold">LMS</Link>
<Link :href="route('catalog.index')" class="text-sm text-gray-600 hover:underline">Browse Courses</Link>
<Link :href="route('enrollments.index')" class="text-sm text-gray-600 hover:underline">My Courses</Link>
<Link
    v-if="canCreateCourses"
    :href="route('courses.index')"
    class="text-sm text-gray-600 hover:underline"
>
    Courses
</Link>
```

Keep the existing brand `Link` and the instructor-only "Courses" link; only the two new lines are added. If the brand and links are not already in a shared flex container on the left, wrap the brand + these links in a `<div class="flex items-center gap-4">` so they cluster together (the user name + logout stay in their own right-hand `<div class="flex items-center gap-4">`).

- [ ] **Step 2: Build the frontend**

Run: `npm run build`
Expected: build succeeds; `AuthenticatedLayout` compiles with the new links.

- [ ] **Step 3: Run the full test suite**

Run: `php artisan test --compact`
Expected: PASS — all prior auth/dashboard/course tests plus the new catalog and enrollment tests.

- [ ] **Step 4: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/js/Layouts/AuthenticatedLayout.vue
git commit -m "Add catalog and my-courses navigation links

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review Notes

- **Spec coverage:** enroll ability on CoursePolicy + enroll endpoint (Task 1); catalog index (published-only, enrolled-marking) + detail (syllabus, 404 on non-published, is_enrolled) (Task 2); My Courses (Task 3); nav links for all authenticated users (Task 4). Every test in the spec's matrix maps to a task: role-matrix enroll + idempotency + draft/archived 403 + guest (Task 1); catalog published-only + enrolled-mark + guest + syllabus-order + 404s + is_enrolled (Task 2); My-Courses-own-only (Task 3); enroll-policy unit (Task 1).
- **No throwaway stubs:** each Vue page is created in the same task as the controller whose tests assert `component('...')`, so Inertia's `ensure_pages_exist` is satisfied on first run — unlike the prior slice.
- **Route placement:** all four student routes go inside the existing `auth` → `verified` nested group; the plan names that block explicitly in each route step.
- **No new `can` prop:** enroll is not permission-gated, so `HandleInertiaRequests` is untouched and nav links show for everyone — consistent with the locked decision.
- **Type consistency:** `is_enrolled` (snake) is the prop name in both controller and Vue across Tasks 2; enroll route name `courses.enroll` is produced in Task 1 and consumed by Show.vue in Task 2; `EnrollStudent::run(user, course)` matches the existing action signature.
- **Enrolled-state N+1:** index plucks `course_id`s once (Task 2 Step 3), per the spec.
- **Known admin edge:** `Gate::before` grants admins every ability, so an admin's `can('enroll', $draftCourse)` returns true — an admin can technically enroll in an unpublished course, bypassing the policy's Published check. This is consistent with the app-wide admin-god pattern and was acknowledged in the spec; the draft/archived-403 tests deliberately use a student. Not a defect to fix here.
- **`@` alias + slug binding:** proven in prior slices; `route('catalog.show', course.slug)` and `route('courses.enroll', course.slug)` pass the slug.
```
