<?php

namespace App\Actions;

use App\Models\Enrollment;
use App\Models\Lesson;
use Lorisleiva\Actions\Concerns\AsAction;

class CompleteLesson
{
    use AsAction;

    /**
     * Mark a lesson complete for an enrollment and recalculate its progress.
     */
    public function handle(Enrollment $enrollment, Lesson $lesson): Enrollment
    {
        $enrollment->lessonCompletions()->firstOrCreate(
            ['lesson_id' => $lesson->id],
            ['completed_at' => now()],
        );

        $totalLessons = $enrollment->course->lessons()->count();
        $completedLessons = $enrollment->lessonCompletions()->count();

        $enrollment->update([
            'progress_percentage' => $totalLessons > 0
                ? (int) round($completedLessons / $totalLessons * 100)
                : 0,
        ]);

        return $enrollment;
    }
}
