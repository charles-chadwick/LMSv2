<?php

namespace App\Models\Concerns\Filters;

use Illuminate\Database\Eloquent\Builder;

interface Filter
{
    /**
     * Apply this filter's constraint for the given value to the query.
     */
    public function apply(Builder $query, mixed $value): void;
}
