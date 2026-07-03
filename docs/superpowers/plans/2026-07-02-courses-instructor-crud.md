# Courses (Instructor CRUD) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let instructors and admins manage courses (list, create, edit, update, publish, archive, delete) through the UI, guarded by a `CoursePolicy`, establishing the Action→Controller→Policy→Vue pattern for later slices.

**Architecture:** Thin controllers authorize via `CoursePolicy` and delegate; validation lives in Form Requests; status transitions are Loris Leiva Actions (`PublishCourse`, `ArchiveCourse`); Vue pages use Inertia `useForm`. Ownership + seeded Spatie permissions drive authorization; admins bypass via the existing `Gate::before`.

**Tech Stack:** Laravel 13, Inertia Laravel v3, Vue 3, Inertia Vue v3, Tailwind v4, Spatie Permission, Loris Leiva Actions, Pest 4.

## Global Constraints

- PHP 8.4. Constructor property promotion; explicit return types and param type hints on every method.
- Naming (per CLAUDE.md): variables `snake_case`, methods/functions `camelCase`, classes `TitleCase`. Enum keys `TitleCase`.
- Curly braces on all control structures, even single-line bodies.
- No new Composer/npm dependencies.
- Create files with `php artisan make:` where a generator exists; pass `--no-interaction`.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes, before each commit.
- Tests are Pest feature tests. Run with `php artisan test --compact`.
- Prefer named routes and the `route()` helper for URL generation.
- Vue components have a single root element.
- **Course tests must seed `RolePermissionSeeder`** in a `beforeEach` — `RefreshDatabase` does not seed, and factory-assigned roles otherwise carry no permissions, so `$user->can('create courses')` would be false.
- Enum values are TitleCase strings: `CourseLevel` = Beginner/Intermediate/Advanced; `CourseStatus` = Draft/Published/Archived.
- Commit message trailer: `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.

## File Structure

**New — PHP:**
- `app/Policies/CoursePolicy.php` — authorization for every course ability
- `app/Actions/PublishCourse.php` — status→Published + published_at stamp
- `app/Actions/ArchiveCourse.php` — status→Archived
- `app/Http/Requests/Course/StoreCourseRequest.php` — create validation
- `app/Http/Requests/Course/UpdateCourseRequest.php` — update validation
- `app/Http/Controllers/CourseController.php` — resourceful CRUD (no `show`)
- `app/Http/Controllers/PublishCourseController.php` — invokable publish endpoint
- `app/Http/Controllers/ArchiveCourseController.php` — invokable archive endpoint

**New — Vue:**
- `resources/js/Pages/Courses/Index.vue`
- `resources/js/Pages/Courses/Create.vue`
- `resources/js/Pages/Courses/Edit.vue`
- `resources/js/Components/CourseForm.vue`

**New — Tests:**
- `tests/Feature/Courses/CourseAuthorizationTest.php`
- `tests/Feature/Courses/CourseManagementTest.php`

**Modified:**
- `app/Http/Controllers/Controller.php` — add `AuthorizesRequests` trait
- `app/Http/Middleware/HandleInertiaRequests.php` — add `auth.user.can` map
- `routes/web.php` — course routes
- `resources/js/Layouts/AuthenticatedLayout.vue` — "Courses" nav link

---

### Task 1: CoursePolicy + shared `can` map

**Files:**
- Create: `app/Policies/CoursePolicy.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/Courses/CourseAuthorizationTest.php`

**Interfaces:**
- Produces: `CoursePolicy` with `viewAny(User)`, `view(User, Course)`, `create(User)`, `update(User, Course)`, `delete(User, Course)`, `publish(User, Course)`, `archive(User, Course)`, all `: bool`. Auto-discovered by Laravel for `Course`.
- Produces: shared prop `auth.user.can.create_courses` (bool).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Courses/CourseAuthorizationTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('an instructor can update their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();

    expect($instructor->can('update', $course))->toBeTrue();
});

test('an instructor cannot update another instructors course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $other = User::factory()->instructor()->create();
    $course = Course::factory()->for($other, 'instructor')->create();

    expect($instructor->can('update', $course))->toBeFalse();
});

test('a student cannot create courses', function (): void {
    $student = User::factory()->student()->create();

    expect($student->can('create', Course::class))->toBeFalse();
});

test('an admin can update any course', function (): void {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();

    expect($admin->can('update', $course))->toBeTrue();
});

test('an admin can publish any course', function (): void {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();

    expect($admin->can('publish', $course))->toBeTrue();
});

test('instructors have the create_courses ability shared to inertia', function (): void {
    $instructor = User::factory()->instructor()->create();

    $this->actingAs($instructor)
        ->get('/')
        ->assertInertia(fn ($page) => $page->where('auth.user.can.create_courses', true));
});

test('students do not have the create_courses ability', function (): void {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->get('/')
        ->assertInertia(fn ($page) => $page->where('auth.user.can.create_courses', false));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=CourseAuthorizationTest`
