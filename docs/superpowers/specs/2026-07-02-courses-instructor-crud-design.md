# Courses (Instructor CRUD) — Design Spec

**Date:** 2026-07-02
**Status:** Approved for planning
**Phase:** Second application-layer slice — first domain feature; establishes the Action→Controller→Policy→Vue pattern.

## Context

Authentication shipped (login, reset, verification, role-aware dashboard). The domain layer
(models, enums, Loris Leiva Actions, Spatie roles/permissions) is complete. This slice builds
the first authoring feature: instructors and admins manage courses through the UI. It is the
reference implementation every later slice copies — so authorization, form handling, Actions,
and Vue page structure all get established here cleanly.

## Decisions (locked)

| Decision | Choice |
|----------|--------|
| Scope | **Instructor CRUD only.** List, create, edit, update, publish, archive, delete courses. No student browse/enroll this slice (deferred, along with the `EnrollStudent` wiring). |
| Status lifecycle | **Draft → Publish → Archive.** Created as Draft; publish sets `Published` + stamps `published_at`; archive sets `Archived`. |
| Publish/Archive | Implemented as **Loris Leiva Actions** (`PublishCourse`, `ArchiveCourse`) to match the existing domain layer. Create/update remain plain controller CRUD. |
| Cover image | **Deferred.** Text fields only (title, summary, description, level, status). |
| Ownership | Instructors manage **only their own** courses; admins manage all (via existing `Gate::before`). |
| Index scope | Admins see all courses; instructors see only their own. |
| Slug | Auto-generated from title on create (`Str::slug` + uniqueness suffix); **stable after creation** (not regenerated on title edits, to avoid breaking URLs). |
| Delete | Soft delete (model already uses `SoftDeletes`). |
| Vue permission gating | Share a `can` permissions map via `HandleInertiaRequests` for nav/button gating. |

## Non-Goals (deferred to later slices)

- Student-facing course browse / detail (`show`) and self-enroll (`EnrollStudent`).
- Modules / lessons / content management (`manage course content` permission).
- Cover image / media uploads.
- Assignments, tests, discussions, certificates.

## Architecture

Thin controllers authorize via `CoursePolicy` and delegate. Validation lives in Form Requests.
Status transitions are domain Actions. Vue pages use Inertia `useForm`. Everything follows the
conventions established by the auth slice and the existing Actions layer.

### Authorization — `app/Policies/CoursePolicy.php`

Auto-discovered by Laravel for the `Course` model. Methods and their backing seeded permissions:

| Method | Rule |
|--------|------|
| `viewAny(User)` | `true` for any authenticated user with `create courses` (instructors/admins). |
| `view(User, Course)` | owner or `create courses` holder. |
| `create(User)` | `create courses`. |
| `update(User, Course)` | `update courses` **and** `course.instructor_id === user.id`. |
| `delete(User, Course)` | `delete courses` **and** owner. |
| `publish(User, Course)` | `publish courses` **and** owner. |
| `archive(User, Course)` | `publish courses` **and** owner. |

Admins bypass all checks via the existing `Gate::before` in `AppServiceProvider`. Students hold
none of these permissions, so every write is denied.

### Actions — `app/Actions/`

- `PublishCourse::handle(Course $course): Course` — sets `status = CourseStatus::Published`,
  `published_at = now()` (only stamps if not already set), saves, returns the course.
- `ArchiveCourse::handle(Course $course): Course` — sets `status = CourseStatus::Archived`, saves,
  returns the course.

Both use the `AsAction` trait, matching `EnrollStudent` et al.

### Controllers — `app/Http/Controllers/`

- `CourseController` — `index`, `create`, `store`, `edit`, `update`, `destroy` (no `show`).
  Uses `$this->authorize(...)` against the policy. `index` filters by ownership. `store`
  generates the slug and sets `instructor_id = auth id`.
- `PublishCourseController::__invoke(Course $course)` — authorizes `publish`, calls
  `PublishCourse::run($course)`, redirects back with status.
- `ArchiveCourseController::__invoke(Course $course)` — authorizes `archive`, calls
  `ArchiveCourse::run($course)`, redirects back with status.

### Form Requests — `app/Http/Requests/Course/`

- `StoreCourseRequest` — `authorize()` returns `true`; the controller calls
  `$this->authorize('create', Course::class)` explicitly (consistent with the other actions,
  which authorize in the controller). Rules: `title` required string max 255; `summary` nullable
  string; `description` nullable string; `level` required enum (`CourseLevel`).
- `UpdateCourseRequest` — same field rules; `title` required.

### Slug generation

