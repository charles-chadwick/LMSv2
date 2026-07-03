# Course Content Authoring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A curriculum builder where a course's instructor (or admin) creates/edits/reorders/deletes modules and lessons (drag-and-drop, rich-text lesson content sanitized server-side), plus the global lesson-slug uniqueness fix.

**Architecture:** Thin authoring controllers under `App\Http\Controllers\Curriculum\` authorize a new `manageContent` ability on `CoursePolicy` and delegate; reordering is done by two Loris Leiva actions; lesson HTML is sanitized on write by a `Lesson::content` mutator (mews/purifier); the builder is one Inertia page using vuedraggable and a TipTap rich-text component.

**Tech Stack:** Laravel 13, Inertia v3, Vue 3, Tailwind v4, Spatie Permission, Loris Leiva Actions, mews/purifier, TipTap, vuedraggable, Pest 4.

## Global Constraints

- PHP 8.4. Explicit return types + param type hints on every method; curly braces on all control structures.
- Naming: variables snake_case, methods camelCase, classes TitleCase. Enum keys TitleCase.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes, before each commit.
- Pest feature tests; `php artisan test --compact`; output must be pristine.
- **Course/authoring tests must seed `RolePermissionSeeder`** in a `beforeEach` — RefreshDatabase does not seed; `manageContent` relies on the `manage course content` permission.
- Prefer named routes and the `route()` helper. `{course}` and `{lesson}` bind by slug; `{module}` binds by id.
- Authoring routes live inside the existing `auth`→`verified` group in `routes/web.php`.
- The authoring `Curriculum\LessonController` is imported in `routes/web.php` aliased as `CurriculumLessonController` (the student `App\Http\Controllers\LessonController` is already imported).
- Lesson slug generation checks `withTrashed()` (soft-deleted lessons keep their row and count against the global unique index).
- Vue components single root element; `@` alias; match sibling pages.
- New deps (approved): npm `vuedraggable@next`, `@tiptap/vue-3`, `@tiptap/starter-kit`, `@tiptap/extension-link`; composer `mews/purifier`.
- Commit trailer: `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.

## File Structure

**New — PHP:** `app/Policies` (extend), `app/Actions/ReorderModules.php`, `app/Actions/ReorderLessons.php`,
`app/Http/Controllers/Curriculum/{CurriculumController,ModuleController,LessonController}.php`,
`app/Http/Requests/Curriculum/{StoreModuleRequest,UpdateModuleRequest,StoreLessonRequest,UpdateLessonRequest}.php`,
`config/purifier.php`, `database/migrations/<ts>_make_lesson_slug_globally_unique.php`.

**New — Vue:** `resources/js/Pages/Curriculum/Show.vue`, `resources/js/Components/RichTextEditor.vue`.

**New — Tests:** `tests/Feature/Curriculum/{ManageContentPolicyTest,ReorderActionsTest,ModuleAuthoringTest,LessonContentSanitizationTest,LessonAuthoringTest,CurriculumBuilderTest}.php`.

**Modified:** `app/Models/Lesson.php`, `app/Policies/CoursePolicy.php`, `routes/web.php`,
`resources/js/Pages/Courses/Index.vue`, `resources/js/Pages/Lessons/Show.vue`, `package.json`, `composer.json`.

---

### Task 1: `manageContent` policy

**Files:**
- Modify: `app/Policies/CoursePolicy.php`
- Test: `tests/Feature/Curriculum/ManageContentPolicyTest.php`

**Interfaces:**
- Produces: `CoursePolicy::manageContent(User, Course): bool` (`manage course content` permission AND ownership; admins via `Gate::before`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Curriculum/ManageContentPolicyTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the owning instructor can manage content', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();

    expect($instructor->can('manageContent', $course))->toBeTrue();
});

test('a non-owner instructor cannot manage content', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();

    expect($instructor->can('manageContent', $course))->toBeFalse();
});

test('a student cannot manage content', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->create();

    expect($student->can('manageContent', $course))->toBeFalse();
});

