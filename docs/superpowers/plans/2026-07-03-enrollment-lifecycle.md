# Enrollment Lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a student drop an active course (preserving progress), re-enroll to resume it, and let an instructor remove an active student from their own course.

**Architecture:** Drop is a status transition to `EnrollmentStatus::Dropped` — never a delete — so progress and lesson completions survive and re-enrolling reactivates the same row. A new `DropEnrollment` action performs the transition; `EnrollStudent` is modified to reactivate a dropped row instead of returning it unchanged. One `EnrollmentPolicy::drop` ability and one `DELETE enrollments/{enrollment}` endpoint serve both student self-drop and instructor removal. A new instructor roster page lists a course's students.

**Tech Stack:** Laravel 13, Inertia v3, Vue 3, Pest 4, Loris Leiva Actions (`AsAction`), Spatie permissions.

## Global Constraints

- Naming: variables `snake_case`, methods/functions `camelCase`, classes `TitleCase`.
- Thin controllers authorize via policy + delegate to Actions; features gated behind `auth` + `verified`.
- Policies are auto-discovered (model `Foo` → `FooPolicy`); admins bypass via existing `Gate::before` in `AppServiceProvider`.
- All feature/policy tests seed `RolePermissionSeeder` in `beforeEach` (RefreshDatabase does not seed).
- No migration in this slice — the `status` column and `EnrollmentStatus::Dropped` case already exist.
- Enums serialize to their string value over Inertia (e.g. `EnrollmentStatus::Active` → `"Active"`).
- Run PHP formatting with `vendor/bin/pint --dirty --format agent` before each commit that touches PHP.
- Run tests with `php artisan test --compact --filter=...`.

---

### Task 1: `DropEnrollment` action

**Files:**
- Create: `app/Actions/DropEnrollment.php`
- Test: `tests/Unit/Actions/DropEnrollmentTest.php`

**Interfaces:**
- Produces: `DropEnrollment::run(Enrollment $enrollment): Enrollment` — sets `status = EnrollmentStatus::Dropped`, leaves `progress_percentage`, `lessonCompletions`, and `enrolled_at` untouched.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Actions/DropEnrollmentTest.php`:

```php
<?php

use App\Actions\DropEnrollment;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;

