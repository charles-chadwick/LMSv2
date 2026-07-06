# Course Completion + Certificates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Auto-complete a course and issue a certificate when the last lesson is completed, let the student view/print it, and let anyone verify it publicly by serial number.

**Architecture:** Wire the existing `CompleteCourse` action into `CompleteLesson` (fires once on the Active→Completed transition at 100%). Add a `CertificateController` (authed index + show, public verify), a `CertificatePolicy`, a `serial_number` route key, three Vue pages, and a "View certificate" link on My Courses. No new dependency — export is browser print-to-PDF.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3 + Vue 3, Pest v4. Tests run on MariaDB (`lms_v2_testing`, DatabaseTruncation).

## Global Constraints

- Variables `snake_case`, methods `camelCase`, classes `TitleCase`.
- Curly braces on all control structures; explicit return types + param type hints; PHPDoc array shapes.
- Thin controllers authorize via policy + delegate to Actions; policies auto-discovered; admins bypass via `Gate::before`.
- Feature tests `seed(RolePermissionSeeder::class)` in `beforeEach` (RefreshDatabase doesn't seed).
- Use factories for test model creation (`CourseFactory::published()`, `Module`/`Lesson` factories, `EnrollmentFactory::completed()`, `CertificateFactory`).
- No new Composer/npm dependency.
- After PHP changes run `vendor/bin/pint --dirty --format agent`. After Vue changes run `npm run build`.
- Run focused tests with `php artisan test --compact --filter=...`; full suite once before committing.
- `final_grade` is `null` for now (no graded assessments); certificate views hide the grade when null.
- Existing shapes to build on: `App\Actions\CompleteCourse::run(Enrollment $e, ?float $grade = null)`; `Enrollment::certificate(): HasOne`; `User::certificates(): HasMany`; `Certificate` fields `serial_number` (auto-UUID), `final_grade`, `issued_at`, relations `enrollment()/student()/course()`; `Enrollment.content_snapshot` cast `array` with shape `['course' => ['title', ...], 'modules' => [...]]`.

---

### Task 1: Completion trigger in `CompleteLesson`

**Files:**
- Modify: `app/Actions/CompleteLesson.php`
- Test: `tests/Feature/Courses/CourseCompletionTest.php`

**Interfaces:**
- Consumes: `CompleteCourse::run(Enrollment)`, `EnrollmentStatus::Active`, `EnrollmentStatus::Completed`.
- Produces: completing the final lesson transitions the enrollment to `Completed` and issues one `Certificate`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Courses/CourseCompletionTest.php`:

```php
<?php

use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function enrolledStudentOnCourse(int $lessonCount): array
{
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create(['position' => 0]);
    $lessons = collect(range(0, $lessonCount - 1))->map(
        fn (int $i): Lesson => Lesson::factory()->for($module)->create(['position' => $i]),
    );
    $enrollment = $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    return [$user, $course, $lessons, $enrollment];
}

it('completes the course and issues one certificate when the last lesson is viewed', function () {
    [$user, $course, $lessons, $enrollment] = enrolledStudentOnCourse(2);

    $this->actingAs($user)->get(route('lessons.show', [$course, $lessons[0]]))->assertOk();
    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Active);

    $this->actingAs($user)->get(route('lessons.show', [$course, $lessons[1]]))->assertOk();

    $enrollment->refresh();
    expect($enrollment->status)->toBe(EnrollmentStatus::Completed)
        ->and($enrollment->completed_at)->not->toBeNull()
        ->and(Certificate::where('enrollment_id', $enrollment->id)->count())->toBe(1);
});

it('does not complete the course while lessons remain', function () {
    [$user, $course, $lessons, $enrollment] = enrolledStudentOnCourse(2);

    $this->actingAs($user)->get(route('lessons.show', [$course, $lessons[0]]))->assertOk();

    $enrollment->refresh();
    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and(Certificate::where('enrollment_id', $enrollment->id)->exists())->toBeFalse();
});

