# Course Completion + Certificates — Design

**Date:** 2026-07-05
**Branch:** (new, off main)
**Status:** Approved (pending spec review)

## Goal

Complete the learning loop: when a student finishes the last lesson, automatically
mark the course complete and issue a certificate, then let the student view it
(print-to-PDF) and let anyone publicly verify it by serial number.

## Context

The domain layer already exists and is well-built:

- `App\Actions\CompleteCourse` — sets enrollment `status=Completed`, `progress=100`,
  `final_grade`, `completed_at`, freezes a versioned `content_snapshot`, and issues a
  `Certificate` via `firstOrCreate` (idempotent). Takes `?float $finalGrade`.
- `App\Models\Certificate` — `enrollment`/`student`/`course` relations, `serial_number`
  (auto UUID on create), `final_grade`, `issued_at`; `HasMedia`, `SoftDeletes`.
- Schema present: `enrollments.{final_grade, content_snapshot, completed_at}` and the
  `certificates` table. `Enrollment::certificate(): HasOne` exists.
- `App\Actions\CompleteLesson` recalculates `progress_percentage` but never fires
  `CompleteCourse`. It is called automatically from `LessonController@show`
  (`CompleteLesson::run($enrollment, $lesson)`) for Active enrolled students
  (auto-complete-on-view slice).

Gaps (this slice): the completion trigger, certificate viewing (authed), public
verification, and the My Courses link. No graded assessments exist yet, so completion
is purely "all lessons complete" and `final_grade` is `null` for now.

## Decisions (from brainstorming)

- **Trigger:** automatic when the last lesson completes (inline in `CompleteLesson`).
- **Format:** in-app HTML certificate view; browser print/save-as-PDF. No new
  dependency. `HasMedia` stays available for a future server-PDF option.
- **Verification:** public guest route by serial number, reading from the frozen
  `content_snapshot`.

## Components

### 1. Completion trigger — `CompleteLesson::handle` (modify)

After recalculating progress, fire completion exactly once:

```php
$enrollment->update([...]); // existing progress update

if (
    $enrollment->status === EnrollmentStatus::Active
    && $totalLessons > 0
    && $completedLessons >= $totalLessons
) {
    CompleteCourse::run($enrollment);
}

return $enrollment->refresh();
```

- The `status === Active` guard makes it fire only on the Active→Completed
  transition: a `Completed` student re-viewing a lesson does not reset
  `completed_at`/snapshot (status is no longer Active).
- `$totalLessons > 0` guard: empty courses never auto-complete.
- `CompleteCourse::run($enrollment)` is called with no grade (defaults `finalGrade`
  to `null`).
- No change to `LessonController` (the trigger lives in the action).

### 2. `CertificatePolicy` (new)

```php
public function view(User $user, Certificate $certificate): bool
{
    return $certificate->user_id === $user->id;
}
```
Admins pass via the existing `Gate::before`. Auto-discovered (existing convention).

### 3. `Certificate` route key (modify model)

```php
public function getRouteKeyName(): string
{
    return 'serial_number';
}
```
UUID serials are unguessable and shared between the authed show and public verify.

### 4. `CertificateController` (new)