test('it transitions an active enrollment to dropped and preserves progress', function (): void {
    $enrollment = Enrollment::factory()->create([
        'status' => EnrollmentStatus::Active,
        'progress_percentage' => 40,
    ]);

    $result = DropEnrollment::run($enrollment);

    expect($result->status)->toBe(EnrollmentStatus::Dropped)
        ->and($result->progress_percentage)->toBe(40)
        ->and($enrollment->fresh()->status)->toBe(EnrollmentStatus::Dropped);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DropEnrollmentTest`
Expected: FAIL — class `App\Actions\DropEnrollment` not found.

- [ ] **Step 3: Write minimal implementation**

Create `app/Actions/DropEnrollment.php`:

```php
<?php

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Lorisleiva\Actions\Concerns\AsAction;

class DropEnrollment
{
    use AsAction;

    /**
     * Drop an enrollment by transitioning its status to Dropped, preserving progress.
     */
    public function handle(Enrollment $enrollment): Enrollment
    {
        $enrollment->update(['status' => EnrollmentStatus::Dropped]);

        return $enrollment;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=DropEnrollmentTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/DropEnrollment.php tests/Unit/Actions/DropEnrollmentTest.php
git commit -m "Add DropEnrollment action"
```

---

### Task 2: Reactivate dropped rows in `EnrollStudent`

**Files:**
- Modify: `app/Actions/EnrollStudent.php`
- Test: `tests/Unit/Actions/EnrollStudentTest.php`

**Interfaces:**
- Produces (unchanged signature): `EnrollStudent::run(User $student, Course $course): Enrollment`. New behavior: a new row is created `Active`; an existing `Dropped` row is reactivated to `Active` with its original `enrolled_at` preserved; an existing `Active`/`Completed` row is returned unchanged. Never creates a second row for the same `(user, course)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Actions/EnrollStudentTest.php`:

```php
<?php

use App\Actions\EnrollStudent;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

test('it creates a new active enrollment', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $enrollment = EnrollStudent::run($student, $course);

    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->enrolled_at)->not->toBeNull();
});

test('it reactivates a dropped enrollment without creating a new row or losing progress', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $original = Enrollment::factory()->for($student)->for($course)->create([
        'status' => EnrollmentStatus::Dropped,
        'progress_percentage' => 60,
        'enrolled_at' => now()->subMonth(),
    ]);

    $enrollment = EnrollStudent::run($student, $course);

    expect($enrollment->id)->toBe($original->id)
        ->and($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->progress_percentage)->toBe(60)
        ->and($enrollment->enrolled_at->toDateString())->toBe($original->enrolled_at->toDateString())
        ->and(Enrollment::where(['user_id' => $student->id, 'course_id' => $course->id])->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EnrollStudentTest`
Expected: FAIL — the reactivation test fails because `firstOrCreate` returns the dropped row with `status` still `Dropped`.

- [ ] **Step 3: Modify the action**

Replace the body of `app/Actions/EnrollStudent.php`'s `handle` method:

```php
    public function handle(User $student, Course $course): Enrollment
    {
        $enrollment = Enrollment::firstOrNew([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        if (! $enrollment->exists) {
            $enrollment->status = EnrollmentStatus::Active;
            $enrollment->enrolled_at = now();
        } elseif ($enrollment->status === EnrollmentStatus::Dropped) {
            $enrollment->status = EnrollmentStatus::Active;
        }

        $enrollment->save();

        return $enrollment;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=EnrollStudentTest`
Expected: PASS.

- [ ] **Step 5: Run the existing enrollment feature tests (regression)**

Run: `php artisan test --compact --filter=EnrollmentTest`
Expected: PASS — the existing "enrolling twice does not create a duplicate" test still holds.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/EnrollStudent.php tests/Unit/Actions/EnrollStudentTest.php
git commit -m "Reactivate dropped enrollment on re-enroll instead of returning it unchanged"
```

---

### Task 3: Drop endpoint — policy, route, controller

**Files:**
- Create: `app/Policies/EnrollmentPolicy.php`
- Modify: `app/Http/Controllers/EnrollmentController.php` (add `destroy`)
- Modify: `routes/web.php` (add `DELETE enrollments/{enrollment}`)
- Test: `tests/Feature/Enrollments/DropEnrollmentTest.php`

**Interfaces:**
- Consumes: `DropEnrollment::run(Enrollment $enrollment)` (Task 1).
- Produces: named route `enrollments.destroy` (`DELETE enrollments/{enrollment}`); `EnrollmentPolicy::drop(User $user, Enrollment $enrollment): bool` — true only when `status === Active` AND (`user_id === $user->id` OR `course->instructor_id === $user->id`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Enrollments/DropEnrollmentTest.php`:

```php
<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('a student can drop their own active enrollment and keep progress', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $enrollment = Enrollment::factory()->for($student)->for($course)->create([
        'status' => EnrollmentStatus::Active,
        'progress_percentage' => 30,
    ]);

    $this->actingAs($student)
        ->delete(route('enrollments.destroy', $enrollment))
        ->assertRedirect();

    $enrollment->refresh();
    expect($enrollment->status)->toBe(EnrollmentStatus::Dropped)
        ->and($enrollment->progress_percentage)->toBe(30);
});

test('an instructor can remove an active student from their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);
    $enrollment = Enrollment::factory()->for($course)->create(['status' => EnrollmentStatus::Active]);

    $this->actingAs($instructor)
        ->delete(route('enrollments.destroy', $enrollment))
        ->assertRedirect();

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Dropped);
});

test('an instructor cannot remove a student from another instructors course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $otherCourse = Course::factory()->published()->create();
    $enrollment = Enrollment::factory()->for($otherCourse)->create(['status' => EnrollmentStatus::Active]);

    $this->actingAs($instructor)
        ->delete(route('enrollments.destroy', $enrollment))
        ->assertForbidden();

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Active);
});

test('a student cannot drop another users enrollment', function (): void {
    $student = User::factory()->student()->create();
    $enrollment = Enrollment::factory()->create(['status' => EnrollmentStatus::Active]);

    $this->actingAs($student)
        ->delete(route('enrollments.destroy', $enrollment))
        ->assertForbidden();
});

test('a completed enrollment cannot be dropped', function (): void {
    $student = User::factory()->student()->create();
    $enrollment = Enrollment::factory()->for($student)->completed()->create();

    $this->actingAs($student)
        ->delete(route('enrollments.destroy', $enrollment))
        ->assertForbidden();

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Completed);
});

test('re-enrolling after a drop reactivates the same row with progress intact', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $enrollment = Enrollment::factory()->for($student)->for($course)->create([
        'status' => EnrollmentStatus::Active,
        'progress_percentage' => 50,
    ]);

    $this->actingAs($student)->delete(route('enrollments.destroy', $enrollment));
    $this->actingAs($student)->post(route('courses.enroll', $course))->assertRedirect();

    $enrollment->refresh();
    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->progress_percentage)->toBe(50)
        ->and(Enrollment::where(['user_id' => $student->id, 'course_id' => $course->id])->count())->toBe(1);
});

