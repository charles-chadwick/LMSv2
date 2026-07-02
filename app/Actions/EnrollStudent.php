<?php

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class EnrollStudent
{
    use AsAction;

    /**
     * Enroll a student in a course, returning the existing enrollment if one exists.
     */
    public function handle(User $student, Course $course): Enrollment
    {
        return Enrollment::firstOrCreate(
            [
                'user_id' => $student->id,
                'course_id' => $course->id,
            ],
            [
                'status' => EnrollmentStatus::Active,
                'enrolled_at' => now(),
            ],
        );
    }
}
