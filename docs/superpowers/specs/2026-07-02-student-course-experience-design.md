# Student Course Experience (Catalog, Detail, Enroll) — Design Spec

**Date:** 2026-07-02
**Status:** Approved for planning
**Phase:** Third application-layer slice — the student-facing side of courses; wires the existing `EnrollStudent` action to the UI.

## Context

Authentication and instructor course CRUD have shipped. Instructors publish courses;
`EnrollStudent` (a Loris Leiva action doing an idempotent `firstOrCreate` on user+course)
already exists but is only exercised by the seeder. This slice builds the consumer side:
authenticated users browse published courses, view a course's detail and syllabus, and
self-enroll, then see their enrollments in a "My Courses" list. It reuses the
Catalog→Detail→Enroll→List pattern established by prior slices (controllers thin, policy-gated,
Inertia pages, `useForm`/`router` for writes).

## Decisions (locked)

| Decision | Choice |
|----------|--------|
| Catalog access | **Authenticated + verified users only** (behind `auth` + `verified`, like the rest of the app). Guests hitting catalog routes are redirected to login. |
| Enroll eligibility | **Any authenticated, verified user** may self-enroll — students, instructors, and admins alike. The only gate is that the course is **Published**. No permission check. |
| Detail page | Course info (title, summary, description, instructor, level) **plus a read-only syllabus** (module + lesson titles, ordered). No lesson content/playback — deferred. |
| My Courses | Included — a page listing the current user's enrollments with status and progress. |
| Route separation | Student-facing routes (`/catalog`, `/my-courses`, enroll) live in **their own controllers**, separate from the instructor `/courses` resource, to keep the two audiences cleanly bounded. |
| Enrolling in own course | Not special-cased (harmless; `firstOrCreate` is idempotent). |

## Non-Goals (deferred to later slices)

- Lesson viewing / content playback and `CompleteLesson` wiring.
- Progress tracking UI beyond displaying `progress_percentage`.
- Un-enroll / drop flow, course completion, certificates.
- Search / filtering / pagination of the catalog (simple list for now).
- A public (guest) marketing catalog.

## Architecture

Thin controllers render Inertia pages and delegate. Enrollment is gated by a new
`EnrollmentPolicy` and performed by the existing `EnrollStudent` action. The catalog query
only ever returns Published courses. Vue pages follow the conventions of the instructor slice.

### Authorization — add `enroll` to the existing `CoursePolicy`

The enroll ability acts on a `Course`, so it belongs on `CoursePolicy` (already auto-discovered
for the `Course` model). No new policy file, no manual registration — `$this->authorize('enroll', $course)`
resolves to it directly.

- `enroll(User $user, Course $course): bool` → `$course->status === CourseStatus::Published`.

Any authenticated user passes for a published course; nobody can enroll in a Draft/Archived
course. Admins also pass via the existing `Gate::before`, which is harmless here since the
published-course rule is the only thing that matters. Catalog **viewing** needs no policy — the
routes are already behind `auth`/`verified`, and queries are scoped to Published.

### Controllers — `app/Http/Controllers/`

- `CourseCatalogController`
  - `index(Request): Response` — renders `Catalog/Index` with Published courses (id, title, slug,
    summary, level, instructor name) and, per course, whether the current user is enrolled.
  - `show(Course $course): Response` — 404 unless `status === Published`; eager-loads
    `modules.lessons` (both ordered by position) and `instructor`; passes `is_enrolled`. Renders
    `Catalog/Show`.
- `EnrollmentController`
  - `store(Course $course): RedirectResponse` — `$this->authorize('enroll', $course)`, then
    `EnrollStudent::run($request->user(), $course)`, redirect back with status.
  - `index(Request): Response` — renders `Enrollments/Index` with the current user's enrollments
    (course title/slug, status, progress_percentage).

`show` restricting to Published: use `abort_unless($course->status === CourseStatus::Published, 404)`.

### Routes — `routes/web.php` (inside the existing `auth` + `verified` group)

```php
Route::get('catalog', [CourseCatalogController::class, 'index'])->name('catalog.index');
Route::get('catalog/{course}', [CourseCatalogController::class, 'show'])->name('catalog.show');
Route::post('courses/{course}/enroll', [EnrollmentController::class, 'store'])->name('courses.enroll');
Route::get('my-courses', [EnrollmentController::class, 'index'])->name('enrollments.index');
```

