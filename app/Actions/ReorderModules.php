<?php

namespace App\Actions;

use App\Actions\Concerns\ComparesIdSets;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class ReorderModules
{
    use AsAction, ComparesIdSets;

    /**
     * Rewrite module positions to the given order.
     *
     * @param  array<int, int>  $ordered_module_ids
     *
     * @throws ValidationException
     */
    public function handle(Course $course, array $ordered_module_ids): void
    {
        $actual_ids = $course->modules()->pluck('id')->all();

        if (! $this->isSameSet($actual_ids, $ordered_module_ids)) {
            throw ValidationException::withMessages([
                'modules' => 'The provided modules do not match this course.',
            ]);
        }

        foreach ($ordered_module_ids as $position => $module_id) {
            Module::whereKey($module_id)->update(['position' => $position]);
        }
    }
}
