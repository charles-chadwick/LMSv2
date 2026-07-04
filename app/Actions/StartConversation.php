<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class StartConversation
{
    use AsAction;

    public function handle(User $initiator, User $target): Conversation
    {
        if ($initiator->is($target)) {
            abort(403);
        }

        [$student, $instructor] = $this->resolvePair($initiator, $target);

        return Conversation::firstOrCreate([
            'student_id' => $student->id,
            'instructor_id' => $instructor->id,
        ]);
    }

    /**
     * @return array{0: User, 1: User} [student, instructor]
     */
    private function resolvePair(User $a, User $b): array
    {
        $student = UserRole::Student->value;
        $instructor = UserRole::Instructor->value;

        if ($a->hasRole($student) && $b->hasRole($instructor)) {
            return [$a, $b];
        }

        if ($a->hasRole($instructor) && $b->hasRole($student)) {
            return [$b, $a];
        }

        abort(403);
    }
}
