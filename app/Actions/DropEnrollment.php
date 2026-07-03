<?php

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Lorisleiva\Actions\Concerns\AsAction;

class DropEnrollment
{
    use AsAction;

    /**
     * Drop an enrollment by transitioning its status to Dropped, preserving progress.
     */
    public function handle(Enrollment $enrollment): Enrollment
    {
        if (! $enrollment->status->isDroppable()) {
            return $enrollment;
        }

        $enrollment->update(['status' => EnrollmentStatus::Dropped]);

        return $enrollment;
    }
}