test('a guest cannot drop an enrollment', function (): void {
    $enrollment = Enrollment::factory()->create(['status' => EnrollmentStatus::Active]);

    $this->delete(route('enrollments.destroy', $enrollment))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DropEnrollmentTest`
Expected: FAIL — route `enrollments.destroy` is not defined.

- [ ] **Step 3: Create the policy**

Create `app/Policies/EnrollmentPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    /**
     * Determine whether the user may drop the enrollment (self-drop or instructor removal).
     */
    public function drop(User $user, Enrollment $enrollment): bool
    {
        if ($enrollment->status !== EnrollmentStatus::Active) {
            return false;
        }

        return $enrollment->user_id === $user->id
            || $enrollment->course->instructor_id === $user->id;
    }
}
```

- [ ] **Step 4: Add the controller action**

In `app/Http/Controllers/EnrollmentController.php`, add the `DropEnrollment` and `Enrollment` imports (if not present) and this method:

```php
    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        $this->authorize('drop', $enrollment);

        DropEnrollment::run($enrollment);

        return back()->with('status', 'Enrollment dropped.');
    }
```

Ensure the top of the file imports `use App\Actions\DropEnrollment;` (add alongside the existing `use App\Actions\EnrollStudent;`).

- [ ] **Step 5: Register the route**

In `routes/web.php`, inside the `verified` group, next to the existing enroll route, add:

```php
        Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --compact --filter=DropEnrollmentTest`
Expected: PASS (all 7 cases).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/EnrollmentPolicy.php app/Http/Controllers/EnrollmentController.php routes/web.php tests/Feature/Enrollments/DropEnrollmentTest.php
git commit -m "Add drop endpoint with EnrollmentPolicy for self-drop and instructor removal"
```

---

### Task 4: Instructor roster page (backend)

**Files:**
- Modify: `app/Policies/CoursePolicy.php` (add `viewRoster`)
- Create: `app/Http/Controllers/Course/RosterController.php`
- Modify: `routes/web.php` (add `GET courses/{course}/students`)
- Test: `tests/Feature/Courses/RosterTest.php`

**Interfaces:**
- Produces: named route `courses.roster` (`GET courses/{course}/students`); `CoursePolicy::viewRoster(User $user, Course $course): bool` (`can('update courses')` AND owns course); Inertia page `Courses/Roster` with props `course: {title, slug}` and `students: Array<{id, name, status, progress_percentage, enrolled_at}>`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Courses/RosterTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('an instructor can view the roster of their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);
    $enrollment = Enrollment::factory()->for($course)->create();

    $this->actingAs($instructor)
        ->get(route('courses.roster', $course))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Courses/Roster')
            ->has('students', 1)
            ->where('students.0.id', $enrollment->id)
        );
});

test('an instructor cannot view the roster of another instructors course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $otherCourse = Course::factory()->published()->create();

    $this->actingAs($instructor)
        ->get(route('courses.roster', $otherCourse))
        ->assertForbidden();
});

