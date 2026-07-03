# Lesson Viewing + Progress — Design Spec

**Date:** 2026-07-02
**Status:** Approved for planning
**Phase:** Fourth application-layer slice — lesson consumption; wires the existing `CompleteLesson` action to the UI.

## Context

The catalog, course detail (with a read-only syllabus), and self-enroll shipped in the prior
slice. `CompleteLesson` (a Loris Leiva action doing an idempotent `firstOrCreate` on the
enrollment's lesson completions, then recomputing `progress_percentage`) already exists but is
only exercised by the seeder. This slice builds the consumer side: enrolled users read a
lesson's content, navigate prev/next through the course, and mark lessons complete to drive
progress. It reuses the Controller→Policy→Action→Inertia pattern from prior slices.

## Decisions (locked)

| Decision | Choice |
|----------|--------|
| Lesson access | **Enrolled users + instructor/admin preview.** A user may view lessons if they are enrolled in the course OR are the course's instructor; admins pass via `Gate::before`. Draft courses included (so instructors preview their own). |
| Mark complete | **Explicit "Mark as complete" button** (POST), calling `CompleteLesson`. Idempotent; re-reading has no side effect. |
| Course completion | **Deferred.** `CompleteLesson` updates `progress_percentage`; reaching 100% shows as complete in the UI but does NOT finalize the enrollment or issue a certificate (that is a later slice using `CompleteCourse`). |
| Completing eligibility | Only an **enrolled** user can mark complete (requires an `Enrollment` row). A previewing instructor/admin who is not enrolled can view but not complete. |
| Lesson route binding | **By slug** — add `getRouteKeyName()` to `Lesson` (consistent with `Course`). Payloads carry the lesson **id** as the stable key for completion membership and prev/next. |
| Entry point | Modify `Catalog/Show.vue`: when the user can learn, syllabus lesson titles become links (with a ✓ on completed lessons) and a "Start/Continue learning" button jumps to the first incomplete lesson. |

## Non-Goals (deferred to later slices)

- Course completion, content snapshot, and certificate issuance (`CompleteCourse`).
- Un-complete / reset progress.
- Rich lesson content (media, video); lessons render their stored text `content`.
- Assignments, tests, discussions attached to lessons.
- Enrollment lifecycle (drop/unenroll) — see the known soft-delete/unique-index caveat in project memory.

## Architecture

Thin controllers authorize via `CoursePolicy` and delegate; marking complete is the existing
`CompleteLesson` action. Prev/next and completion state are computed server-side and passed as
explicit Inertia props. Vue pages follow prior-slice conventions.

### Authorization — add `learn` to `CoursePolicy`

`learn(User $user, Course $course): bool` →
`$user->enrollments()->where('course_id', $course->id)->exists() || $course->instructor_id === $user->id`.

Admins bypass via the existing `Gate::before`. No published-status check — enrollment implies the
course was published when joined, and instructor preview must work on Draft courses. Invoked as
`$this->authorize('learn', $course)`.

### Model change

`app/Models/Lesson.php` — add `getRouteKeyName(): string { return 'slug'; }` so lesson route-model
binding resolves by slug. (Lesson `slug` is unique; no other lesson routes exist yet.)

### Controllers — `app/Http/Controllers/`

- `LessonController@show(Request $request, Course $course, Lesson $lesson): Response`
  - `abort_unless($lesson->module->course_id === $course->id, 404)` — the slugged lesson must
    belong to this course (slugs are globally unique, so a foreign lesson slug 404s here).
  - `$this->authorize('learn', $course)`.
  - Build the course's lessons **ordered by module position then lesson position** to derive the
    previous/next lesson (each as `{title, slug}` or null).
  - Resolve the current user's enrollment for the course (may be null for a previewing
    instructor/admin); completed-lesson id set = that enrollment's `lessonCompletions` lesson ids
    (empty when not enrolled).
  - Render `Lessons/Show` with: `course` (`{title, slug}`), `lesson` (`{id, title, content}`),
    `prev`/`next` (`{title, slug}|null`), `is_complete` (bool: current lesson id in the completed
    set), `can_complete` (bool: user is enrolled), and `progress_percentage` (int, 0 when not
    enrolled).
- `CompleteLessonController@__invoke(Request $request, Course $course, Lesson $lesson): RedirectResponse`
  - `abort_unless($lesson->module->course_id === $course->id, 404)`.
  - `$this->authorize('learn', $course)`.
  - `$enrollment = $request->user()->enrollments()->where('course_id', $course->id)->first();`
    `abort_unless($enrollment !== null, 403)` — previewing instructors/admins (not enrolled) cannot
    complete.
  - `CompleteLesson::run($enrollment, $lesson)`; redirect back with status.

### Catalog detail change — `CourseCatalogController@show`

