# Course Content Authoring (Modules + Lessons) — Design Spec

**Date:** 2026-07-03
**Status:** Approved for planning
**Phase:** Fifth application-layer slice — instructor curriculum authoring; closes the loop from "build a course" to "student learns it," and resolves the lesson-slug binding landmine flagged in the lesson-viewing review.

## Context

Instructors can create/publish courses; students browse, enroll, read lessons, and track progress.
But modules and lessons only exist via seeders — there is no UI to author them. This slice adds a
single **curriculum builder** page where a course's instructor (or admin) manages modules and the
lessons within them: create, edit, reorder (drag-and-drop), and delete. It also fixes the
outstanding defect that lessons bind by slug globally while slugs are only unique per module.

## Decisions (locked)

| Decision | Choice |
|----------|--------|
| Scope | **Modules + lessons together**, one slice: full CRUD + reorder for both, on one builder page. |
| UI shape | **Single curriculum builder page** per course — modules with nested lessons, inline add/edit forms. |
| Reordering | **Drag-and-drop** via the `vuedraggable@next` npm package (Vue 3 SortableJS wrapper) — an approved new dependency. Drop fires a reorder POST. |
| Authorization | New `CoursePolicy@manageContent` — `manage course content` permission (already seeded for instructors) AND course ownership; admins via `Gate::before`. |
| Lesson slug | **Globally unique**, DB-enforced (migration swaps the per-module unique index for a global one). Auto-generated from title with a uniqueness suffix; **stable after creation**. |
| Lesson content | Plain `<textarea>` (rendered as plain text elsewhere) — no rich-text/markdown editor. |
| New-item position | Appends at end (`position` = current max + 1). |
| Delete | Soft delete (models already use `SoftDeletes`). |
| Controller grouping | Authoring controllers live under `App\Http\Controllers\Curriculum\` to keep them separate from the student-facing `LessonController@show`. |

## Non-Goals (deferred)

- Rich-text / markdown / media lesson content.
- Bulk import, duplication, or move-lesson-between-modules.
- Versioning / draft-vs-published lesson state (course-level publish already exists).
- Assignments, tests, discussions attached to lessons/modules.

## Architecture

Thin authoring controllers authorize `manageContent` on the parent course and delegate; reordering
is done by two Loris Leiva actions. The builder page is one Inertia page using `vuedraggable` for
both the module list and each module's lesson list. Every mutation redirects back to the builder,
which reloads fresh ordered state.

### Authorization — `CoursePolicy@manageContent`

`manageContent(User $user, Course $course): bool` →
`$user->can('manage course content') && $course->instructor_id === $user->id`.

Admins bypass via `Gate::before`. Students lack the permission. Every module and lesson mutation
authorizes `manageContent` against the parent course (resolved from the module's `course_id` or the
lesson's `module->course_id`).

### Slug fix — migration + generation

- **Migration:** on `lessons`, `dropUnique(['module_id', 'slug'])` then `unique('slug')`. This makes
  lesson slugs globally unique, matching the existing global route-model binding
  (`Lesson::getRouteKeyName() === 'slug'`), so a slug always resolves to exactly one lesson.
- **Generation:** on lesson `store`, slug = `Str::slug($title)` with a `-2`, `-3`, … suffix until
  globally unique across `lessons` (same helper shape as `CourseController::uniqueSlug`). Slug is
  **not** regenerated on update (stable; completions key on `lesson_id`).

### Reorder actions — `app/Actions/`

- `ReorderModules::handle(Course $course, array $ordered_module_ids): void` — verifies the id set
  exactly equals the course's module ids, then writes `position` = array index for each.
- `ReorderLessons::handle(Module $module, array $ordered_lesson_ids): void` — same, against the
  module's lesson ids.

A mismatched id set (foreign or missing id) throws a validation error (422) — reorder cannot move
items across courses/modules or partially reorder.

### Controllers — `App\Http\Controllers\Curriculum\`

- `CurriculumController@show(Course $course): Response` — authorizes `manageContent`; loads
  `modules.lessons` (ordered by position); renders `Curriculum/Show` with the course and its
  modules (each `{id, title, description, lessons: [{id, title, slug, duration_minutes}]}`).
- `ModuleController` — all authorize `manageContent` on the course:
  - `store(StoreModuleRequest, Course $course)` — create module (title, description), position = max+1.
  - `update(UpdateModuleRequest, Module $module)` — update title/description.
  - `destroy(Module $module)` — soft delete.
  - `reorder(Request, Course $course)` — validate `modules` (array of ids) → `ReorderModules::run`.
- `LessonController` (in the `Curriculum` namespace; distinct from the student one) — all authorize
  `manageContent` on the lesson's/module's course:
  - `store(StoreLessonRequest, Module $module)` — create lesson (title, content, duration), unique
    global slug, position = max+1.
  - `update(UpdateLessonRequest, Lesson $lesson)` — update title/content/duration; slug unchanged.
  - `destroy(Lesson $lesson)` — soft delete.
  - `reorder(Request, Module $module)` — validate `lessons` (array of ids) → `ReorderLessons::run`.

### Form Requests — `app/Http/Requests/Curriculum/`

- `StoreModuleRequest` / `UpdateModuleRequest` — `title` required string max 255; `description`
  nullable string. `authorize()` returns true (controller authorizes).
- `StoreLessonRequest` / `UpdateLessonRequest` — `title` required string max 255; `content` nullable
  string; `duration_minutes` nullable integer min 0.

### Routes — `routes/web.php` (inside `auth` → `verified`)

```php
Route::get('courses/{course}/curriculum', [CurriculumController::class, 'show'])->name('curriculum.show');