test('a student cannot view a course roster', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($student)
        ->get(route('courses.roster', $course))
        ->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=RosterTest`
Expected: FAIL — route `courses.roster` not defined.

- [ ] **Step 3: Add the policy ability**

In `app/Policies/CoursePolicy.php`, add:

```php
    public function viewRoster(User $user, Course $course): bool
    {
        return $user->can('update courses') && $course->instructor_id === $user->id;
    }
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Course/RosterController.php`:

```php
<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use Inertia\Inertia;
use Inertia\Response;

class RosterController extends Controller
{
    public function index(Course $course): Response
    {
        $this->authorize('viewRoster', $course);

        $students = $course->enrollments()
            ->with('student:id,name')
            ->latest('enrolled_at')
            ->get()
            ->map(fn (Enrollment $enrollment): array => [
                'id' => $enrollment->id,
                'name' => $enrollment->student->name,
                'status' => $enrollment->status,
                'progress_percentage' => $enrollment->progress_percentage,
                'enrolled_at' => $enrollment->enrolled_at?->toDateString(),
            ]);

        return Inertia::render('Courses/Roster', [
            'course' => [
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'students' => $students,
        ]);
    }
}
```

- [ ] **Step 5: Register the route**

In `routes/web.php`, add the import at the top:

```php
use App\Http\Controllers\Course\RosterController;
```

And inside the `verified` group (near the other `courses/{course}/...` routes) add:

```php
        Route::get('courses/{course}/students', [RosterController::class, 'index'])->name('courses.roster');
```

- [ ] **Step 6: Create a placeholder Vue page so Inertia can render**

Create `resources/js/Pages/Courses/Roster.vue` with a minimal stub (fleshed out in Task 7) so the feature test renders:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    course: { type: Object, required: true },
    students: { type: Array, required: true },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Roster — ${course.title}`" />
        <h1 class="text-2xl font-semibold">Roster</h1>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --compact --filter=RosterTest`
Expected: PASS (all 3 cases).

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/CoursePolicy.php app/Http/Controllers/Course/RosterController.php routes/web.php resources/js/Pages/Courses/Roster.vue tests/Feature/Courses/RosterTest.php
git commit -m "Add instructor course roster page and viewRoster policy"
```

---

### Task 5: Catalog detail — drop button

**Files:**
- Modify: `app/Http/Controllers/CourseCatalogController.php` (`show` props)
- Modify: `resources/js/Pages/Catalog/Show.vue`
- Test: `tests/Feature/Catalog/` (add a prop assertion — reuse existing catalog test file if present, else create `CatalogDropTest.php`)

**Interfaces:**
- Consumes: route `enrollments.destroy` (Task 3).
- Produces: `Catalog/Show` gains props `enrollment_id: ?int` and `enrollment_status: ?string` (the enum value, e.g. `"Active"`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Catalog/CatalogDropTest.php`:

```php
<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the catalog detail exposes the enrollment id and status for an enrolled student', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $enrollment = Enrollment::factory()->for($student)->for($course)->create([
        'status' => EnrollmentStatus::Active,
    ]);

    $this->actingAs($student)
        ->get(route('catalog.show', $course))
        ->assertInertia(fn ($page) => $page
            ->where('enrollment_id', $enrollment->id)
            ->where('enrollment_status', 'Active')
        );
});