it('does not re-issue or reset completion when a lesson is re-viewed after completing', function () {
    [$user, $course, $lessons, $enrollment] = enrolledStudentOnCourse(1);

    $this->actingAs($user)->get(route('lessons.show', [$course, $lessons[0]]))->assertOk();
    $enrollment->refresh();
    $completed_at = $enrollment->completed_at;

    $this->actingAs($user)->get(route('lessons.show', [$course, $lessons[0]]))->assertOk();

    $enrollment->refresh();
    expect(Certificate::where('enrollment_id', $enrollment->id)->count())->toBe(1)
        ->and($enrollment->completed_at->equalTo($completed_at))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CourseCompletionTest`
Expected: FAIL — first test: status stays `Active` / no certificate created (the trigger doesn't exist yet).

- [ ] **Step 3: Add the trigger to `CompleteLesson`**

In `app/Actions/CompleteLesson.php`, add imports:

```php
use App\Enums\EnrollmentStatus;
```
and
```php
use App\Actions\CompleteCourse;
```

Replace the `handle()` method with:

```php
    /**
     * Mark a lesson complete for an enrollment, recalculate progress, and
     * complete the course + issue a certificate once the final lesson is done.
     */
    public function handle(Enrollment $enrollment, Lesson $lesson): Enrollment
    {
        $enrollment->lessonCompletions()->firstOrCreate(
            ['lesson_id' => $lesson->id],
            ['completed_at' => now()],
        );

        $totalLessons = $enrollment->course->lessons()->count();
        $completedLessons = $enrollment->lessonCompletions()->count();

        $enrollment->update([
            'progress_percentage' => $totalLessons > 0
                ? (int) round($completedLessons / $totalLessons * 100)
                : 0,
        ]);

        if (
            $enrollment->status === EnrollmentStatus::Active
            && $totalLessons > 0
            && $completedLessons >= $totalLessons
        ) {
            CompleteCourse::run($enrollment);
        }

        return $enrollment;
    }
```

> `CompleteCourse::run($enrollment)` mutates the same `$enrollment` instance (status → Completed, snapshot frozen, certificate created), so the returned instance already reflects completion. The `status === Active` guard fires the transition exactly once; a Completed student re-viewing a lesson skips it.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=CourseCompletionTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/CompleteLesson.php tests/Feature/Courses/CourseCompletionTest.php
git commit -m "feat: complete course and issue certificate on final lesson"
```

---

### Task 2: Authed certificate viewing (policy, route key, controller, pages)

**Files:**
- Modify: `app/Models/Certificate.php` (add `getRouteKeyName`)
- Create: `app/Policies/CertificatePolicy.php`
- Create: `app/Http/Controllers/CertificateController.php`
- Modify: `routes/web.php` (two routes in the `auth`+`verified` group)
- Create: `resources/js/Pages/Certificates/Index.vue`
- Create: `resources/js/Pages/Certificates/Show.vue`
- Test: `tests/Feature/Certificates/CertificateTest.php`

**Interfaces:**
- Consumes: `CertificateFactory`, `User::certificates()`, `Enrollment.content_snapshot`.
- Produces:
  - Route `certificates.index` (GET `certificates`) — the requesting user's certificates.
  - Route `certificates.show` (GET `certificates/{certificate}`, bound by `serial_number`) — one certificate, owner-or-admin.
  - `CertificateController::certificateProps(Certificate): array` shape `{student_name, course_title, issued_at, serial_number, final_grade}` (course_title from the frozen snapshot).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Certificates/CertificateTest.php`:

```php
<?php

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function certificateFor(User $student): Certificate
{
    $course = Course::factory()->published()->create(['title' => 'Original Title']);
    $enrollment = Enrollment::factory()->completed()->for($student, 'student')->for($course)->create([
        'content_snapshot' => ['version' => 1, 'course' => ['title' => 'Original Title']],
    ]);

    return Certificate::factory()->create([
        'enrollment_id' => $enrollment->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'final_grade' => null,
    ]);
}

it('lets the owner view their certificate by serial number', function () {
    $student = User::factory()->student()->create();
    $certificate = certificateFor($student);

    $this->actingAs($student)->get(route('certificates.show', $certificate))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Certificates/Show')
            ->where('certificate.serial_number', $certificate->serial_number)
            ->where('certificate.course_title', 'Original Title'));
});

it('forbids a different student from viewing a certificate', function () {
    $owner = User::factory()->student()->create();
    $other = User::factory()->student()->create();
    $certificate = certificateFor($owner);

    $this->actingAs($other)->get(route('certificates.show', $certificate))->assertForbidden();
});

it('lets an admin view any certificate', function () {
    $student = User::factory()->student()->create();
    $admin = User::factory()->admin()->create();
    $certificate = certificateFor($student);

    $this->actingAs($admin)->get(route('certificates.show', $certificate))->assertOk();
});

it('lists only the requesting user\'s certificates', function () {
    $student = User::factory()->student()->create();
    certificateFor($student);
    certificateFor(User::factory()->student()->create());

    $this->actingAs($student)->get(route('certificates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Certificates/Index')
            ->has('certificates', 1));
});
```

> Confirm `User::factory()` has `admin()` and `student()` states before running — they are used across the existing suite (e.g. `UserFilterTest`), so they exist.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CertificateTest`
Expected: FAIL — route `certificates.show` not defined.

- [ ] **Step 3: Add the `serial_number` route key to `Certificate`**

In `app/Models/Certificate.php`, add this method (e.g. after `casts()`):

```php
    public function getRouteKeyName(): string
    {
        return 'serial_number';
    }
```

- [ ] **Step 4: Create `CertificatePolicy`**

Create `app/Policies/CertificatePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    public function view(User $user, Certificate $certificate): bool
    {
        return $certificate->user_id === $user->id;
    }
}
```

> Admins are allowed via the existing global `Gate::before`.

- [ ] **Step 5: Create `CertificateController` (index + show)**

Create `app/Http/Controllers/CertificateController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    public function index(Request $request): Response
    {
        $certificates = $request->user()->certificates()
            ->with('course:id,title')
            ->latest('issued_at')
            ->get()
            ->map(fn (Certificate $certificate): array => [
                'serial_number' => $certificate->serial_number,
                'course_title' => $certificate->course->title,
                'issued_at' => $certificate->issued_at?->toIso8601String(),
                'final_grade' => $certificate->final_grade,
            ]);

        return Inertia::render('Certificates/Index', [
            'certificates' => $certificates,
        ]);
    }

    public function show(Certificate $certificate): Response
    {
        $this->authorize('view', $certificate);

        return Inertia::render('Certificates/Show', [
            'certificate' => $this->certificateProps($certificate),
        ]);
    }

    /**
     * Certificate display shape, drawing the course title from the frozen snapshot.
     *
     * @return array<string, mixed>
     */
    private function certificateProps(Certificate $certificate): array
    {
        $certificate->loadMissing('student:id,first_name,last_name', 'enrollment', 'course:id,title');

        $snapshotTitle = $certificate->enrollment?->content_snapshot['course']['title'] ?? null;

        return [
            'student_name' => $certificate->student->name,
            'course_title' => $snapshotTitle ?? $certificate->course->title,
            'issued_at' => $certificate->issued_at?->toIso8601String(),
            'serial_number' => $certificate->serial_number,
            'final_grade' => $certificate->final_grade,
        ];
    }
}
```

> `student->name` is the generated first/last column; selecting `first_name,last_name` keeps it populated.

- [ ] **Step 6: Register the authed routes**

In `routes/web.php`, inside the `auth` + `verified` group (near the other resource routes), add:

```php
        Route::get('certificates', [CertificateController::class, 'index'])->name('certificates.index');
        Route::get('certificates/{certificate}', [CertificateController::class, 'show'])->name('certificates.show');
```

Add `use App\Http\Controllers\CertificateController;` at the top of `routes/web.php` if imports are used there (match the file's existing style — if it uses inline FQNs, follow that).

- [ ] **Step 7: Create `Certificates/Index.vue`**

Create `resources/js/Pages/Certificates/Index.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    certificates: { type: Array, required: true },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="My certificates" />
        <PageHeader title="My certificates" subtitle="Certificates you've earned by completing courses." />

        <div v-if="certificates.length === 0" class="rounded-2xl border border-dashed bg-card p-12 text-center text-muted-foreground">
            You haven't earned any certificates yet.
        </div>

        <ul v-else class="divide-y rounded-2xl border bg-card">
            <li v-for="certificate in certificates" :key="certificate.serial_number" class="flex items-center justify-between p-4">
                <div>
                    <p class="font-semibold text-foreground">{{ certificate.course_title }}</p>
                    <p class="text-sm text-muted-foreground">Issued {{ new Date(certificate.issued_at).toLocaleDateString() }}</p>
                </div>
                <Link :href="route('certificates.show', certificate.serial_number)" class="text-sm font-medium text-sky-600 hover:underline">
                    View
                </Link>
            </li>
        </ul>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 8: Create `Certificates/Show.vue`**

Create `resources/js/Pages/Certificates/Show.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    certificate: { type: Object, required: true },
});