Expected: FAIL — `CoursePolicy` does not exist / `auth.user.can` missing.

- [ ] **Step 3: Create the CoursePolicy**

Create `app/Policies/CoursePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('create courses');
    }

    public function view(User $user, Course $course): bool
    {
        return $user->can('create courses') || $course->instructor_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('create courses');
    }

    public function update(User $user, Course $course): bool
    {
        return $user->can('update courses') && $course->instructor_id === $user->id;
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->can('delete courses') && $course->instructor_id === $user->id;
    }

    public function publish(User $user, Course $course): bool
    {
        return $user->can('publish courses') && $course->instructor_id === $user->id;
    }

    public function archive(User $user, Course $course): bool
    {
        return $user->can('publish courses') && $course->instructor_id === $user->id;
    }
}
```

Note: no manual registration needed — Laravel auto-discovers `App\Policies\CoursePolicy` for `App\Models\Course`. Admins short-circuit every method via the existing `Gate::before` in `AppServiceProvider`.

- [ ] **Step 4: Add the `can` map to shared Inertia data**

In `app/Http/Middleware/HandleInertiaRequests.php`, extend the `auth.user` array (add the `can` key alongside `roles`):

```php
'auth' => [
    'user' => $request->user()
        ? [
            ...$request->user()->only('id', 'name', 'email'),
            'roles' => $request->user()->getRoleNames()->all(),
            'can' => [
                'create_courses' => $request->user()->can('create courses'),
            ],
        ]
        : null,
],
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=CourseAuthorizationTest`
Expected: PASS (7 tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/CoursePolicy.php app/Http/Middleware/HandleInertiaRequests.php tests/Feature/Courses/CourseAuthorizationTest.php
git commit -m "Add CoursePolicy and shared course abilities

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Status transition Actions

**Files:**
- Create: `app/Actions/PublishCourse.php`, `app/Actions/ArchiveCourse.php`
- Test: `tests/Feature/Courses/CourseManagementTest.php` (created here, extended in later tasks)

**Interfaces:**
- Produces: `PublishCourse::handle(Course $course): Course` (also callable as `PublishCourse::run($course)`) — sets `status = CourseStatus::Published`, `published_at = now()` if not already set.
- Produces: `ArchiveCourse::handle(Course $course): Course` — sets `status = CourseStatus::Archived`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Courses/CourseManagementTest.php`:

```php
<?php

use App\Actions\ArchiveCourse;
use App\Actions\PublishCourse;
use App\Enums\CourseStatus;
use App\Models\Course;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('publishing a draft course sets status and stamps published_at', function (): void {
    $course = Course::factory()->create([
        'status' => CourseStatus::Draft,
        'published_at' => null,
    ]);

    $result = PublishCourse::run($course);

    expect($result->status)->toBe(CourseStatus::Published)
        ->and($result->published_at)->not->toBeNull();
});

test('publishing does not overwrite an existing published_at', function (): void {
    $original = now()->subWeek();
    $course = Course::factory()->create([
        'status' => CourseStatus::Draft,
        'published_at' => $original,
    ]);

    PublishCourse::run($course);

    expect($course->fresh()->published_at->timestamp)->toBe($original->timestamp);
});

test('archiving a course sets status to archived', function (): void {
    $course = Course::factory()->published()->create();

    $result = ArchiveCourse::run($course);

    expect($result->status)->toBe(CourseStatus::Archived);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=CourseManagementTest`
Expected: FAIL — `App\Actions\PublishCourse` not found.

- [ ] **Step 3: Create PublishCourse**

Create `app/Actions/PublishCourse.php`:

```php
<?php

namespace App\Actions;

use App\Enums\CourseStatus;
use App\Models\Course;
use Lorisleiva\Actions\Concerns\AsAction;

class PublishCourse
{
    use AsAction;

    /**
     * Publish a course, stamping the first publication time.
     */
    public function handle(Course $course): Course
    {
        $course->update([
            'status' => CourseStatus::Published,
            'published_at' => $course->published_at ?? now(),
        ]);

        return $course;
    }
}
```

- [ ] **Step 4: Create ArchiveCourse**

Create `app/Actions/ArchiveCourse.php`:

```php
<?php

namespace App\Actions;

use App\Enums\CourseStatus;
use App\Models\Course;
use Lorisleiva\Actions\Concerns\AsAction;

class ArchiveCourse
{
    use AsAction;

    /**
     * Archive a course so it is retired from browsing.
     */
    public function handle(Course $course): Course
    {
        $course->update([
            'status' => CourseStatus::Archived,
        ]);

        return $course;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=CourseManagementTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/PublishCourse.php app/Actions/ArchiveCourse.php tests/Feature/Courses/CourseManagementTest.php
git commit -m "Add PublishCourse and ArchiveCourse actions

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Course CRUD controller, requests, and routes

**Files:**
- Modify: `app/Http/Controllers/Controller.php` (add `AuthorizesRequests`)
- Create: `app/Http/Requests/Course/StoreCourseRequest.php`, `app/Http/Requests/Course/UpdateCourseRequest.php`
- Create: `app/Http/Controllers/CourseController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Courses/CourseManagementTest.php`

**Interfaces:**
- Consumes: `CoursePolicy` (Task 1).
- Produces: named routes `courses.index`, `courses.create`, `courses.store`, `courses.edit`, `courses.update`, `courses.destroy`. `CourseController` renders `Courses/Index`, `Courses/Create`, `Courses/Edit`; `create`/`edit` pass a `levels` prop of `array<int, array{value: string, label: string}>`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Courses/CourseManagementTest.php`:

```php
use App\Enums\CourseLevel;
use App\Models\User;

test('an instructor sees only their own courses on the index', function (): void {
    $instructor = User::factory()->instructor()->create();
    Course::factory()->for($instructor, 'instructor')->create(['title' => 'Mine']);
    Course::factory()->create(['title' => 'Someone elses']);

    $this->actingAs($instructor)
        ->get(route('courses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Courses/Index')
            ->has('courses', 1)
            ->where('courses.0.title', 'Mine')
        );
});

test('an admin sees every course on the index', function (): void {
    $admin = User::factory()->admin()->create();
    Course::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('courses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('courses', 3));
});

test('an instructor can store a new draft course', function (): void {
    $instructor = User::factory()->instructor()->create();

    $this->actingAs($instructor)
        ->post(route('courses.store'), [
            'title' => 'Intro to Testing',
            'summary' => 'A short summary',
            'description' => 'A longer description',
            'level' => CourseLevel::Beginner->value,
        ])
        ->assertRedirect(route('courses.index'));

    $course = Course::where('title', 'Intro to Testing')->sole();
    expect($course->instructor_id)->toBe($instructor->id)
        ->and($course->status)->toBe(CourseStatus::Draft)
        ->and($course->slug)->toBe('intro-to-testing');
});

test('storing a course with a duplicate title gets a unique slug', function (): void {
    $instructor = User::factory()->instructor()->create();
    Course::factory()->create(['title' => 'Duplicate', 'slug' => 'duplicate']);

    $this->actingAs($instructor)->post(route('courses.store'), [
        'title' => 'Duplicate',
        'level' => CourseLevel::Beginner->value,
    ])->assertRedirect(route('courses.index'));

    expect(Course::where('slug', 'duplicate-2')->exists())->toBeTrue();
});

test('storing a course requires a title and level', function (): void {
    $instructor = User::factory()->instructor()->create();

    $this->actingAs($instructor)
        ->post(route('courses.store'), ['title' => '', 'level' => ''])
        ->assertSessionHasErrors(['title', 'level']);
});

test('an instructor can update their own course without changing the slug', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create([
        'title' => 'Original',
        'slug' => 'original',
    ]);

    $this->actingAs($instructor)
        ->put(route('courses.update', $course), [
            'title' => 'Renamed',
            'level' => CourseLevel::Advanced->value,
        ])
        ->assertRedirect(route('courses.index'));

    $course->refresh();
    expect($course->title)->toBe('Renamed')
        ->and($course->slug)->toBe('original');
});

test('an instructor can soft delete their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();

    $this->actingAs($instructor)
        ->delete(route('courses.destroy', $course))
        ->assertRedirect(route('courses.index'));

    expect($course->fresh()->trashed())->toBeTrue();
});

test('a student cannot access the course index', function (): void {
    $student = User::factory()->student()->create();

    $this->actingAs($student)->get(route('courses.index'))->assertForbidden();
});

test('a student cannot store a course', function (): void {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->post(route('courses.store'), ['title' => 'X', 'level' => CourseLevel::Beginner->value])
        ->assertForbidden();
});

test('an instructor cannot update another instructors course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create(['title' => 'Theirs']);

    $this->actingAs($instructor)
        ->put(route('courses.update', $course), [
            'title' => 'Hacked',
            'level' => CourseLevel::Beginner->value,
        ])
        ->assertForbidden();
});

test('guests are redirected from the course index to login', function (): void {
    $this->get(route('courses.index'))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=CourseManagementTest`
Expected: FAIL — route `courses.index` not defined.

- [ ] **Step 3: Add the AuthorizesRequests trait to the base controller**

Replace `app/Http/Controllers/Controller.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;
}
```

- [ ] **Step 4: Create StoreCourseRequest**

Create `app/Http/Requests/Course/StoreCourseRequest.php`:

```php
<?php

namespace App\Http\Requests\Course;

use App\Enums\CourseLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
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
            'summary' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'level' => ['required', Rule::enum(CourseLevel::class)],
        ];
    }
}
```

- [ ] **Step 5: Create UpdateCourseRequest**

Create `app/Http/Requests/Course/UpdateCourseRequest.php`:

```php
<?php

namespace App\Http\Requests\Course;

use App\Enums\CourseLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
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
            'summary' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'level' => ['required', Rule::enum(CourseLevel::class)],
        ];
    }
}
```

- [ ] **Step 6: Create CourseController**

Create `app/Http/Controllers/CourseController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\UserRole;
use App\Http\Requests\Course\StoreCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Course::class);

        $user = $request->user();

        $courses = Course::query()
            ->when(
                ! $user->hasRole(UserRole::Admin->value),
                fn ($query) => $query->where('instructor_id', $user->id),
            )
            ->latest()
            ->get(['id', 'title', 'slug', 'status', 'level']);

        return Inertia::render('Courses/Index', [
            'courses' => $courses,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Course::class);

        return Inertia::render('Courses/Create', [
            'levels' => $this->levelOptions(),
        ]);
    }

    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $this->authorize('create', Course::class);

        $validated = $request->validated();

        Course::create([
            ...$validated,
            'instructor_id' => $request->user()->id,
            'slug' => $this->uniqueSlug($validated['title']),
            'status' => CourseStatus::Draft,
        ]);

        return redirect()->route('courses.index')->with('status', 'Course created.');
    }

    public function edit(Course $course): Response
    {
        $this->authorize('update', $course);

        return Inertia::render('Courses/Edit', [
            'course' => $course->only('title', 'slug', 'summary', 'description', 'level'),
            'levels' => $this->levelOptions(),
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        $course->update($request->validated());

        return redirect()->route('courses.index')->with('status', 'Course updated.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $this->authorize('delete', $course);

        $course->delete();

        return redirect()->route('courses.index')->with('status', 'Course deleted.');
    }

    /**
     * Build a unique slug for a new course from its title.
     */
    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (Course::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * The selectable course levels for form dropdowns.
     *
     * @return array<int, array{value: string, label: string}>
     */
    protected function levelOptions(): array
    {
        return array_map(
            fn (CourseLevel $level): array => ['value' => $level->value, 'label' => $level->name],
            CourseLevel::cases(),
        );
    }
}
```

- [ ] **Step 7: Add the routes**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\CourseController;
```

Inside the existing `Route::middleware('auth')->group(...)`, add (the resource excludes `show`, deferred to the student slice):

```php
Route::resource('courses', CourseController::class)->except('show');
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=CourseManagementTest`
Expected: PASS (all Task 2 + Task 3 tests).

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Controller.php app/Http/Controllers/CourseController.php app/Http/Requests/Course routes/web.php tests/Feature/Courses/CourseManagementTest.php
git commit -m "Add course CRUD controller, requests, and routes

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Publish and Archive endpoints

**Files:**
- Create: `app/Http/Controllers/PublishCourseController.php`, `app/Http/Controllers/ArchiveCourseController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Courses/CourseManagementTest.php`

**Interfaces:**
- Consumes: `PublishCourse`/`ArchiveCourse` Actions (Task 2), `CoursePolicy` (Task 1).
- Produces: named routes `courses.publish`, `courses.archive` (both POST).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Courses/CourseManagementTest.php`:

```php
test('an instructor can publish their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create([
        'status' => CourseStatus::Draft,
        'published_at' => null,
    ]);

    $this->actingAs($instructor)
        ->post(route('courses.publish', $course))
        ->assertRedirect();

    $course->refresh();
    expect($course->status)->toBe(CourseStatus::Published)
        ->and($course->published_at)->not->toBeNull();
});

