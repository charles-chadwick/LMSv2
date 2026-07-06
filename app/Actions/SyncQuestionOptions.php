<?php

namespace App\Actions;

use App\Models\Question;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncQuestionOptions
{
    use AsAction;

    /**
     * Reconcile a question's options against the submitted set: update matched
     * rows, create new ones, and soft-delete any that were removed. Option
     * position follows the submitted order.
     *
     * @param  array<int, array{id?: int|null, text: string, is_correct?: bool}>  $options
     */
    public function handle(Question $question, array $options): void
    {
        $kept_ids = [];

        foreach (array_values($options) as $position => $option) {
            $attributes = [
                'text' => $option['text'],
                'is_correct' => (bool) ($option['is_correct'] ?? false),
                'position' => $position,
            ];

            $existing = ! empty($option['id'])
                ? $question->options()->whereKey($option['id'])->first()
                : null;

            if ($existing !== null) {
                $existing->update($attributes);
                $kept_ids[] = $existing->id;

                continue;
            }

            $kept_ids[] = $question->options()->create($attributes)->id;
        }

        $question->options()->whereKeyNot($kept_ids)->delete();
    }
}
