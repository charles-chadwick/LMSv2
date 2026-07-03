<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class ReorderLessons
{
    use AsAction;

    /**
     * Rewrite lesson positions within a module to the given order.
     *
     * @param  array<int, int>  $ordered_lesson_ids
     *
     * @throws ValidationException
     */
    public function handle(Module $module, array $ordered_lesson_ids): void
    {
        $actual_ids = $module->lessons()->pluck('id')->all();

        if (! $this->isSameSet($actual_ids, $ordered_lesson_ids)) {
            throw ValidationException::withMessages([
                'lessons' => 'The provided lessons do not match this module.',
            ]);
        }

        foreach ($ordered_lesson_ids as $position => $lesson_id) {
            Lesson::whereKey($lesson_id)->update(['position' => $position]);
        }
    }

    /**
     * @param  array<int, int>  $a
     * @param  array<int, int>  $b
     */
    protected function isSameSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }
}