test('an instructor can archive their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->published()->create();

    $this->actingAs($instructor)
        ->post(route('courses.archive', $course))
        ->assertRedirect();

    expect($course->fresh()->status)->toBe(CourseStatus::Archived);
});

test('an instructor cannot publish another instructors course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();

    $this->actingAs($instructor)
        ->post(route('courses.publish', $course))
        ->assertForbidden();
});

test('a student cannot publish a course', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->create();

    $this->actingAs($student)
        ->post(route('courses.publish', $course))
        ->assertForbidden();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=CourseManagementTest`
Expected: FAIL — route `courses.publish` not defined.

- [ ] **Step 3: Create PublishCourseController**

Create `app/Http/Controllers/PublishCourseController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Actions\PublishCourse;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;

class PublishCourseController extends Controller
{
    public function __invoke(Course $course): RedirectResponse
    {
        $this->authorize('publish', $course);

        PublishCourse::run($course);

        return back()->with('status', 'Course published.');
    }
}
```

- [ ] **Step 4: Create ArchiveCourseController**

Create `app/Http/Controllers/ArchiveCourseController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Actions\ArchiveCourse;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;

class ArchiveCourseController extends Controller
{
    public function __invoke(Course $course): RedirectResponse
    {
        $this->authorize('archive', $course);

        ArchiveCourse::run($course);

        return back()->with('status', 'Course archived.');
    }
}
```

- [ ] **Step 5: Add the routes**

In `routes/web.php`, add the imports:

```php
use App\Http\Controllers\ArchiveCourseController;
use App\Http\Controllers\PublishCourseController;
```

Inside the `auth` group, right after the `Route::resource('courses', ...)` line:

```php
Route::post('courses/{course}/publish', PublishCourseController::class)->name('courses.publish');
Route::post('courses/{course}/archive', ArchiveCourseController::class)->name('courses.archive');
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=CourseManagementTest`
Expected: PASS (all course management tests).

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/PublishCourseController.php app/Http/Controllers/ArchiveCourseController.php routes/web.php tests/Feature/Courses/CourseManagementTest.php
git commit -m "Add course publish and archive endpoints

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: Vue pages, form, and nav link

**Files:**
- Create: `resources/js/Components/CourseForm.vue`, `resources/js/Pages/Courses/Index.vue`, `resources/js/Pages/Courses/Create.vue`, `resources/js/Pages/Courses/Edit.vue`
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`

**Interfaces:**
- Consumes: `courses.*` routes (Tasks 3-4); `levels` prop (`{ value, label }[]`); `auth.user.can.create_courses`.

- [ ] **Step 1: Create the shared CourseForm component**

Create `resources/js/Components/CourseForm.vue`:

```vue
<script setup>
defineProps({
    form: {
        type: Object,
        required: true,
    },
    levels: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <div class="space-y-4">
        <div>
            <label for="title" class="block text-sm font-medium">Title</label>
            <input
                id="title"
                v-model="form.title"
                type="text"
                required
                class="mt-1 block w-full rounded border-gray-300 shadow-sm"
            />
            <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
        </div>

        <div>
            <label for="level" class="block text-sm font-medium">Level</label>
            <select
                id="level"
                v-model="form.level"
                required
                class="mt-1 block w-full rounded border-gray-300 shadow-sm"
            >
                <option value="" disabled>Select a level</option>
                <option v-for="level in levels" :key="level.value" :value="level.value">
                    {{ level.label }}
                </option>
            </select>
            <p v-if="form.errors.level" class="mt-1 text-sm text-red-600">{{ form.errors.level }}</p>
        </div>

        <div>
            <label for="summary" class="block text-sm font-medium">Summary</label>
            <input
                id="summary"
                v-model="form.summary"
                type="text"
                class="mt-1 block w-full rounded border-gray-300 shadow-sm"
            />
            <p v-if="form.errors.summary" class="mt-1 text-sm text-red-600">{{ form.errors.summary }}</p>
        </div>

        <div>
            <label for="description" class="block text-sm font-medium">Description</label>
            <textarea
                id="description"
                v-model="form.description"
                rows="6"
                class="mt-1 block w-full rounded border-gray-300 shadow-sm"
            />
            <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Create the Index page**

Create `resources/js/Pages/Courses/Index.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    courses: {
        type: Array,
        required: true,
    },
});