Extend the existing `show` payload so the syllabus can become interactive:
- Add `can_learn` (bool) = `$request->user()->can('learn', $course)`.
- Each syllabus lesson gains `id`, `slug` (alongside `title`).
- Add `completed_lesson_ids` (array) = the current user's completed lesson ids for this course
  (empty when not enrolled), so the syllabus can render ✓ marks.
- Add `first_incomplete_lesson_slug` (string|null) for the "Start/Continue learning" button
  target (first lesson, in order, not in the completed set; null when all complete or none exist).

### Routes — `routes/web.php` (inside the `auth` → `verified` group)

```php
Route::get('learn/{course}/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
Route::post('learn/{course}/{lesson}/complete', CompleteLessonController::class)->name('lessons.complete');
```

Both `{course}` and `{lesson}` bind by slug.

### Lesson ordering

The course's lessons in learning order = `Course::lessons()` (hasManyThrough Lesson via Module),
ordered by `modules.position` then `lessons.position`. This ordered collection drives prev/next
(in `LessonController`) and `first_incomplete_lesson_slug` (in `CourseCatalogController`).

### Frontend — `resources/js/`

- `Pages/Lessons/Show.vue` — lesson header (course title link, lesson title), the lesson `content`
  (whitespace-preserved text), a progress bar (`progress_percentage`), a "Mark as complete" button
  (shown when `can_complete` and not `is_complete`; a "Completed ✓" state otherwise), and prev/next
  links (disabled when null). Marking complete uses `router.post(route('lessons.complete', [course.slug, lesson-slug]))`.
- `Pages/Catalog/Show.vue` (modify) — when `can_learn`: render syllabus lesson titles as `<Link>`s to
  `lessons.show` with a ✓ for completed ones, and a "Start learning" / "Continue" button to
  `first_incomplete_lesson_slug` (hidden when null). When not `can_learn`: unchanged read-only text.

### Shared data

No new shared props; `learn` is a per-course authorize call, not a global ability.

## Data Flow

1. Enrolled user on `Catalog/Show` clicks a syllabus lesson (or "Continue") → `lessons.show`.
2. `LessonController@show` authorizes `learn`, computes prev/next + completion → `Lessons/Show`.
3. User clicks "Mark as complete" → POST `lessons.complete` → enrollment resolved (403 if none) →
   `CompleteLesson::run` (idempotent, recomputes progress) → redirect back; button + progress update.
4. Prev/next links move through lessons in module→lesson position order.

## Error Handling

- Non-enrolled student viewing a lesson → `403` (policy `learn`).
- Lesson slug not belonging to the course in the URL → `404` (`abort_unless`).
- Previewing instructor/admin marking complete → `403` (no enrollment row).
- Guest → redirect to login (`auth`); unverified → verification notice (`verified`).
- Double "Mark as complete" → no duplicate (`firstOrCreate`), progress stable.

## Testing (Pest feature tests, built test-first)

**Policy:**
- `learn` true for an enrolled user, true for the course instructor, true for admin, false for an
  unrelated user.

**Lesson view:**
- Enrolled user sees `Lessons/Show` with lesson content, correct prev/next, and completion state.
- A lesson whose slug belongs to a different course → 404 under this course.
- Non-enrolled, non-instructor user → 403.
- Instructor preview: 200, `can_complete` false.
- Prev/next order is correct across module boundaries (module 0 last lesson → module 1 first lesson).

**Mark complete:**
- Enrolled user marks a lesson complete → `LessonCompletion` row exists and `progress_percentage`
  is recomputed (e.g. 1 of 2 lessons → 50).
- Idempotent — second POST creates no duplicate and progress is unchanged.
- Non-enrolled previewing instructor → 403, no completion created.
- Guest → redirect to login.

**Catalog detail integration:**
- For an enrolled user, `catalog.show` payload includes `can_learn` true, lesson `id`/`slug`,
  `completed_lesson_ids`, and a correct `first_incomplete_lesson_slug`.
- For a non-enrolled student, `can_learn` is false.

## Files Touched

**New — PHP:** `app/Http/Controllers/LessonController.php`,
`app/Http/Controllers/CompleteLessonController.php`.

**New — Vue:** `resources/js/Pages/Lessons/Show.vue`.

**New — Tests:** `tests/Feature/Lessons/LessonViewingTest.php`,
`tests/Feature/Lessons/LessonCompletionTest.php`.

**Modified:** `app/Models/Lesson.php` (add `getRouteKeyName`), `app/Policies/CoursePolicy.php`
(add `learn`), `app/Http/Controllers/CourseCatalogController.php` (extend `show` payload),
`routes/web.php`, `resources/js/Pages/Catalog/Show.vue`.

**Reused unchanged:** `App\Actions\CompleteLesson`, `Enrollment`/`LessonCompletion` models + factories.

**No new dependencies.**