test('an admin can manage content on any course', function (): void {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();

    expect($admin->can('manageContent', $course))->toBeTrue();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=ManageContentPolicyTest`
Expected: FAIL — `manageContent` undefined.

- [ ] **Step 3: Add the method**

In `app/Policies/CoursePolicy.php`, add after `learn`:

```php
public function manageContent(User $user, Course $course): bool
{
    return $user->can('manage course content') && $course->instructor_id === $user->id;
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=ManageContentPolicyTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/CoursePolicy.php tests/Feature/Curriculum/ManageContentPolicyTest.php
git commit -m "Add manageContent course ability

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Reorder actions

**Files:**
- Create: `app/Actions/ReorderModules.php`, `app/Actions/ReorderLessons.php`
- Test: `tests/Feature/Curriculum/ReorderActionsTest.php`

**Interfaces:**
- Produces: `ReorderModules::handle(Course $course, array $ordered_module_ids): void` and `ReorderLessons::handle(Module $module, array $ordered_lesson_ids): void`. Both throw `Illuminate\Validation\ValidationException` if the id set does not exactly equal the parent's children ids; otherwise rewrite `position` = array index. Callable via `::run(...)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Curriculum/ReorderActionsTest.php`:

```php
<?php

use App\Actions\ReorderLessons;
use App\Actions\ReorderModules;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Validation\ValidationException;

test('reorder modules rewrites positions to the given order', function (): void {
    $course = Course::factory()->create();
    $a = Module::factory()->for($course)->create(['position' => 0]);
    $b = Module::factory()->for($course)->create(['position' => 1]);
    $c = Module::factory()->for($course)->create(['position' => 2]);

    ReorderModules::run($course, [$c->id, $a->id, $b->id]);

    expect($c->fresh()->position)->toBe(0)
        ->and($a->fresh()->position)->toBe(1)
        ->and($b->fresh()->position)->toBe(2);
});

test('reorder modules rejects an id from another course', function (): void {
    $course = Course::factory()->create();
    $a = Module::factory()->for($course)->create(['position' => 0]);
    $foreign = Module::factory()->create();

    expect(fn () => ReorderModules::run($course, [$a->id, $foreign->id]))
        ->toThrow(ValidationException::class);

    expect($a->fresh()->position)->toBe(0);
});

test('reorder lessons rewrites positions to the given order', function (): void {
    $module = Module::factory()->create();
    $a = Lesson::factory()->for($module)->create(['position' => 0]);
    $b = Lesson::factory()->for($module)->create(['position' => 1]);

    ReorderLessons::run($module, [$b->id, $a->id]);

    expect($b->fresh()->position)->toBe(0)
        ->and($a->fresh()->position)->toBe(1);
});

test('reorder lessons rejects an id from another module', function (): void {
    $module = Module::factory()->create();
    $a = Lesson::factory()->for($module)->create(['position' => 0]);
    $foreign = Lesson::factory()->create();

    expect(fn () => ReorderLessons::run($module, [$a->id, $foreign->id]))
        ->toThrow(ValidationException::class);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=ReorderActionsTest`
Expected: FAIL — action classes not found.

- [ ] **Step 3: Create ReorderModules**

Create `app/Actions/ReorderModules.php`:

```php
<?php

namespace App\Actions;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class ReorderModules
{
    use AsAction;

    /**
     * Rewrite module positions to the given order.
     *
     * @param  array<int, int>  $ordered_module_ids
     *
     * @throws ValidationException
     */
    public function handle(Course $course, array $ordered_module_ids): void
    {
        $actual_ids = $course->modules()->pluck('id')->all();

        if (! $this->isSameSet($actual_ids, $ordered_module_ids)) {
            throw ValidationException::withMessages([
                'modules' => 'The provided modules do not match this course.',
            ]);
        }

        foreach ($ordered_module_ids as $position => $module_id) {
            Module::whereKey($module_id)->update(['position' => $position]);
        }
    }

    /**
     * @param  array<int, int>  $a
     * @param  array<int, int>  $b
     */
    protected function isSameSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }
}
```

- [ ] **Step 4: Create ReorderLessons**

Create `app/Actions/ReorderLessons.php`:

```php
<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class ReorderLessons
{
    use AsAction;

    /**
     * Rewrite lesson positions within a module to the given order.
     *
     * @param  array<int, int>  $ordered_lesson_ids
     *
     * @throws ValidationException
     */
    public function handle(Module $module, array $ordered_lesson_ids): void
    {
        $actual_ids = $module->lessons()->pluck('id')->all();

        if (! $this->isSameSet($actual_ids, $ordered_lesson_ids)) {
            throw ValidationException::withMessages([
                'lessons' => 'The provided lessons do not match this module.',
            ]);
        }

        foreach ($ordered_lesson_ids as $position => $lesson_id) {
            Lesson::whereKey($lesson_id)->update(['position' => $position]);
        }
    }

    /**
     * @param  array<int, int>  $a
     * @param  array<int, int>  $b
     */
    protected function isSameSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=ReorderActionsTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/ReorderModules.php app/Actions/ReorderLessons.php tests/Feature/Curriculum/ReorderActionsTest.php
git commit -m "Add ReorderModules and ReorderLessons actions

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Module authoring

**Files:**
- Create: `app/Http/Controllers/Curriculum/ModuleController.php`
- Create: `app/Http/Requests/Curriculum/StoreModuleRequest.php`, `app/Http/Requests/Curriculum/UpdateModuleRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Curriculum/ModuleAuthoringTest.php`

**Interfaces:**
- Consumes: `CoursePolicy::manageContent` (Task 1); `ReorderModules::run` (Task 2).
- Produces: routes `modules.store` (POST `courses/{course}/modules`), `modules.update` (PUT `modules/{module}`), `modules.destroy` (DELETE `modules/{module}`), `modules.reorder` (POST `courses/{course}/modules/reorder`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Curriculum/ModuleAuthoringTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the owner can create a module appended at the end', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    Module::factory()->for($course)->create(['position' => 0]);

    $this->actingAs($instructor)
        ->post(route('modules.store', $course), ['title' => 'New Module', 'description' => 'Desc'])
        ->assertRedirect();

    $module = Module::where('title', 'New Module')->sole();
    expect($module->course_id)->toBe($course->id)
        ->and($module->position)->toBe(1);
});

test('the owner can update a module', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $module = Module::factory()->for($course)->create(['title' => 'Old']);

    $this->actingAs($instructor)
        ->put(route('modules.update', $module), ['title' => 'Renamed', 'description' => null])
        ->assertRedirect();

    expect($module->fresh()->title)->toBe('Renamed');
});

test('the owner can soft-delete a module', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $module = Module::factory()->for($course)->create();

    $this->actingAs($instructor)->delete(route('modules.destroy', $module))->assertRedirect();

    expect($module->fresh()->trashed())->toBeTrue();
});

test('the owner can reorder modules', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $a = Module::factory()->for($course)->create(['position' => 0]);
    $b = Module::factory()->for($course)->create(['position' => 1]);

    $this->actingAs($instructor)
        ->post(route('modules.reorder', $course), ['modules' => [$b->id, $a->id]])
        ->assertRedirect();

    expect($b->fresh()->position)->toBe(0)->and($a->fresh()->position)->toBe(1);
});

test('a non-owner cannot create a module', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();

    $this->actingAs($instructor)
        ->post(route('modules.store', $course), ['title' => 'X'])
        ->assertForbidden();
});

test('a guest is redirected from module creation', function (): void {
    $course = Course::factory()->create();

    $this->post(route('modules.store', $course), ['title' => 'X'])->assertRedirect(route('login'));
});

test('creating a module requires a title', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();

    $this->actingAs($instructor)
        ->post(route('modules.store', $course), ['title' => ''])
        ->assertSessionHasErrors('title');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=ModuleAuthoringTest`
Expected: FAIL — route `modules.store` not defined.

- [ ] **Step 3: Create the form requests**

Create `app/Http/Requests/Curriculum/StoreModuleRequest.php`:

```php
<?php

namespace App\Http\Requests\Curriculum;

use Illuminate\Foundation\Http\FormRequest;

class StoreModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
```

Create `app/Http/Requests/Curriculum/UpdateModuleRequest.php` (identical body, class name `UpdateModuleRequest`):

```php
<?php

namespace App\Http\Requests\Curriculum;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Create ModuleController**

Create `app/Http/Controllers/Curriculum/ModuleController.php`:

```php
<?php

namespace App\Http\Controllers\Curriculum;

use App\Actions\ReorderModules;
use App\Http\Controllers\Controller;
use App\Http\Requests\Curriculum\StoreModuleRequest;
use App\Http\Requests\Curriculum\UpdateModuleRequest;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function store(StoreModuleRequest $request, Course $course): RedirectResponse
    {
        $this->authorize('manageContent', $course);

        $course->modules()->create([
            ...$request->validated(),
            'position' => (int) $course->modules()->max('position') + 1,
        ]);

        return back()->with('status', 'Module created.');
    }

    public function update(UpdateModuleRequest $request, Module $module): RedirectResponse
    {
        $this->authorize('manageContent', $module->course);

        $module->update($request->validated());

        return back()->with('status', 'Module updated.');
    }

    public function destroy(Module $module): RedirectResponse
    {
        $this->authorize('manageContent', $module->course);

        $module->delete();

        return back()->with('status', 'Module deleted.');
    }

    public function reorder(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('manageContent', $course);

        $validated = $request->validate([
            'modules' => ['required', 'array'],
            'modules.*' => ['integer'],
        ]);

        ReorderModules::run($course, $validated['modules']);

        return back()->with('status', 'Modules reordered.');
    }
}
```

Note: `$course->modules()->max('position')` returns null for an empty course; `(int) null` is `0`, so the first module gets position 1. Acceptable (positions need only be monotonic).

- [ ] **Step 5: Add the routes**

In `routes/web.php`, add the imports:

```php
use App\Http\Controllers\Curriculum\ModuleController;
```

Inside the `Route::middleware('verified')->group(...)` block, add:

```php
Route::post('courses/{course}/modules', [ModuleController::class, 'store'])->name('modules.store');
Route::put('modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
Route::delete('modules/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy');
Route::post('courses/{course}/modules/reorder', [ModuleController::class, 'reorder'])->name('modules.reorder');
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=ModuleAuthoringTest`
Expected: PASS (7 tests).

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Curriculum/ModuleController.php app/Http/Requests/Curriculum routes/web.php tests/Feature/Curriculum/ModuleAuthoringTest.php
git commit -m "Add module authoring endpoints

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Lesson content sanitization

**Files:**
- Modify: `composer.json` (via `composer require mews/purifier`)
- Create: `config/purifier.php`
- Modify: `app/Models/Lesson.php`
- Test: `tests/Feature/Curriculum/LessonContentSanitizationTest.php`

**Interfaces:**
- Produces: a `content` attribute mutator on `Lesson` that runs any non-null assigned value through `Purifier::clean($value, 'lesson')`. Every lesson write is sanitized.

- [ ] **Step 1: Install mews/purifier**

Run: `composer require mews/purifier`
Expected: package installed; `Mews\Purifier\PurifierServiceProvider` auto-discovered.

- [ ] **Step 2: Create the purifier config**

Create `config/purifier.php` (definition cache disabled so no writable cache dir is needed in tests/CI):

```php
<?php

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'settings' => [
        'default' => [
            'HTML.Allowed' => 'p,br,strong,em,u,s,h1,h2,h3,ul,ol,li,blockquote,a[href|title],code,pre',
            'AutoFormat.RemoveEmpty' => true,
            'Cache.DefinitionImpl' => null,
        ],
        'lesson' => [
            'HTML.Allowed' => 'p,br,strong,em,u,s,h1,h2,h3,ul,ol,li,blockquote,a[href|title],code,pre',
            'HTML.TargetBlank' => true,
            'AutoFormat.RemoveEmpty' => true,
            'Cache.DefinitionImpl' => null,
        ],
    ],
];
```

- [ ] **Step 3: Write the failing test**

Create `tests/Feature/Curriculum/LessonContentSanitizationTest.php`:

```php
<?php

use App\Models\Lesson;

test('lesson content is sanitized on save', function (): void {
    $lesson = Lesson::factory()->create([
        'content' => '<p>Safe copy</p><script>alert(1)</script>'
            .'<a href="javascript:alert(2)">x</a><iframe src="evil"></iframe>',
    ]);

    $stored = $lesson->fresh()->content;

    expect($stored)->toContain('Safe copy')
        ->not->toContain('<script>')
        ->not->toContain('javascript:')
        ->not->toContain('<iframe');
});

test('allowed formatting survives sanitization', function (): void {
    $lesson = Lesson::factory()->create([
        'content' => '<p><strong>Bold</strong> and <em>italic</em></p><ul><li>Item</li></ul>',
    ]);

    $stored = $lesson->fresh()->content;

    expect($stored)->toContain('<strong>Bold</strong>')
        ->toContain('<em>italic</em>')
        ->toContain('<li>Item</li>');
});

test('null content stays null', function (): void {
    $lesson = Lesson::factory()->create(['content' => null]);

    expect($lesson->fresh()->content)->toBeNull();
});
```

- [ ] **Step 4: Run the test to verify it fails**

Run: `php artisan test --compact --filter=LessonContentSanitizationTest`
Expected: FAIL — raw `<script>`/`javascript:` still present (no mutator yet).

- [ ] **Step 5: Add the content mutator to Lesson**

In `app/Models/Lesson.php`, add the imports:

```php
use Illuminate\Database\Eloquent\Casts\Attribute;
use Mews\Purifier\Facades\Purifier;
```

Add the mutator method (near the other model methods):

```php
/**
 * Sanitize lesson HTML on write so only safe formatting is ever stored.
 */
protected function content(): Attribute
{
    return Attribute::make(
        set: fn (?string $value): ?string => $value === null ? null : Purifier::clean($value, 'lesson'),
    );
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --compact --filter=LessonContentSanitizationTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Run the full suite (mutator now runs on all lesson writes)**

Run: `php artisan test --compact`
Expected: PASS — existing lesson-creating tests still green (plain content passes through unchanged).

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add composer.json composer.lock config/purifier.php app/Models/Lesson.php tests/Feature/Curriculum/LessonContentSanitizationTest.php
git commit -m "Sanitize lesson content on write with HTMLPurifier

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: Lesson authoring + global slug uniqueness

**Files:**
- Create: `database/migrations/<ts>_make_lesson_slug_globally_unique.php` (use `php artisan make:migration make_lesson_slug_globally_unique --no-interaction`)
- Create: `app/Http/Controllers/Curriculum/LessonController.php`
- Create: `app/Http/Requests/Curriculum/StoreLessonRequest.php`, `app/Http/Requests/Curriculum/UpdateLessonRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Curriculum/LessonAuthoringTest.php`

**Interfaces:**
- Consumes: `CoursePolicy::manageContent` (Task 1), `ReorderLessons::run` (Task 2), the `Lesson` content mutator (Task 4).
- Produces: routes `lessons.store` (POST `modules/{module}/lessons`), `lessons.update` (PUT `lessons/{lesson}`), `lessons.destroy` (DELETE `lessons/{lesson}`), `lessons.reorder` (POST `modules/{module}/lessons/reorder`). Lesson slugs globally unique (DB index + generation).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Curriculum/LessonAuthoringTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function ownedModule(User $instructor): Module
{
    $course = Course::factory()->for($instructor, 'instructor')->create();

    return Module::factory()->for($course)->create();
}

test('the owner can create a lesson with a unique slug appended at the end', function (): void {
    $instructor = User::factory()->instructor()->create();
    $module = ownedModule($instructor);
    Lesson::factory()->for($module)->create(['position' => 0]);

    $this->actingAs($instructor)
        ->post(route('lessons.store', $module), [
            'title' => 'Intro Lesson',
            'content' => '<p>Hello</p>',
            'duration_minutes' => 10,
        ])
        ->assertRedirect();

    $lesson = Lesson::where('title', 'Intro Lesson')->sole();
    expect($lesson->slug)->toBe('intro-lesson')
        ->and($lesson->position)->toBe(1)
        ->and($lesson->module_id)->toBe($module->id);
});

test('a duplicate lesson title gets a globally unique slug', function (): void {
    $instructor = User::factory()->instructor()->create();
    $module = ownedModule($instructor);
    Lesson::factory()->for($module)->create(['title' => 'Welcome', 'slug' => 'welcome']);

    $this->actingAs($instructor)->post(route('lessons.store', $module), [
        'title' => 'Welcome',
    ])->assertRedirect();

    expect(Lesson::where('slug', 'welcome-2')->exists())->toBeTrue();
});

test('the owner can update a lesson without changing its slug', function (): void {
    $instructor = User::factory()->instructor()->create();
    $module = ownedModule($instructor);
    $lesson = Lesson::factory()->for($module)->create(['title' => 'Original', 'slug' => 'original']);

    $this->actingAs($instructor)
        ->put(route('lessons.update', $lesson), ['title' => 'Renamed', 'content' => '<p>New</p>'])
        ->assertRedirect();

    $lesson->refresh();
    expect($lesson->title)->toBe('Renamed')->and($lesson->slug)->toBe('original');
});

test('the owner can soft-delete a lesson', function (): void {
    $instructor = User::factory()->instructor()->create();
    $module = ownedModule($instructor);
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($instructor)->delete(route('lessons.destroy', $lesson))->assertRedirect();

    expect($lesson->fresh()->trashed())->toBeTrue();
});

test('the owner can reorder lessons', function (): void {
    $instructor = User::factory()->instructor()->create();
    $module = ownedModule($instructor);
    $a = Lesson::factory()->for($module)->create(['position' => 0]);
    $b = Lesson::factory()->for($module)->create(['position' => 1]);

    $this->actingAs($instructor)
        ->post(route('lessons.reorder', $module), ['lessons' => [$b->id, $a->id]])
        ->assertRedirect();

    expect($b->fresh()->position)->toBe(0)->and($a->fresh()->position)->toBe(1);
});

test('a non-owner cannot create a lesson', function (): void {
    $instructor = User::factory()->instructor()->create();
    $module = Module::factory()->create();

    $this->actingAs($instructor)
        ->post(route('lessons.store', $module), ['title' => 'X'])
        ->assertForbidden();
});

test('a guest is redirected from lesson creation', function (): void {
    $module = Module::factory()->create();

    $this->post(route('lessons.store', $module), ['title' => 'X'])->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=LessonAuthoringTest`
Expected: FAIL — route `lessons.store` not defined.

- [ ] **Step 3: Create the slug-uniqueness migration**

Run `php artisan make:migration make_lesson_slug_globally_unique --no-interaction`, then replace its body:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropUnique(['module_id', 'slug']);
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->unique(['module_id', 'slug']);
        });
    }
};
```

- [ ] **Step 4: Create the form requests**

Create `app/Http/Requests/Curriculum/StoreLessonRequest.php`:

```php
<?php

namespace App\Http\Requests\Curriculum;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
```

Create `app/Http/Requests/Curriculum/UpdateLessonRequest.php` (same rules, class `UpdateLessonRequest`):

```php
<?php

namespace App\Http\Requests\Curriculum;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
```

- [ ] **Step 5: Create the Curriculum LessonController**

Create `app/Http/Controllers/Curriculum/LessonController.php`:

```php
<?php

namespace App\Http\Controllers\Curriculum;

use App\Actions\ReorderLessons;
use App\Http\Controllers\Controller;
use App\Http\Requests\Curriculum\StoreLessonRequest;
use App\Http\Requests\Curriculum\UpdateLessonRequest;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LessonController extends Controller
{
    public function store(StoreLessonRequest $request, Module $module): RedirectResponse
    {
        $this->authorize('manageContent', $module->course);

        $validated = $request->validated();

        $module->lessons()->create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['title']),
            'position' => (int) $module->lessons()->max('position') + 1,
        ]);

        return back()->with('status', 'Lesson created.');
    }

    public function update(UpdateLessonRequest $request, Lesson $lesson): RedirectResponse
    {
        $this->authorize('manageContent', $lesson->module->course);

        $lesson->update($request->validated());

        return back()->with('status', 'Lesson updated.');
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        $this->authorize('manageContent', $lesson->module->course);

        $lesson->delete();

        return back()->with('status', 'Lesson deleted.');
    }

    public function reorder(Request $request, Module $module): RedirectResponse
    {
        $this->authorize('manageContent', $module->course);

        $validated = $request->validate([
            'lessons' => ['required', 'array'],
            'lessons.*' => ['integer'],
        ]);

        ReorderLessons::run($module, $validated['lessons']);

        return back()->with('status', 'Lessons reordered.');
    }

    /**
     * Build a globally unique lesson slug (including soft-deleted rows, which
     * keep their slug against the global unique index).
     */
    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (Lesson::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
```

Note: `$module->course` — `Module::course()` belongsTo exists. `$lesson->module->course` chains through existing relations.

- [ ] **Step 6: Add the routes**

In `routes/web.php`, add the aliased import (the student `LessonController` is already imported):

```php
use App\Http\Controllers\Curriculum\LessonController as CurriculumLessonController;
```

Inside the `Route::middleware('verified')->group(...)` block, add:

```php
Route::post('modules/{module}/lessons', [CurriculumLessonController::class, 'store'])->name('lessons.store');
Route::put('lessons/{lesson}', [CurriculumLessonController::class, 'update'])->name('lessons.update');
Route::delete('lessons/{lesson}', [CurriculumLessonController::class, 'destroy'])->name('lessons.destroy');
Route::post('modules/{module}/lessons/reorder', [CurriculumLessonController::class, 'reorder'])->name('lessons.reorder');
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=LessonAuthoringTest`
Expected: PASS (7 tests).

- [ ] **Step 8: Run the full suite**

Run: `php artisan test --compact`
Expected: PASS — the migration change and new routes don't regress prior lesson/enrollment tests.

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Http/Controllers/Curriculum/LessonController.php app/Http/Requests/Curriculum/StoreLessonRequest.php app/Http/Requests/Curriculum/UpdateLessonRequest.php routes/web.php tests/Feature/Curriculum/LessonAuthoringTest.php
git commit -m "Add lesson authoring endpoints with global slug uniqueness

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: Curriculum builder page + drag reorder

**Files:**
- Create: `app/Http/Controllers/Curriculum/CurriculumController.php`
- Modify: `routes/web.php`
- Create: `resources/js/Pages/Curriculum/Show.vue`
- Modify: `resources/js/Pages/Courses/Index.vue`
- Modify: `package.json` (via `npm install vuedraggable@next`)
- Test: `tests/Feature/Curriculum/CurriculumBuilderTest.php`

**Interfaces:**
- Consumes: `CoursePolicy::manageContent` (Task 1); all module/lesson routes (Tasks 3, 5).
- Produces: route `curriculum.show` (GET `courses/{course}/curriculum`); passes `course` (`{id, title, slug}`) and `modules` (`[{id, title, description, lessons: [{id, title, slug, content, duration_minutes}]}]`) in position order.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Curriculum/CurriculumBuilderTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the owner sees the curriculum builder with modules and lessons in order', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $module_one = Module::factory()->for($course)->create(['title' => 'Module A', 'position' => 0]);
    Module::factory()->for($course)->create(['title' => 'Module B', 'position' => 1]);
    Lesson::factory()->for($module_one)->create(['title' => 'Lesson A1', 'position' => 0]);

    $this->actingAs($instructor)
        ->get(route('curriculum.show', $course))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Curriculum/Show')
            ->where('modules.0.title', 'Module A')
            ->where('modules.1.title', 'Module B')
            ->where('modules.0.lessons.0.title', 'Lesson A1')
        );
});

test('a non-owner cannot open the curriculum builder', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();

    $this->actingAs($instructor)->get(route('curriculum.show', $course))->assertForbidden();
});

test('a guest is redirected from the curriculum builder', function (): void {
    $course = Course::factory()->create();

    $this->get(route('curriculum.show', $course))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=CurriculumBuilderTest`
Expected: FAIL — route `curriculum.show` not defined.

- [ ] **Step 3: Create CurriculumController**

Create `app/Http/Controllers/Curriculum/CurriculumController.php`:

```php
<?php

namespace App\Http\Controllers\Curriculum;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Inertia\Inertia;
use Inertia\Response;

class CurriculumController extends Controller
{
    public function show(Course $course): Response
    {
        $this->authorize('manageContent', $course);

        $course->load('modules.lessons');

        return Inertia::render('Curriculum/Show', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'modules' => $course->modules->map(fn ($module): array => [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'lessons' => $module->lessons->map(fn ($lesson): array => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'slug' => $lesson->slug,
                    'content' => $lesson->content,
                    'duration_minutes' => $lesson->duration_minutes,
                ])->values(),
            ])->values(),
        ]);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\Curriculum\CurriculumController;
```

Inside the `Route::middleware('verified')->group(...)` block, add:

```php
Route::get('courses/{course}/curriculum', [CurriculumController::class, 'show'])->name('curriculum.show');
```

- [ ] **Step 5: Install vuedraggable**

Run: `npm install vuedraggable@next`
Expected: `vuedraggable` (v4, Vue 3) added to `package.json` dependencies.

- [ ] **Step 6: Add the "Curriculum" link to Courses/Index.vue**

In `resources/js/Pages/Courses/Index.vue`, add a Curriculum link as the first action in the actions cell (before Edit):

```vue
<Link :href="route('curriculum.show', course.slug)" class="text-indigo-600 hover:underline">Curriculum</Link>
```

so the actions `<div>` reads:

```vue
<div class="flex justify-end gap-3">
    <Link :href="route('curriculum.show', course.slug)" class="text-indigo-600 hover:underline">Curriculum</Link>
    <Link :href="route('courses.edit', course.slug)" class="text-blue-600 hover:underline">Edit</Link>
    <button type="button" class="text-green-600 hover:underline" @click="publish(course)">Publish</button>
    <button type="button" class="text-amber-600 hover:underline" @click="archive(course)">Archive</button>
    <button type="button" class="text-red-600 hover:underline" @click="destroy(course)">Delete</button>
</div>
```

- [ ] **Step 7: Create the builder page**

Create `resources/js/Pages/Curriculum/Show.vue` (lesson content uses a plain `<textarea>` here; Task 7 swaps it for the rich editor):

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps({
    course: { type: Object, required: true },
    modules: { type: Array, required: true },
});

// Local, drag-mutable deep copy; re-seeded whenever Inertia reloads props.
const moduleList = ref(clone(props.modules));
watch(() => props.modules, (value) => {
    moduleList.value = clone(value);
});

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

const reload = { preserveScroll: true };

// Modules
const newModuleTitle = ref('');
const addModule = () => {
    if (! newModuleTitle.value.trim()) {
        return;
    }
    router.post(route('modules.store', props.course.slug), { title: newModuleTitle.value }, {
        ...reload,
        onSuccess: () => {
            newModuleTitle.value = '';
        },
    });
};
const updateModule = (module) => {
    router.put(route('modules.update', module.id), { title: module.title, description: module.description }, reload);
};
const deleteModule = (module) => {
    if (confirm(`Delete module "${module.title}" and its lessons?`)) {
        router.delete(route('modules.destroy', module.id), reload);
    }
};
const persistModuleOrder = () => {
    router.post(route('modules.reorder', props.course.slug), {
        modules: moduleList.value.map((m) => m.id),
    }, reload);
};

// Lessons
const newLessonTitle = ref({});
const addLesson = (module) => {
    const title = (newLessonTitle.value[module.id] ?? '').trim();
    if (! title) {
        return;
    }
    router.post(route('lessons.store', module.id), { title }, {
        ...reload,
        onSuccess: () => {
            newLessonTitle.value[module.id] = '';
        },
    });
};
const updateLesson = (lesson) => {
    router.put(route('lessons.update', lesson.slug), {
        title: lesson.title,
        content: lesson.content,
        duration_minutes: lesson.duration_minutes,
    }, reload);
};
const deleteLesson = (lesson) => {
    if (confirm(`Delete lesson "${lesson.title}"?`)) {
        router.delete(route('lessons.destroy', lesson.slug), reload);
    }
};
const persistLessonOrder = (module) => {
    router.post(route('lessons.reorder', module.id), {
        lessons: module.lessons.map((l) => l.id),
    }, reload);
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Curriculum · ${course.title}`" />

        <h1 class="mb-6 text-2xl font-semibold">{{ course.title }} — Curriculum</h1>

        <draggable v-model="moduleList" item-key="id" handle=".module-handle" class="space-y-4" @end="persistModuleOrder">
            <template #item="{ element: module }">
                <div class="rounded border p-4">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="module-handle cursor-move text-gray-400">⠿</span>
                        <input
                            v-model="module.title"
                            class="flex-1 rounded border-gray-300 text-sm font-medium"
                            @blur="updateModule(module)"
                        />
                        <button type="button" class="text-sm text-red-600 hover:underline" @click="deleteModule(module)">
                            Delete
                        </button>
                    </div>

                    <draggable
                        v-model="module.lessons"
                        item-key="id"
                        handle=".lesson-handle"
                        class="space-y-2 pl-6"
                        @end="persistLessonOrder(module)"
                    >
                        <template #item="{ element: lesson }">
                            <div class="rounded bg-gray-50 p-3">
                                <div class="flex items-center gap-2">
                                    <span class="lesson-handle cursor-move text-gray-400">⠿</span>
                                    <input
                                        v-model="lesson.title"
                                        class="flex-1 rounded border-gray-300 text-sm"
                                        @blur="updateLesson(lesson)"
                                    />
                                    <input
                                        v-model.number="lesson.duration_minutes"
                                        type="number"
                                        min="0"
                                        class="w-20 rounded border-gray-300 text-sm"
                                        placeholder="min"
                                        @blur="updateLesson(lesson)"
                                    />
                                    <button type="button" class="text-sm text-red-600 hover:underline" @click="deleteLesson(lesson)">
                                        Delete
                                    </button>
                                </div>
                                <textarea
                                    v-model="lesson.content"
                                    rows="4"
                                    class="mt-2 block w-full rounded border-gray-300 text-sm"
                                    placeholder="Lesson content"
                                    @blur="updateLesson(lesson)"
                                />
                            </div>
                        </template>
                    </draggable>

                    <div class="mt-3 flex gap-2 pl-6">
                        <input
                            v-model="newLessonTitle[module.id]"
                            class="flex-1 rounded border-gray-300 text-sm"
                            placeholder="New lesson title"
                            @keyup.enter="addLesson(module)"
                        />
                        <button type="button" class="rounded bg-gray-900 px-3 py-1 text-sm text-white" @click="addLesson(module)">
                            Add lesson
                        </button>
                    </div>
                </div>
            </template>
        </draggable>

        <div class="mt-6 flex gap-2">
            <input
                v-model="newModuleTitle"
                class="flex-1 rounded border-gray-300 text-sm"
                placeholder="New module title"
                @keyup.enter="addModule"
            />
            <button type="button" class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white" @click="addModule">
                Add module
            </button>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=CurriculumBuilderTest`
Expected: PASS (3 tests).

- [ ] **Step 9: Build the frontend**

Run: `npm run build`
Expected: build succeeds; `Curriculum/Show` compiles and `vuedraggable` resolves.

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Curriculum/CurriculumController.php routes/web.php resources/js/Pages/Curriculum resources/js/Pages/Courses/Index.vue package.json package-lock.json tests/Feature/Curriculum/CurriculumBuilderTest.php
git commit -m "Add curriculum builder page with drag-and-drop reorder

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 7: Rich-text editor + student HTML render

**Files:**
- Modify: `package.json` (via `npm install @tiptap/vue-3 @tiptap/starter-kit @tiptap/extension-link`)
- Create: `resources/js/Components/RichTextEditor.vue`
- Modify: `resources/js/Pages/Curriculum/Show.vue` (swap the lesson content `<textarea>` for `RichTextEditor`)
- Modify: `resources/js/Pages/Lessons/Show.vue` (render content via `v-html`)

**Interfaces:**
- Consumes: the lesson `content` field in the builder (Task 6) and the student lesson view.
- Produces: `RichTextEditor.vue` — a `v-model`-bound TipTap editor emitting an HTML string.

- [ ] **Step 1: Install TipTap**

Run: `npm install @tiptap/vue-3 @tiptap/starter-kit @tiptap/extension-link`
Expected: the three packages added to `package.json`.

- [ ] **Step 2: Create RichTextEditor.vue**

Create `resources/js/Components/RichTextEditor.vue`:

```vue
<script setup>
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const editor = ref(
    new Editor({
        content: props.modelValue || '',
        extensions: [StarterKit, Link.configure({ openOnClick: false })],
        onUpdate: ({ editor }) => {
            emit('update:modelValue', editor.getHTML());
        },
    }),
);

// Keep the editor in sync if the bound value changes externally (e.g. props reload).
watch(() => props.modelValue, (value) => {
    if (editor.value && value !== editor.value.getHTML()) {
        editor.value.commands.setContent(value || '', false);
    }
});

onBeforeUnmount(() => {
    editor.value?.destroy();
});

const toggle = (fn) => () => fn();
</script>

<template>
    <div class="rounded border border-gray-300">
        <div v-if="editor" class="flex flex-wrap gap-1 border-b bg-gray-50 p-1 text-sm">
            <button type="button" class="rounded px-2 py-1 hover:bg-gray-200" :class="{ 'bg-gray-200': editor.isActive('bold') }" @click="toggle(() => editor.chain().focus().toggleBold().run())()">B</button>
            <button type="button" class="rounded px-2 py-1 italic hover:bg-gray-200" :class="{ 'bg-gray-200': editor.isActive('italic') }" @click="toggle(() => editor.chain().focus().toggleItalic().run())()">i</button>
            <button type="button" class="rounded px-2 py-1 hover:bg-gray-200" :class="{ 'bg-gray-200': editor.isActive('heading', { level: 2 }) }" @click="toggle(() => editor.chain().focus().toggleHeading({ level: 2 }).run())()">H2</button>
            <button type="button" class="rounded px-2 py-1 hover:bg-gray-200" :class="{ 'bg-gray-200': editor.isActive('bulletList') }" @click="toggle(() => editor.chain().focus().toggleBulletList().run())()">• List</button>
            <button type="button" class="rounded px-2 py-1 hover:bg-gray-200" :class="{ 'bg-gray-200': editor.isActive('orderedList') }" @click="toggle(() => editor.chain().focus().toggleOrderedList().run())()">1. List</button>
        </div>
        <EditorContent :editor="editor" class="prose max-w-none p-3 text-sm focus:outline-none" />
    </div>
</template>
```

- [ ] **Step 3: Swap the textarea in the builder for RichTextEditor**

In `resources/js/Pages/Curriculum/Show.vue`, add the import at the top of `<script setup>`:

```js
import RichTextEditor from '@/Components/RichTextEditor.vue';
```

Replace the lesson content `<textarea ...>` element with:

```vue
<div class="mt-2">
    <RichTextEditor v-model="lesson.content" @update:model-value="updateLesson(lesson)" />
</div>
```

(Editing fires `updateLesson` on each change; the server sanitizes on write.)

- [ ] **Step 4: Render sanitized content in the student lesson view**

In `resources/js/Pages/Lessons/Show.vue`, replace the plain-text content block:

```vue
<div class="mb-8 whitespace-pre-line text-gray-700">{{ lesson.content }}</div>
```

with (content is server-sanitized, so `v-html` is safe here):

```vue
<div class="prose mb-8 max-w-none text-gray-700" v-html="lesson.content" />
```

- [ ] **Step 5: Build the frontend**

Run: `npm run build`
Expected: build succeeds; `RichTextEditor`, `Curriculum/Show`, and `Lessons/Show` all compile with TipTap resolved.

- [ ] **Step 6: Run the full test suite**

Run: `php artisan test --compact`
Expected: PASS — all prior tests plus this slice's (the `Lessons/Show.vue` render change is client-side; the controller payload is unchanged, so `LessonViewingTest` stays green).

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add package.json package-lock.json resources/js/Components/RichTextEditor.vue resources/js/Pages/Curriculum/Show.vue resources/js/Pages/Lessons/Show.vue
git commit -m "Add TipTap rich-text lesson editor and render sanitized HTML

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review Notes

- **Spec coverage:** `manageContent` policy (T1); reorder actions with id-set validation (T2); module CRUD+reorder (T3); sanitization mutator + purifier config (T4); lesson CRUD+reorder + slug migration + global-unique generation (T5); builder page + drag reorder + Curriculum link (T6); TipTap editor + `v-html` student render (T7). Every spec test maps to a task.
- **Slug + soft-delete interaction:** `uniqueSlug` uses `withTrashed()` so a soft-deleted lesson's retained slug can't collide with the new global unique index (the class of bug the enrollment gotcha flagged).
- **Sanitization is enforced on write** via the `Lesson::content` mutator (T4), before any authoring endpoint exists (T5) — so every lesson write, from any path, is sanitized. `v-html` (T7) is only ever fed already-sanitized DB content.
- **Controller name clash resolved:** `Curriculum\LessonController` is imported into `routes/web.php` aliased as `CurriculumLessonController`; the student `LessonController` import is unchanged.
- **No throwaway stubs:** each new Vue page is built in the task whose test asserts its component; `Curriculum/Show.vue` starts with a textarea (T6) and is upgraded to the editor (T7) — a field swap, not a rebuild.
- **Dependency isolation:** `mews/purifier` (T4), `vuedraggable` (T6), and TipTap (T7) are each installed in the task that first needs them, so a reviewer sees one dependency addition per gate.
- **Type consistency:** reorder payload keys (`modules`, `lessons`) match between controllers (T3/T5) and the builder's `router.post` bodies (T6); `ReorderModules::run(Course, int[])` / `ReorderLessons::run(Module, int[])` signatures match their callers; lesson update binds by `slug` in both the route (T5) and the builder's `route('lessons.update', lesson.slug)` (T6).
```
