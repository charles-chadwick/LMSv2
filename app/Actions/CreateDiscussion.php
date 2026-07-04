<?php

namespace App\Actions;

use App\Models\Course;
use App\Models\Discussion;
use App\Models\User;
use App\Notifications\NewDiscussionQuestion;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateDiscussion
{
    use AsAction;

    /**
     * @param  array{title: string, body: string, lesson_id?: int|null}  $data
     */
    public function handle(Course $course, User $author, array $data): Discussion
    {
        $discussion = $course->discussions()->create([
            'user_id' => $author->id,
            'lesson_id' => $data['lesson_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'],
        ]);

        $instructor = $course->instructor;

        if ($instructor !== null && $instructor->id !== $author->id) {
            $instructor->notify(new NewDiscussionQuestion($discussion));
        }

        return $discussion;
    }
}