const issuedDate = new Date(props.certificate.issued_at).toLocaleDateString(undefined, {
    year: 'numeric', month: 'long', day: 'numeric',
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Certificate" />

        <div class="mx-auto max-w-3xl">
            <div class="mb-4 flex justify-end print:hidden">
                <button class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700" @click="window.print()">
                    Print / Save as PDF
                </button>
            </div>

            <div class="rounded-2xl border-4 border-double border-sky-700/40 bg-card p-12 text-center shadow-sm">
                <p class="text-sm uppercase tracking-widest text-muted-foreground">Certificate of Completion</p>
                <p class="mt-8 text-lg text-muted-foreground">This certifies that</p>
                <p class="mt-2 text-3xl font-bold text-foreground">{{ certificate.student_name }}</p>
                <p class="mt-6 text-lg text-muted-foreground">has successfully completed</p>
                <p class="mt-2 text-2xl font-semibold text-foreground">{{ certificate.course_title }}</p>
                <p v-if="certificate.final_grade !== null" class="mt-4 text-muted-foreground">Final grade: {{ certificate.final_grade }}</p>
                <p class="mt-8 text-sm text-muted-foreground">Issued {{ issuedDate }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Serial: {{ certificate.serial_number }}</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

> `window.print()` is used inline in the template's `@click`; Vue templates can reference the global `window` directly.

- [ ] **Step 9: Run test to verify it passes**

Run: `php artisan test --compact --filter=CertificateTest`
Expected: PASS (4 tests).

- [ ] **Step 10: Build + format + commit**

```bash
npm run build
vendor/bin/pint --dirty --format agent
git add app/Models/Certificate.php app/Policies/CertificatePolicy.php app/Http/Controllers/CertificateController.php routes/web.php resources/js/Pages/Certificates/Index.vue resources/js/Pages/Certificates/Show.vue tests/Feature/Certificates/CertificateTest.php
git commit -m "feat: student certificate index and printable view"
```

---

### Task 3: Public certificate verification

**Files:**
- Modify: `app/Http/Controllers/CertificateController.php` (add `verify`)
- Modify: `routes/web.php` (standalone public route, outside `auth`/`guest` groups)
- Create: `resources/js/Pages/Certificates/Verify.vue`
- Test: `tests/Feature/Certificates/CertificateVerifyTest.php`

**Interfaces:**
- Consumes: `Certificate` lookup by `serial_number`, `Enrollment.content_snapshot`.
- Produces: Route `certificates.verify` (GET `certificates/verify/{serial}`), public; renders `Certificates/Verify` with `{valid: bool, student_name?, course_title?, issued_at?, serial_number?}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Certificates/CertificateVerifyTest.php`:

```php
<?php

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('verifies a real certificate for a guest using the frozen snapshot title', function () {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create(['title' => 'Renamed Later']);
    $enrollment = Enrollment::factory()->completed()->for($student, 'student')->for($course)->create([
        'content_snapshot' => ['version' => 1, 'course' => ['title' => 'Title At Issue']],
    ]);
    $certificate = Certificate::factory()->create([
        'enrollment_id' => $enrollment->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
    ]);

    $this->get(route('certificates.verify', $certificate->serial_number))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Certificates/Verify')
            ->where('result.valid', true)
            ->where('result.course_title', 'Title At Issue')
            ->where('result.serial_number', $certificate->serial_number));
});

it('reports an unknown serial as invalid without a 404', function () {
    $this->get(route('certificates.verify', 'not-a-real-serial'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Certificates/Verify')
            ->where('result.valid', false));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CertificateVerifyTest`
Expected: FAIL — route `certificates.verify` not defined.

- [ ] **Step 3: Add the `verify` method to `CertificateController`**

In `app/Http/Controllers/CertificateController.php`, add:

```php
    public function verify(string $serial): Response
    {
        $certificate = Certificate::query()
            ->where('serial_number', $serial)
            ->with('student:id,first_name,last_name', 'enrollment', 'course:id,title')
            ->first();

        if ($certificate === null) {
            return Inertia::render('Certificates/Verify', [
                'result' => ['valid' => false],
            ]);
        }

        $snapshotTitle = $certificate->enrollment?->content_snapshot['course']['title'] ?? null;

        return Inertia::render('Certificates/Verify', [
            'result' => [
                'valid' => true,
                'student_name' => $certificate->student->name,
                'course_title' => $snapshotTitle ?? $certificate->course->title,
                'issued_at' => $certificate->issued_at?->toIso8601String(),
                'serial_number' => $certificate->serial_number,
            ],
        ]);
    }
```

- [ ] **Step 4: Register the public route**

In `routes/web.php`, add a standalone route OUTSIDE both the `auth` and `guest` groups (top level, so guests and logged-in users can reach it):

```php
Route::get('certificates/verify/{serial}', [CertificateController::class, 'verify'])->name('certificates.verify');
```

> Path has two segments after `certificates/`, so it never collides with the one-segment `certificates/{certificate}` show route.

- [ ] **Step 5: Create `Certificates/Verify.vue`**

Create `resources/js/Pages/Certificates/Verify.vue`:

```vue
<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    result: { type: Object, required: true },
});

const issuedDate = props.result.valid
    ? new Date(props.result.issued_at).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })
    : null;
</script>

<template>
    <GuestLayout>
        <Head title="Verify certificate" />

        <div v-if="result.valid" class="rounded-2xl border bg-card p-8 text-center">
            <p class="text-lg font-semibold text-emerald-600">✓ Verified certificate</p>
            <p class="mt-4 text-2xl font-bold text-foreground">{{ result.student_name }}</p>
            <p class="mt-1 text-muted-foreground">completed</p>
            <p class="mt-1 text-xl font-semibold text-foreground">{{ result.course_title }}</p>
            <p class="mt-4 text-sm text-muted-foreground">Issued {{ issuedDate }}</p>
            <p class="mt-1 text-xs text-muted-foreground">Serial: {{ result.serial_number }}</p>
        </div>

        <div v-else class="rounded-2xl border bg-card p-8 text-center">
            <p class="text-lg font-semibold text-rose-600">This is not a valid certificate</p>
            <p class="mt-2 text-sm text-muted-foreground">We couldn't find a certificate with that serial number.</p>
        </div>
    </GuestLayout>
</template>
```

> If `GuestLayout` requires a differently-named default slot or props, check `resources/js/Layouts/GuestLayout.vue` and adapt the wrapper to match (the existing auth pages use it).

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=CertificateVerifyTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Build + format + commit**

```bash
npm run build
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/CertificateController.php routes/web.php resources/js/Pages/Certificates/Verify.vue tests/Feature/Certificates/CertificateVerifyTest.php
git commit -m "feat: public certificate verification by serial"
```

---

### Task 4: "View certificate" link on My Courses

**Files:**
- Modify: `app/Http/Controllers/EnrollmentController.php` (index eager-load + mapping)
- Modify: `resources/js/Pages/Enrollments/Index.vue` (link)
- Test: `tests/Feature/Enrollments/EnrollmentTest.php` (add a case) OR a new assertion in `EnrollmentFilterTest`; use `EnrollmentTest`.

**Interfaces:**
- Consumes: `Enrollment::certificate()`.
- Produces: each My Courses row exposes `certificate_serial` (string|null).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Enrollments/EnrollmentTest.php` (append a new `it(...)`; keep existing imports — it already uses `Enrollment`, `User`, `AssertableInertia`, `RolePermissionSeeder`; add `App\Models\Certificate` and `App\Models\Course` imports if not present):

```php
it('exposes the certificate serial on a completed enrollment', function () {
    $student = App\Models\User::factory()->student()->create();
    $course = App\Models\Course::factory()->published()->create();
    $completed = App\Models\Enrollment::factory()->completed()->for($student, 'student')->for($course)->create();
    $certificate = App\Models\Certificate::factory()->create([
        'enrollment_id' => $completed->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
    ]);
    App\Models\Enrollment::factory()->for($student, 'student')->create(); // active, no certificate

    $this->actingAs($student)->get(route('enrollments.index'))
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->where('enrollments.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['certificate_serial'] === $certificate->serial_number,
            ) && collect($rows)->contains(fn ($row) => $row['certificate_serial'] === null)));
});
```

> If `EnrollmentTest.php` already imports these classes at the top with `use`, use the short names instead of the `App\Models\...` FQNs to match the file's style.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EnrollmentTest`
Expected: FAIL — `certificate_serial` key missing from the row array.

- [ ] **Step 3: Add the certificate to the index eager-load + mapping**

In `app/Http/Controllers/EnrollmentController.php@index`, change the query's `with(...)` to also load the certificate, and add `certificate_serial` to the mapped row:

```php
        $enrollments = $request->user()->enrollments()
            ->with('course:id,title,slug', 'certificate:id,enrollment_id,serial_number')
            ->withFilters($request->input('filters'))
            ->latest('enrolled_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Enrollment $enrollment): array => [
                'id' => $enrollment->id,
                'course_title' => $enrollment->course->title,
                'course_slug' => $enrollment->course->slug,
                'status' => $enrollment->status,
                'progress_percentage' => $enrollment->progress_percentage,
                'certificate_serial' => $enrollment->certificate?->serial_number,
            ]);
```

- [ ] **Step 4: Add the link in `Enrollments/Index.vue`**

In `resources/js/Pages/Enrollments/Index.vue`, in the table row (after the Progress cell), add a cell that links to the certificate when present. Add this `<TableCell>` inside the `<TableRow v-for=...>` block, and a matching `<TableHead></TableHead>` in the header row:

Header (add after the Progress `<TableHead>`):
```html
                        <TableHead class="w-32"></TableHead>
```

Row (add after the Progress `<TableCell>`):
```html
                        <TableCell>
                            <Link
                                v-if="enrollment.certificate_serial"
                                :href="route('certificates.show', enrollment.certificate_serial)"
                                class="text-sm font-medium text-sky-600 hover:underline"
                            >
                                View certificate
                            </Link>
                        </TableCell>
```

(`Link` is already imported in this page.)

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=EnrollmentTest`
Expected: PASS.

- [ ] **Step 6: Build + format + commit**

```bash
npm run build
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/EnrollmentController.php resources/js/Pages/Enrollments/Index.vue tests/Feature/Enrollments/EnrollmentTest.php
git commit -m "feat: link to earned certificate from My Courses"
```

---

### Task 5: Full-suite regression sweep

- [ ] **Step 1: Run the certificate + completion suites**

Run: `php artisan test --compact --filter=Certificate` then `php artisan test --compact --filter=CourseCompletion`
Expected: all PASS.

- [ ] **Step 2: Run the full suite**

Run: `php artisan test --compact`
Expected: PASS (no regressions — pay attention to existing lesson/enrollment tests, since `CompleteLesson` changed).

- [ ] **Step 3: Final build + format check**

```bash
npm run build
vendor/bin/pint --dirty --format agent
```
Expected: build succeeds, pint clean. If either produced changes, commit them:
```bash
git add -A && git commit -m "chore: build assets and format for certificates slice"
```

---

## Self-Review

**Spec coverage:**
- Automatic completion trigger on last lesson (Active guard, empty-course guard) → Task 1. ✓
- Certificate issued via existing `CompleteCourse` (idempotent) → Task 1 (calls it) + verified by tests. ✓
- `serial_number` route key → Task 2 Step 3. ✓
- `CertificatePolicy::view` owner/admin → Task 2 Step 4. ✓
- Authed `certificates.index` + `certificates.show` + pages → Task 2. ✓
- Public `certificates.verify` from frozen snapshot, invalid = 200 → Task 3. ✓
- My Courses certificate link → Task 4. ✓
- `final_grade` hidden when null → Task 2 Step 8 (`v-if`), passed as null in tests. ✓
- No new dependency; print-to-PDF → Task 2 Step 8 (`window.print()`). ✓
- Snapshot-title authenticity (rename course, verify shows original) → Task 3 Step 1 test. ✓
- Out of scope (server PDF, completion notification, grade computation) → not planned. ✓

**Placeholder scan:** No TBD/TODO; every code step shows full code. ✓

**Type consistency:** `certificateProps()`/`verify()` emit the same field names the Vue pages and tests read (`student_name`, `course_title`, `issued_at`, `serial_number`, `final_grade`; verify wraps in `result.valid`); `certificate_serial` used consistently in Task 4 controller + Vue + test; `CompleteCourse::run` signature matches Task 1 usage; route names (`certificates.index/show/verify`) consistent across tasks. ✓