Route::post('courses/{course}/modules', [ModuleController::class, 'store'])->name('modules.store');
Route::put('modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
Route::delete('modules/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy');
Route::post('courses/{course}/modules/reorder', [ModuleController::class, 'reorder'])->name('modules.reorder');

Route::post('modules/{module}/lessons', [LessonController::class, 'store'])->name('lessons.store');
Route::put('lessons/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
Route::delete('lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');
Route::post('modules/{module}/lessons/reorder', [LessonController::class, 'reorder'])->name('lessons.reorder');
```

`{course}` and `{lesson}` bind by slug; `{module}` binds by id. (`LessonController` here refers to
the `Curriculum` namespace class.) Route ordering: the `modules/reorder` and `lessons/reorder`
POSTs use distinct paths from the student `learn/...` routes — no collision.

### Frontend — `resources/js/`

- `Pages/Curriculum/Show.vue` — the builder. A `vuedraggable` list of modules; within each module,
  a `vuedraggable` list of its lessons. Each module row: title/description, inline edit, delete,
  and an "Add lesson" inline form. A top-level "Add module" inline form. Dropping an item commits
  the new order with a `router.post` to the matching `*.reorder` route carrying the reordered id
  array; adds/edits/deletes use `router`/`useForm` to the CRUD routes; all reload the page props.
- `package.json` — add `vuedraggable@next` (imported as `import draggable from 'vuedraggable'`).
- `resources/js/Pages/Courses/Index.vue` (modify) — add a "Curriculum" link per course row (the
  instructor already sees only their own courses there), to `curriculum.show`.

### Entry point

The instructor course **Index** gains a per-row "Curriculum" link to the builder. Access is
`manageContent`-gated at the route/controller, so a non-owner hitting the URL gets 403.

## Data Flow

1. Instructor clicks "Curriculum" on their course → `curriculum.show` (authorize `manageContent`).
2. Adds a module → `modules.store` → appended → redirect back, list reloads.
3. Adds lessons to a module → `lessons.store` → unique slug, appended.
4. Drags a lesson/module → drop → `lessons.reorder`/`modules.reorder` with the new id order →
   `ReorderLessons`/`ReorderModules` rewrite positions → reload.
5. Inline edit / delete → `*.update` / `*.destroy` → reload.

## Error Handling

- Non-owner (or student) hitting any authoring route → `403` (policy `manageContent`).
- Guest → redirect to login (`auth`); unverified → verification notice (`verified`).
- Reorder payload whose id set doesn't match the parent's children → `422` (validation in the action).
- Duplicate-title lessons still get distinct slugs (suffix); the DB global-unique index is the backstop.
- Validation failures on inline forms → Inertia field errors.

## Testing (Pest feature tests, built test-first)

**Policy:**
- `manageContent`: owner-instructor true, non-owner instructor false, student false, admin true.

**Slug migration + generation:**
- Two lessons created with the same title (in the same module AND across different modules/courses)
  receive distinct global slugs; both are individually resolvable by slug via `lessons.show`.

**Modules:**
- Owner can store (appends at end), update, soft-delete a module.
- Non-owner → 403 on each; guest → login redirect.
- `modules.reorder` rewrites `position` to the given order; a payload containing a module id from
  another course → 422, positions unchanged.

**Lessons:**
- Owner can store (unique slug, position appended), update (slug unchanged when title changes),
  soft-delete a lesson.
- Non-owner → 403; guest → login redirect.
- `lessons.reorder` rewrites `position`; a lesson id from another module → 422.

**Builder page + entry point:**
- `curriculum.show` renders `Curriculum/Show` with modules and nested lessons in position order for
  the owner; 403 for a non-owner.

## Files Touched

**New — PHP:** `database/migrations/<ts>_make_lesson_slug_globally_unique.php`,
`app/Actions/ReorderModules.php`, `app/Actions/ReorderLessons.php`,
`app/Http/Controllers/Curriculum/CurriculumController.php`,
`app/Http/Controllers/Curriculum/ModuleController.php`,
`app/Http/Controllers/Curriculum/LessonController.php`,
`app/Http/Requests/Curriculum/{StoreModuleRequest,UpdateModuleRequest,StoreLessonRequest,UpdateLessonRequest}.php`.

**New — Vue:** `resources/js/Pages/Curriculum/Show.vue`.

**New — Tests:** `tests/Feature/Curriculum/ModuleAuthoringTest.php`,
`tests/Feature/Curriculum/LessonAuthoringTest.php`,
`tests/Feature/Curriculum/CurriculumBuilderTest.php`,
`tests/Feature/Curriculum/LessonSlugUniquenessTest.php`.

**Modified:** `app/Policies/CoursePolicy.php` (add `manageContent`), `routes/web.php`,
`resources/js/Pages/Courses/Index.vue` (add "Curriculum" link), `package.json` (add `vuedraggable`).

**Reused unchanged:** `Module`/`Lesson` models + factories.

**New dependency:** `vuedraggable@next` (approved).