const canCreate = computed(() => usePage().props.auth.user.can?.create_courses ?? false);

const destroy = (course) => {
    if (confirm(`Delete "${course.title}"?`)) {
        router.delete(route('courses.destroy', course.slug));
    }
};

const publish = (course) => {
    router.post(route('courses.publish', course.slug));
};

const archive = (course) => {
    router.post(route('courses.archive', course.slug));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Courses" />

        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Courses</h1>
            <Link
                v-if="canCreate"
                :href="route('courses.create')"
                class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white"
            >
                New course
            </Link>
        </div>

        <div v-if="courses.length === 0" class="rounded border border-dashed p-8 text-center text-gray-500">
            No courses yet.
        </div>

        <table v-else class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="border-b text-gray-500">
                    <th class="py-2">Title</th>
                    <th class="py-2">Level</th>
                    <th class="py-2">Status</th>
                    <th class="py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="course in courses" :key="course.id" class="border-b">
                    <td class="py-3 font-medium">{{ course.title }}</td>
                    <td class="py-3">{{ course.level }}</td>
                    <td class="py-3">
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs">{{ course.status }}</span>
                    </td>
                    <td class="py-3">
                        <div class="flex justify-end gap-3">
                            <Link :href="route('courses.edit', course.slug)" class="text-blue-600 hover:underline">Edit</Link>
                            <button type="button" class="text-green-600 hover:underline" @click="publish(course)">Publish</button>
                            <button type="button" class="text-amber-600 hover:underline" @click="archive(course)">Archive</button>
                            <button type="button" class="text-red-600 hover:underline" @click="destroy(course)">Delete</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 3: Create the Create page**

Create `resources/js/Pages/Courses/Create.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CourseForm from '@/Components/CourseForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    levels: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    title: '',
    summary: '',
    description: '',
    level: '',
});

const submit = () => {
    form.post(route('courses.store'));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="New course" />

        <h1 class="mb-6 text-2xl font-semibold">New course</h1>

        <form class="max-w-2xl" @submit.prevent="submit">
            <CourseForm :form="form" :levels="levels" />

            <div class="mt-6 flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Create course
                </button>
                <Link :href="route('courses.index')" class="text-sm text-gray-600 hover:underline">Cancel</Link>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 4: Create the Edit page**

Create `resources/js/Pages/Courses/Edit.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CourseForm from '@/Components/CourseForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    course: {
        type: Object,
        required: true,
    },
    levels: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    title: props.course.title,
    summary: props.course.summary ?? '',
    description: props.course.description ?? '',
    level: props.course.level ?? '',
});

