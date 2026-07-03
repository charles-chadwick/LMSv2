# Enrollment Lifecycle — Design

**Date:** 2026-07-03
**Slice:** Enrollment lifecycle (drop / re-enroll / instructor removal)

## Purpose

Close the loop on the student course experience: a student can **drop** a course they are enrolled in, and later **re-enroll** and resume where they left off. Instructors can **remove** an active student from their own course. This is the sixth application-layer slice.

## Decisions

1. **Drop is a status transition, not a delete.** Dropping sets `Enrollment::status` to `Dropped`; progress and lesson completions are preserved. This matches the pre-existing `EnrollmentStatus::Dropped` enum case and avoids the soft-delete/unique-index landmine (nothing soft-deletes enrollments).
2. **Two actors:** student self-drop **and** instructor removal, sharing one authorization ability and one HTTP endpoint.
3. **Re-enroll resumes.** Re-enrolling a `Dropped` enrollment reactivates the same row to `Active` with progress intact — no reset, no new row.
4. **Only `Active` enrollments can be dropped.** `Completed` cannot be dropped (would erase a completion the next slice depends on); dropping an already-`Dropped` row is invalid.

## State machine

```
Active ──drop / remove──▶ Dropped
Dropped ──re-enroll──────▶ Active     (same row, progress preserved)
Completed ──✗──▶                       (cannot be dropped)
```

## Domain layer

- **`DropEnrollment` action (new)** — `handle(Enrollment $enrollment): Enrollment`. Sets `status = Dropped` and saves. Leaves `progress_percentage`, `lessonCompletions`, and `enrolled_at` untouched.
- **`EnrollStudent` action (modified)** — currently `firstOrCreate` returns an existing row unchanged, so a `Dropped` row would never reactivate. Change to find-or-new by `(user_id, course_id)`:
  - new row → create with `status = Active`, `enrolled_at = now()`;
  - existing `Dropped` row → flip `status` to `Active`, preserve original `enrolled_at`;
  - existing `Active`/`Completed` row → return unchanged.

  This doubles as the regression fix for the re-enroll landmine: because drop never soft-deletes, the `unique(user_id, course_id)` index is never violated.

No migration — the `status` column and `Dropped` enum case already exist.

## Backend surface

- **`EnrollmentPolicy` (new)** — `drop(User $user, Enrollment $enrollment): bool`: true only when `enrollment->status === Active` **and** (`enrollment->user_id === $user->id` **or** `enrollment->course->instructor_id === $user->id`). Admins bypass via the existing `Gate::before`. One ability serves both self-drop and instructor removal.
- **`DELETE enrollments/{enrollment}` → `EnrollmentController@destroy`** — authorizes `drop`, runs `DropEnrollment`, redirects `back()` with a status message. Both student and instructor use this same route; the policy decides.
- **`GET courses/{course}/students` → roster** (new `Course\RosterController@index` or a method on `EnrollmentController`, matching sibling structure). Authorized by a new **`CoursePolicy::viewRoster`** (`$course->instructor_id === $user->id && $user->can('update courses')`, consistent with the `update` ability). Renders enrolled students: name, status, progress, enrolled date.

## Frontend

- **`Enrollments/Index.vue` (My Courses)** — add a **Drop** button on each `Active` row; `Dropped` rows keep their badge with no drop button (history stays visible; re-enroll happens via the catalog). Add `enrollment id` to the mapped props.
- **`Catalog/Show.vue`** — when enrolled and `Active`, show a **Drop course** button; add `enrollment_id` and `status` to props. Re-enroll uses the existing enroll button/flow.
- **`Courses/Roster.vue` (new)** — table of students with a **Remove** button per `Active` student, linked from the instructor's course management surface (matching how the curriculum link is exposed).
- Destructive actions use a **confirmation step consistent with the existing module/lesson delete** convention.

## Testing

Pest feature + unit tests, seeding `RolePermissionSeeder` in `beforeEach` (per project convention):

- Student drops own `Active` enrollment → status `Dropped`, progress + completions preserved.
- Drop of a `Completed`, already-`Dropped`, or another user's enrollment → `403`.
- Instructor removes an `Active` student from own course; cannot remove from another instructor's course.
- **Re-enroll after drop** → same row reactivated to `Active`, progress intact, no duplicate row, no 500 (landmine regression).
- Roster page authorization + render.
- Unit: `DropEnrollment` sets status to `Dropped`; `EnrollStudent` reactivates a `Dropped` row.

## Out of scope

- Instructor bulk actions on the roster.
- Drop reasons / audit trail.
- Notifications or emails on drop/removal.
- Changing the `enrollments` soft-delete unique index (stays dormant; memory note to be updated).

## Established conventions applied

Thin controllers authorize via policy + delegate to Actions; validation in FormRequests where input exists; policies auto-discovered; admins bypass via `Gate::before`; features gated behind `auth` + `verified`; Vue pages built alongside their controller; course/lesson route-model binding by slug; course tests seed `RolePermissionSeeder`.
