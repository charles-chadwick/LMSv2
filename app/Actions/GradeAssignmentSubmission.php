<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GradeAssignmentSubmission
{
    use AsAction;

    /**
     * Record a grade, feedback and grader for an assignment submission.
     */
    public function handle(AssignmentSubmission $submission, float $score, ?string $feedback = null, ?User $grader = null): AssignmentSubmission
    {
        $submission->update([
            'status' => SubmissionStatus::Graded,
            'score' => $score,
            'feedback' => $feedback,
            'graded_by' => $grader?->id,
            'graded_at' => now(),
        ]);

        return $submission;
    }
}