const submit = () => {
    form.put(route('courses.update', props.course.slug));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Edit course" />

        <h1 class="mb-6 text-2xl font-semibold">Edit course</h1>

        <form class="max-w-2xl" @submit.prevent="submit">
            <CourseForm :form="form" :levels="levels" />

            <div class="mt-6 flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Save changes
                </button>
                <Link :href="route('courses.index')" class="text-sm text-gray-600 hover:underline">Cancel</Link>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 5: Add the Courses nav link**

In `resources/js/Layouts/AuthenticatedLayout.vue`, update the `<script setup>` to expose the ability and add the link. Replace the nav's inner links block so it reads:

```vue
<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const user = computed(() => usePage().props.auth.user);
const canCreateCourses = computed(() => user.value.can?.create_courses ?? false);
</script>
```

Then in the template, add a Courses link before the user's name (inside the existing right-hand `<div class="flex items-center gap-4">`, or as a left-nav item next to the LMS brand). Place it right after the brand `Link`:

```vue
<Link
    v-if="canCreateCourses"
    :href="route('courses.index')"
    class="text-sm text-gray-600 hover:underline"
>
    Courses
</Link>
```

- [ ] **Step 6: Build the frontend**

Run: `npm run build`
Expected: build succeeds; `Index`, `Create`, `Edit`, and `CourseForm` all compile with no unresolved imports.

- [ ] **Step 7: Run the full test suite**

Run: `php artisan test --compact`
Expected: PASS — all auth, dashboard, and course tests.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/js
git commit -m "Add course management Vue pages and nav link

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review Notes

- **Spec coverage:** CoursePolicy + all 7 abilities (Task 1); `can` map (Task 1); PublishCourse/ArchiveCourse Actions (Task 2); CRUD controller with ownership-scoped index, slug generation, soft delete, FormRequests (Task 3); publish/archive endpoints (Task 4); Index/Create/Edit/CourseForm + nav link (Task 5). Tests for authorization, ownership, and each transition are spread across Tasks 1-4.
- **Seeding gotcha:** every course test file seeds `RolePermissionSeeder` in `beforeEach` so factory roles carry their permissions — called out in Global Constraints and present in both test files.
- **AuthorizesRequests:** base controller lacked the trait; added in Task 3 Step 3 before any `$this->authorize()` call is exercised.
- **Slug on update:** `UpdateCourseRequest` has no `slug` field and `update()` only writes validated data, so the slug stays stable — asserted by the "without changing the slug" test.
- **Route-model binding:** Course binds by `slug` (`getRouteKeyName`), so Vue passes `course.slug` to `route()` helpers — consistent across Index actions and Edit.
- **Type consistency:** `levelOptions()` returns `{value, label}` (Task 3) and the Vue `CourseForm`/Create/Edit consume exactly those keys (Task 5). Action method names `PublishCourse::run`/`ArchiveCourse::run` match between Tasks 2 and 4.
- **`@` alias:** already proven to resolve during the auth slice build, so `@/Components/...` and `@/Layouts/...` are safe.
```
