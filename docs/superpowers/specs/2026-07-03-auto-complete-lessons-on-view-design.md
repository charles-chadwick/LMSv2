# Auto-complete lessons on view

## Goal

Lessons are marked complete automatically when a student views them. The manual
"Mark as Complete" button is removed. Because completion is view-driven, every
lesson (including the last one, which has no "Next" link) can be completed, so
`progress_percentage` can reach 100%.

## Terminology

The UI's "page" is a **Lesson** (Course → Module → Lesson). Completion is tracked
by **LessonCompletion** (unique per `enrollment_id` + `lesson_id`).

## Behavior

- When an **enrolled student** issues `GET lessons.show`, the current lesson is
  marked complete as a side effect. Idempotent: re-viewing creates no new row and
  does not change `completed_at`.
- **Previewing instructors** and **non-enrolled users** do not generate
  completions. This mirrors the existing authorization on the old complete action
  (enrollment required; instructor preview forbidden from completing).
- **Guests** are redirected to login (unchanged).
- The progress bar, "Completed ✓" badge, and Prev/Next links remain.

## Changes

### Backend

- `app/Http/Controllers/LessonController.php` (the `lessons.show` handler):
  after resolving the viewer's enrollment, if the viewer is an enrolled student,
  call `CompleteLesson::run($enrollment, $lesson)` for the current lesson. The
  action already upserts the completion idempotently and recomputes
  `progress_percentage`, so viewing updates progress automatically.
- Reuse the existing `app/Actions/CompleteLesson.php` unchanged.
- Remove the now-unused manual-completion path:
  - `lessons.complete` POST route in `routes/web.php`.
  - `app/Http/Controllers/CompleteLessonController.php`.

### Frontend

- `resources/js/Pages/Lessons/Show.vue`:
  - Remove the "Mark as Complete" button, the `markComplete` handler, the
    `completing` ref, and the `router.post` call.
  - Remove `can_complete` usage tied to the button. Keep `is_complete` /
    `progress_percentage` display (badge + progress bar) and the Prev/Next links.

## Testing

Rewrite `tests/Feature/Lessons/LessonCompletionTest.php` so cases assert the
on-view trigger instead of the POST endpoint. No coverage is dropped:

- Enrolled student views a lesson → one LessonCompletion created, progress
  reflects it (e.g. 50% of 2 lessons).
- Viewing the same lesson twice → still one completion (idempotent), progress
  unchanged on the second view.
- Viewing all lessons → progress reaches 100% (covers the last lesson).
- Previewing instructor views a lesson → no completion created.
- Non-enrolled user → forbidden, no completion.
- Guest → redirected to login, no completion.
- Lesson belonging to another course → 404 (unchanged).

## Trade-off

Completion now happens on a GET request — a side effect on read. This is standard
for "mark as read" progress tracking and keeps the flow simple. Consequence: the
first lesson a student opens is counted complete immediately. This is the intended
"complete on view" semantic.
