<?php

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use Lorisleiva\Actions\Concerns\AsAction;

class CompleteCourse
{
    use AsAction;

    /**
     * The snapshot schema version, bumped whenever the frozen shape below changes.
     */
    public const SNAPSHOT_VERSION = 1;

    /**
     * Mark an enrollment complete: freeze the course content and issue a certificate.
     */
    public function handle(Enrollment $enrollment, ?float $finalGrade = null): Enrollment
    {
        $enrollment->update([
            'status' => EnrollmentStatus::Completed,
            'progress_percentage' => 100,
            'final_grade' => $finalGrade,
            'completed_at' => now(),
            'content_snapshot' => $this->snapshot($enrollment->course),
        ]);

        $enrollment->certificate()->firstOrCreate([], [
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
            'final_grade' => $finalGrade,
            'issued_at' => now(),
        ]);

        return $enrollment;
    }

    /**
     * Build a versioned, whitelisted snapshot of the course the student learned.
     *
     * @return array<string, mixed>
     */
    protected function snapshot(Course $course): array
    {
        $course->loadMissing('modules.lessons');

        return [
            'version' => self::SNAPSHOT_VERSION,
            'captured_at' => now()->toIso8601String(),
            'course' => [
                'title' => $course->title,
                'summary' => $course->summary,
                'description' => $course->description,
            ],
            'modules' => $course->modules->map(fn (Module $module): array => [
                'title' => $module->title,
                'description' => $module->description,
                'position' => $module->position,
                'lessons' => $module->lessons->map(fn (Lesson $lesson): array => [
                    'title' => $lesson->title,
                    'content' => $lesson->content,
                    'position' => $lesson->position,
                    'duration_minutes' => $lesson->duration_minutes,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