test('the catalog detail exposes null enrollment props for a non-enrolled student', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($student)
        ->get(route('catalog.show', $course))
        ->assertInertia(fn ($page) => $page
            ->where('enrollment_id', null)
            ->where('enrollment_status', null)
        );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CatalogDropTest`
Expected: FAIL — props `enrollment_id` / `enrollment_status` are absent.

- [ ] **Step 3: Add the props in the controller**

In `app/Http/Controllers/CourseCatalogController.php`'s `show`, the `$enrollment` variable already exists. Add these two entries to the `Inertia::render('Catalog/Show', [...])` array (alongside `'is_enrolled'`):

```php
            'enrollment_id' => $enrollment?->id,
            'enrollment_status' => $enrollment?->status,
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=CatalogDropTest`
Expected: PASS.

- [ ] **Step 5: Add the drop button to the Vue page**

In `resources/js/Pages/Catalog/Show.vue`:

Add the two props to `defineProps`:

```js
    enrollment_id: { type: Number, default: null },
    enrollment_status: { type: String, default: null },
```

Add a `drop` handler in the `<script setup>` (below the `enroll` function):

```js
const dropping = ref(false);

const drop = () => {
    if (! confirm(`Drop "${props.course.title}"? Your progress is saved if you re-enroll.`)) {
        return;
    }
    dropping.value = true;
    router.delete(route('enrollments.destroy', props.enrollment_id), {
        preserveScroll: true,
        onFinish: () => {
            dropping.value = false;
        },
    });
};
```

In the template, replace the existing `<span v-if="is_enrolled" ...>Enrolled</span>` block with an Enrolled badge plus a Drop button shown only for active enrollments:

```vue
                <template v-if="is_enrolled">
                    <span class="rounded bg-green-100 px-4 py-2 text-sm font-medium text-green-700">
                        Enrolled
                    </span>
                    <button
                        v-if="enrollment_status === 'Active'"
                        type="button"
                        :disabled="dropping"
                        class="rounded border border-red-300 px-4 py-2 text-sm font-medium text-red-600 disabled:opacity-50"
                        @click="drop"
                    >
                        Drop course
                    </button>
                </template>
```

(The `v-else` on the Enroll `<button>` stays as-is — it shows when not enrolled, which is the re-enroll path for a dropped student since `is_enrolled` reflects an existing row; see note below.)

**Note on re-enroll UX:** `is_enrolled` is true whenever an enrollment row exists, including a `Dropped` one. For a dropped student we want the Enroll button back. Update the enroll `<button>` condition from `v-else` to:

```vue
                <button
                    v-if="! is_enrolled || enrollment_status === 'Dropped'"
                    type="button"
                    :disabled="enrolling"
                    class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                    @click="enroll"
                >
                    {{ enrollment_status === 'Dropped' ? 'Re-enroll' : 'Enroll' }}
                </button>
```

And wrap the Enrolled badge/Drop button in `v-if="is_enrolled && enrollment_status !== 'Dropped'"` instead of just `is_enrolled`:

```vue
                <template v-if="is_enrolled && enrollment_status !== 'Dropped'">
```

- [ ] **Step 6: Build the frontend**

Run: `npm run build`
Expected: builds without errors.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/CourseCatalogController.php resources/js/Pages/Catalog/Show.vue tests/Feature/Catalog/CatalogDropTest.php
git commit -m "Add drop / re-enroll controls to catalog detail page"
```

---

### Task 6: My Courses — drop button

**Files:**
- Modify: `app/Http/Controllers/EnrollmentController.php` (`index` mapping)
- Modify: `resources/js/Pages/Enrollments/Index.vue`
- Test: `tests/Feature/Enrollments/EnrollmentTest.php` (extend — assert `id` present in the My Courses payload)

**Interfaces:**
- Consumes: route `enrollments.destroy` (Task 3).
- Produces: the `enrollments.index` payload gains `id` per row.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Enrollments/EnrollmentTest.php`:

```php
test('my courses exposes the enrollment id for each row', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $enrollment = Enrollment::factory()->for($user)->for($course)->create();

    $this->actingAs($user)
        ->get(route('enrollments.index'))
        ->assertInertia(fn ($page) => $page
            ->where('enrollments.0.id', $enrollment->id)
        );
});
```

If `Enrollment` is not already imported at the top of that test file, add `use App\Models\Enrollment;`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EnrollmentTest`
Expected: FAIL — the new test fails; `enrollments.0.id` is missing.

- [ ] **Step 3: Add `id` to the controller mapping**

In `app/Http/Controllers/EnrollmentController.php`'s `index`, add `'id' => $enrollment->id,` as the first entry of the mapped array:

```php
            ->map(fn (Enrollment $enrollment): array => [
                'id' => $enrollment->id,
                'course_title' => $enrollment->course->title,
                'course_slug' => $enrollment->course->slug,
                'status' => $enrollment->status,
                'progress_percentage' => $enrollment->progress_percentage,
            ]);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=EnrollmentTest`
Expected: PASS.

- [ ] **Step 5: Add the drop button to the Vue page**

In `resources/js/Pages/Enrollments/Index.vue`:

Add `router` to the import and a `drop` handler:

```js
import { Head, Link, router } from '@inertiajs/vue3';
```

```js
const drop = (enrollment) => {
    if (! confirm(`Drop "${enrollment.course_title}"? Your progress is saved if you re-enroll.`)) {
        return;
    }
    router.delete(route('enrollments.destroy', enrollment.id), { preserveScroll: true });
};
```

Add an Actions column. In `<thead>`, add a header cell:

```vue
                    <th class="py-2 text-right">Actions</th>
```

In the `<tbody>` row, after the Progress cell, add:

```vue
                    <td class="py-3 text-right">
                        <button
                            v-if="enrollment.status === 'Active'"
                            type="button"
                            class="text-red-600 hover:underline"
                            @click="drop(enrollment)"
                        >
                            Drop
                        </button>
                    </td>
```

Also change the `<tr>` key from `enrollment.course_slug` to `enrollment.id` (rows are now uniquely identified and a dropped+re-enrolled course is still one row):