- `index(Request $request): Response` — the student's own certificates
  (`$request->user()->certificates()` — add `User::certificates(): HasMany` on
  `user_id`), latest first, each shaped `{serial_number, course_title, issued_at,
  final_grade}` (course_title from the certificate's `course` relation). Renders
  `Certificates/Index`.
- `show(Certificate $certificate): Response` — `authorize('view', $certificate)`;
  renders `Certificates/Show` with `{student_name, course_title, issued_at, serial_number,
  final_grade}` drawn from the certificate + its frozen enrollment snapshot
  (`certificate.enrollment.content_snapshot['course']['title']` for authenticity;
  fall back to the `course` relation title if snapshot missing).
- `verify(string $serial): Response` — **public** (no auth). Looks up the certificate
  by `serial_number`; renders `Certificates/Verify` with either
  `{valid: true, student_name, course_title, issued_at, serial_number}` (from the
  frozen snapshot) or `{valid: false}` for an unknown serial (HTTP 200, friendly
  invalid state — not a 404).

### 5. Routes (`routes/web.php`)

Inside the existing `auth` + `verified` group:
```php
Route::get('certificates', [CertificateController::class, 'index'])->name('certificates.index');
Route::get('certificates/{certificate}', [CertificateController::class, 'show'])->name('certificates.show');
```
Standalone public route (outside both `auth` and `guest` groups, so guests AND
logged-in users can reach it):
```php
Route::get('certificates/verify/{serial}', [CertificateController::class, 'verify'])->name('certificates.verify');
```
Note: register `certificates/verify/{serial}` so it does not collide with
`certificates/{certificate}` — the verify route is public and outside the auth group,
and `{certificate}` binds by `serial_number`; the literal `verify/` segment path
differs, so ordering is unambiguous.

### 6. Vue pages (new)

- `Certificates/Index.vue` — list of earned certificates (course title, issued date,
  links to show + a "copy verify link").
- `Certificates/Show.vue` — styled, print-friendly certificate (name, course, issue
  date, serial; `final_grade` row shown only when non-null). Includes a Print button
  (`window.print()`), and print CSS to render cleanly.
- `Certificates/Verify.vue` — public verification result: "Verified ✓" card with
  name/course/issued/serial, or an "invalid certificate" state.

### 7. My Courses link (`EnrollmentController@index` + `Enrollments/Index.vue`)

- Eager-load the certificate: `->with('course:id,title,slug', 'certificate:id,enrollment_id,serial_number')`.
- Add to the row mapping: `'certificate_serial' => $enrollment->certificate?->serial_number`.
- In `Enrollments/Index.vue`, when `enrollment.certificate_serial` is present, render a
  "View certificate" link to `route('certificates.show', enrollment.certificate_serial)`.

## Data Flow

1. Student views the final lesson → `LessonController@show` → `CompleteLesson::run`.
2. `CompleteLesson` recalcs progress; at 100% + Active → `CompleteCourse::run`.
3. `CompleteCourse` flips the enrollment to Completed, freezes the snapshot, and
   `firstOrCreate`s the certificate.
4. My Courses now shows the enrollment as Completed with a certificate link.
5. The student opens `certificates.show` (print-to-PDF); anyone opens
   `certificates.verify/{serial}` to confirm authenticity from the frozen snapshot.

## Error / Edge Cases

- Empty course (0 lessons): never reaches 100% → no auto-completion.
- Re-viewing a lesson after completion: status is `Completed`, guard blocks
  re-running `CompleteCourse` (no snapshot/timestamp reset).
- Dropped enrollment: status is `Dropped`, not `Active` → never auto-completes; the
  `learn` policy already blocks lesson access for dropped students anyway.
- Unknown serial on verify: friendly `{valid: false}` page (HTTP 200).
- Non-owner hitting `certificates.show`: 403 via policy (admins allowed via
  `Gate::before`).

## Out of Scope

- Server-generated PDF (HTML print covers export; `HasMedia` unused for now).
- A "course completed" notification (infra exists — easy follow-up).
- Grade computation / `final_grade` population (awaits the assignments/tests slices).
- Regenerating snapshots or certificate revocation UI.

## Testing

**Trigger (`CompleteLessonTest` / `CompleteCourseTest` feature):**
- Completing the final lesson flips the enrollment to Completed and creates exactly
  one certificate.
- Completing a non-final lesson does not complete the course or issue a certificate.
- Re-running `CompleteLesson` on a Completed enrollment does not change `completed_at`
  or create a second certificate.
- A course with 0 lessons never auto-completes.

**Certificate viewing (`CertificateTest` feature):**
- Owner can view `certificates.show`; a different student gets 403; an admin can view.
- `certificates.index` lists only the requesting user's certificates.
- Route-model binding resolves by `serial_number`.

**Public verify (`CertificateVerifyTest` feature):**
- A guest (unauthenticated) can open `certificates.verify/{serial}` and sees
  `valid: true` with name/course/issued from the snapshot.
- An unknown serial returns `valid: false` (HTTP 200).
- The displayed course title comes from the frozen snapshot (rename the course after
  issuance; verify still shows the original title).

**My Courses (`EnrollmentTest` / existing):**
- A completed enrollment exposes `certificate_serial`; an in-progress one exposes null.

All feature tests `seed(RolePermissionSeeder::class)` in `beforeEach`. Full suite green
+ pint clean before merge.

## Success Criteria

- Viewing the last lesson issues a certificate automatically, once.
- Student can view + print their certificate; My Courses links to it.
- Anyone can verify a certificate by serial, with data from the frozen snapshot.
- No new Composer/npm dependency added.
