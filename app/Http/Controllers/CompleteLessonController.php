<?php

namespace App\Http\Controllers;

use App\Actions\CompleteLesson;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompleteLessonController extends Controller
{
    public function __invoke(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        abort_unless($lesson->module->course_id === $course->id, 404);

        $this->authorize('learn', $course);

        $enrollment = $request->user()->enrollments()->where('course_id', $course->id)->first();

        abort_unless($enrollment !== null, 403);

        CompleteLesson::run($enrollment, $lesson);

        return back()->with('status', 'Lesson completed.');
    }
}
