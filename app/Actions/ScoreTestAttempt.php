<?php

namespace App\Actions;

use App\Enums\TestAttemptStatus;
use App\Models\TestAnswer;
use App\Models\TestAttempt;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class ScoreTestAttempt
{
    use AsAction;

    /**
     * Auto-grade an attempt's answers against the correct options and total the score.
     *
     * Only auto-gradable question types (multiple choice, true/false) are scored;
     * open-ended answers are left for manual grading (0 points here).
     */
    public function handle(TestAttempt $attempt, ?User $grader = null): TestAttempt
    {
        $attempt->loadMissing('answers.question.options');

        $score = $attempt->answers->sum(fn (TestAnswer $answer): float => $this->gradeAnswer($answer));

        $attempt->update([
            'status' => TestAttemptStatus::Graded,
            'score' => $score,
            'graded_by' => $grader?->id,
            'submitted_at' => $attempt->submitted_at ?? now(),
            'graded_at' => now(),
        ]);

        return $attempt;
    }

    /**
     * Grade a single answer, persist the outcome, and return the points earned.
     */
    protected function gradeAnswer(TestAnswer $answer): float
    {
        $question = $answer->question;

        if (! $question->type->isAutoGradable()) {
            return 0.0;
        }

        $correctOption = $question->options->firstWhere('is_correct', true);
        $isCorrect = $answer->question_option_id !== null
            && $answer->question_option_id === $correctOption?->id;
        $points = $isCorrect ? (float) $question->points : 0.0;

        $answer->update([
            'is_correct' => $isCorrect,
            'points_awarded' => $points,
        ]);

        return $points;
    }
}
