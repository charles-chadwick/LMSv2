<?php

namespace App\Http\Controllers;

use App\Actions\CompleteLesson;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    public function show(Request $request, Course $course, Lesson $lesson): Response
    {
        abort_unless($lesson->module->course_id === $course->id, 404);

        $this->authorize('learn', $course);

        $course->load('modules.lessons');
        $ordered_lessons = $course->modules->flatMap(fn ($module) => $module->lessons)->values();

        $index = $ordered_lessons->search(fn ($item): bool => $item->id === $lesson->id);
        $prev = $index > 0 ? $ordered_lessons[$index - 1] : null;
        $next = $index < $ordered_lessons->count() - 1 ? $ordered_lessons[$index + 1] : null;

        $enrollment = $request->user()->enrollments()->where('course_id', $course->id)->first();

        if ($enrollment !== null) {
            CompleteLesson::run($enrollment, $lesson);
        }

        $completed_lesson_ids = $enrollment
            ? $enrollment->lessonCompletions()->pluck('lesson_id')->all()
            : [];

        return Inertia::render('Lessons/Show', [
            'course' => [
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'lesson' => [
                'id' => $lesson->id,
                'slug' => $lesson->slug,
                'title' => $lesson->title,
                'content' => $lesson->content,
            ],
            'prev' => $prev ? ['title' => $prev->title, 'slug' => $prev->slug] : null,
            'next' => $next ? ['title' => $next->title, 'slug' => $next->slug] : null,
            'is_complete' => in_array($lesson->id, $completed_lesson_ids, true),
            'progress_percentage' => $enrollment ? $enrollment->progress_percentage : 0,
        ]);
    }
}