Course binds by `slug` (existing `getRouteKeyName`). The `catalog/{course}` binding resolves any
course by slug; the controller enforces the Published rule via `abort_unless` so unpublished
slugs 404 rather than leaking Draft content.

### Enrolled-state detection

For the catalog list and detail, "is the current user enrolled?" is computed without N+1: load the
user's enrolled course ids once (`$request->user()->enrollments()->pluck('course_id')`) and mark
each catalog course; for `show`, check membership of that course id. `enrolled_at` is stamped by
`EnrollStudent` (`now()`), status `Active`.

### Frontend — `resources/js/`

- `Pages/Catalog/Index.vue` — grid/list of published course cards (title, level, summary, instructor);
  each links to detail and shows an "Enrolled" badge when applicable.
- `Pages/Catalog/Show.vue` — course header (title, instructor, level, description) + read-only
  syllabus (modules with nested lesson titles). Enroll button (`router.post` to `courses.enroll`)
  that renders as a disabled "Enrolled" state when `is_enrolled`.
- `Pages/Enrollments/Index.vue` — table of the user's enrollments: course title (links to detail),
  status badge, progress percentage.
- `Layouts/AuthenticatedLayout.vue` — add "Browse Courses" (`catalog.index`) and "My Courses"
  (`enrollments.index`) nav links, shown to **all** authenticated users.

### Shared data

No new `can` entries needed (enroll is not permission-gated). The existing `auth.user` shape is
unchanged.

## Data Flow

1. User clicks "Browse Courses" → `catalog.index` → list of Published courses, each flagged enrolled/not.
2. Clicks a course → `catalog.show` → detail + syllabus + `is_enrolled`.
3. Clicks "Enroll" → POST `courses.enroll` → `CoursePolicy::enroll` (course must be Published) →
   `EnrollStudent::run(user, course)` (idempotent) → redirect back; button now shows "Enrolled".
4. "My Courses" → `enrollments.index` → the user's enrollments with status + progress.

## Error Handling

- Guest hitting any route → redirect to login (`auth`); unverified → verification notice (`verified`).
- Enroll on a Draft/Archived course → `403` (policy).
- Viewing a non-published course detail by slug → `404` (`abort_unless`).
- Double-enroll (double click / re-submit) → no duplicate row (`firstOrCreate`), button idempotent.

## Testing (Pest feature tests, built test-first)

**Catalog:**
- Index lists Published courses only (Draft and Archived excluded).
- Index marks courses the current user is enrolled in.
- Guest is redirected to login from `catalog.index`.

**Detail:**
- `show` renders `Catalog/Show` with the module+lesson syllabus in position order.
- `show` on a Draft or Archived course returns 404.
- `show` passes correct `is_enrolled` (true after enrolling, false before).

**Enroll:**
- A student can enroll in a Published course → an `Enrollment` row exists (status Active, `enrolled_at` set).
- An instructor can enroll; an admin can enroll (self-enroll allowed for all roles).
- Re-enrolling is idempotent — a second POST creates no duplicate enrollment.
- Enrolling in a Draft or Archived course → 403, no enrollment created.
- Guest enroll attempt → redirect to login.

**My Courses:**
- `enrollments.index` lists only the current user's enrollments, not others'.

**Policy:**
- `CoursePolicy::enroll` returns true for any user on a Published course, false on Draft/Archived.

## Files Touched

**New — PHP:** `app/Http/Controllers/CourseCatalogController.php`,
`app/Http/Controllers/EnrollmentController.php`.

**New — Vue:** `Pages/Catalog/Index.vue`, `Pages/Catalog/Show.vue`, `Pages/Enrollments/Index.vue`.

**New — Tests:** `tests/Feature/Catalog/CourseCatalogTest.php`,
`tests/Feature/Enrollments/EnrollmentTest.php`.

**Modified:** `app/Policies/CoursePolicy.php` (add `enroll`), `routes/web.php`,
`resources/js/Layouts/AuthenticatedLayout.vue`.

**Reused unchanged:** `App\Actions\EnrollStudent`, `Enrollment` model/factory.

**No new dependencies.**
