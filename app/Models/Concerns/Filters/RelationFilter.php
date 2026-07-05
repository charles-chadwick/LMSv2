<?php

namespace App\Models\Concerns\Filters;

use Illuminate\Database\Eloquent\Builder;

class RelationFilter implements Filter
{
    public function __construct(private string $relation, private string $column) {}

    public function apply(Builder $query, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];

        $query->whereHas($this->relation, fn (Builder $query): Builder => $query->whereIn($this->column, $values));
    }
}