```vue
                <tr v-for="enrollment in enrollments" :key="enrollment.id" class="border-b">
```

- [ ] **Step 6: Build the frontend**

Run: `npm run build`
Expected: builds without errors.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/EnrollmentController.php resources/js/Pages/Enrollments/Index.vue tests/Feature/Enrollments/EnrollmentTest.php
git commit -m "Add drop button to My Courses list"
```

---

### Task 7: Roster page UI + instructor link

**Files:**
- Modify: `resources/js/Pages/Courses/Roster.vue` (flesh out the Task 4 stub)
- Modify: `resources/js/Pages/Courses/Index.vue` (add a Roster link)

**Interfaces:**
- Consumes: `Courses/Roster` props from Task 4 (`course`, `students`); route `enrollments.destroy` (Task 3); route `courses.roster` (Task 4).

- [ ] **Step 1: Flesh out the roster page**

Replace `resources/js/Pages/Courses/Roster.vue` with:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    course: { type: Object, required: true },
    students: { type: Array, required: true },
});

const remove = (student) => {
    if (! confirm(`Remove ${student.name} from "${props.course.title}"?`)) {
        return;
    }
    router.delete(route('enrollments.destroy', student.id), { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Roster — ${course.title}`" />

        <h1 class="mb-6 text-2xl font-semibold">Roster — {{ course.title }}</h1>

        <div v-if="students.length === 0" class="rounded border border-dashed p-8 text-center text-gray-500">
            No students enrolled yet.
        </div>

        <table v-else class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="border-b text-gray-500">
                    <th class="py-2">Student</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Progress</th>
                    <th class="py-2">Enrolled</th>
                    <th class="py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="student in students" :key="student.id" class="border-b">
                    <td class="py-3 font-medium">{{ student.name }}</td>
                    <td class="py-3">
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs">{{ student.status }}</span>
                    </td>
                    <td class="py-3">{{ student.progress_percentage }}%</td>
                    <td class="py-3">{{ student.enrolled_at }}</td>
                    <td class="py-3 text-right">
                        <button
                            v-if="student.status === 'Active'"
                            type="button"
                            class="text-red-600 hover:underline"
                            @click="remove(student)"
                        >
                            Remove
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 2: Add the Roster link to the instructor course list**

In `resources/js/Pages/Courses/Index.vue`, inside the actions `<div class="flex justify-end gap-3">`, add a link next to the existing Curriculum link:

```vue
                            <Link :href="route('courses.roster', course.slug)" class="text-purple-600 hover:underline">Roster</Link>
```

- [ ] **Step 3: Build the frontend**

Run: `npm run build`
Expected: builds without errors.

- [ ] **Step 4: Smoke-test the full suite**

Run: `php artisan test --compact`
Expected: PASS — entire suite green.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Courses/Roster.vue resources/js/Pages/Courses/Index.vue
git commit -m "Flesh out roster page UI and link it from the course list"
```

---

## Post-implementation

- [ ] Update the memory note `enrollment-softdelete-unique-index-gotcha.md`: the drop path uses a status transition and never soft-deletes enrollments, so the unique-index landmine stays dormant; `EnrollStudent` now reactivates dropped rows. Update `project-roadmap-and-slices.md` to mark enrollment lifecycle shipped.
- [ ] Consider `superpowers:requesting-code-review` before merging.

## Self-Review notes

- **Spec coverage:** Drop-as-status (Tasks 1,3) ✓; re-enroll resume (Task 2, regression test in Task 3) ✓; student self-drop + instructor removal via one ability/endpoint (Task 3) ✓; only `Active` droppable (policy in Task 3, completed-drop test) ✓; roster (Task 4 backend, Task 7 UI) ✓; catalog + My Courses drop UI (Tasks 5,6) ✓; confirmation via `confirm()` matching module/lesson delete convention ✓; no migration ✓; tests seed `RolePermissionSeeder` ✓.
- **Types:** `DropEnrollment::run(Enrollment): Enrollment`, `EnrollStudent::run(User, Course): Enrollment`, `EnrollmentPolicy::drop(User, Enrollment): bool`, `CoursePolicy::viewRoster(User, Course): bool` — consistent across tasks.
- **Enum serialization:** feature tests compare `enrollment_status` / `status` against the string `'Active'`/`'Dropped'`, matching Inertia's string-backed enum serialization already relied on by the existing My Courses view.
