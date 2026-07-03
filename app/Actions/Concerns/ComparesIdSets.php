<?php

namespace App\Actions\Concerns;

trait ComparesIdSets
{
    /**
     * Determine whether two id lists contain exactly the same elements.
     *
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
