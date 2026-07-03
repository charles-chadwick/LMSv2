<?php

namespace App\Actions;

use App\Enums\CourseStatus;
use App\Models\Course;
use Lorisleiva\Actions\Concerns\AsAction;

class PublishCourse
{
    use AsAction;

    /**
     * Publish a course, stamping the first publication time.
     */
    public function handle(Course $course): Course
    {
        $course->update([
            'status' => CourseStatus::Published,
            'published_at' => $course->published_at ?? now(),
        ]);

        return $course;
    }
}
