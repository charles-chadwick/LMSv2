<?php

namespace App\Actions;

use App\Enums\CourseStatus;
use App\Models\Course;
use Lorisleiva\Actions\Concerns\AsAction;

class ArchiveCourse
{
    use AsAction;

    /**
     * Archive a course so it is retired from browsing.
     */
    public function handle(Course $course): Course
    {
        $course->update([
            'status' => CourseStatus::Archived,
        ]);

        return $course;
    }
}