On `store`: `$slug = Str::slug($title)`; if it collides with an existing course slug, append
`-2`, `-3`, … until unique. Implemented in the controller (or a small private helper). Slug is
**not** changed on update.

### Routes — `routes/web.php` (inside the existing `auth` group)

```php
Route::resource('courses', CourseController::class)->except('show');
Route::post('courses/{course}/publish', PublishCourseController::class)->name('courses.publish');
Route::post('courses/{course}/archive', ArchiveCourseController::class)->name('courses.archive');
```

Course route-model binding uses the slug (`getRouteKeyName` already returns `slug`).

### Shared data — `app/Http/Middleware/HandleInertiaRequests.php`

Extend `auth.user` with a `can` map for client-side gating:

```php
'can' => $request->user() ? [
    'create_courses' => $request->user()->can('create courses'),
] : [],
```

(Grows as later slices add abilities.)

### Frontend — `resources/js/`

- `Pages/Courses/Index.vue` — table of the current user's (or all, for admins) courses with
  title, level, status badge, and per-row actions: Edit (link), Publish/Archive
  (`<Link method="post">`), Delete (`<Link method="delete">` with confirm). "New course" button
  gated on `auth.user.can.create_courses`.
- `Pages/Courses/Create.vue` and `Pages/Courses/Edit.vue` — each renders the shared
  `CourseForm.vue` partial and wires `useForm` to `courses.store` / `courses.update`.
- `Components/CourseForm.vue` — fields: title, summary, description, level (`<select>` over
  `CourseLevel` values passed as a prop). Emits nothing; receives a `form` object.
- `Layouts/AuthenticatedLayout.vue` — add a "Courses" nav link, shown when
  `auth.user.can.create_courses` is true.

Level options are passed from the controller as a prop (array of `{ value, label }` from the
`CourseLevel` enum) so the Vue layer needs no enum duplication.

## Data Flow

1. Instructor clicks "Courses" → `courses.index` → policy `viewAny` → list filtered to own courses.
2. "New course" → `courses.create` → `Create.vue` → submit → `StoreCourseRequest` validates →
   `CourseController@store` sets `instructor_id`, generates slug, `status = Draft` → redirect to index.
3. Edit → `courses.edit` (policy `update`, ownership) → `Edit.vue` → `UpdateCourseRequest` → save.
4. Publish → `courses.publish` (policy `publish`) → `PublishCourse` Action → back with status.
5. Archive → `courses.archive` (policy `archive`) → `ArchiveCourse` Action → back with status.
6. Delete → `courses.destroy` (policy `delete`) → soft delete → back with status.

## Error Handling

- Unauthorized writes → `403` (policy denial). Guests → redirect to `login` (auth middleware).
- Validation failures → Inertia form errors surfaced on the field.
- Editing/publishing another instructor's course → `403` (ownership check).

## Testing (Pest feature tests, built test-first)

**Policy / authorization:**
- Instructor can update/publish/archive/delete **own** course.
- Instructor **cannot** update/publish/archive/delete **another** instructor's course (403).
- Student is forbidden from every course write (403) and from `create`/`index` gated actions.
- Admin can manage any course.
- Guests are redirected to login from course routes.

**Feature:**
- `index` shows only the instructor's own courses; shows all for an admin.
- `store` creates a Draft course owned by the actor with a unique slug (collision appends suffix).
- `update` changes fields but not the slug.
- `PublishCourse` sets status Published and stamps `published_at`.
- `ArchiveCourse` sets status Archived.
- `destroy` soft-deletes (row remains with `deleted_at`).
- Shared `auth.user.can.create_courses` is true for instructors, false for students.

## Files Touched

**New — PHP:** `app/Policies/CoursePolicy.php`, `app/Http/Controllers/CourseController.php`,
`app/Http/Controllers/PublishCourseController.php`, `app/Http/Controllers/ArchiveCourseController.php`,
`app/Http/Requests/Course/StoreCourseRequest.php`, `app/Http/Requests/Course/UpdateCourseRequest.php`,
`app/Actions/PublishCourse.php`, `app/Actions/ArchiveCourse.php`.

**New — Vue:** `Pages/Courses/Index.vue`, `Pages/Courses/Create.vue`, `Pages/Courses/Edit.vue`,
`Components/CourseForm.vue`.

**New — Tests:** `tests/Feature/Courses/CourseManagementTest.php`,
`tests/Feature/Courses/CourseAuthorizationTest.php`.

**Modified:** `routes/web.php`, `app/Http/Middleware/HandleInertiaRequests.php`,
`resources/js/Layouts/AuthenticatedLayout.vue`.

**No new dependencies.**
