<?php

namespace App\Models\Concerns\Filters;

use Illuminate\Database\Eloquent\Builder;

class ExactFilter implements Filter
{
    public function __construct(private string $column) {}

    public function apply(Builder $query, mixed $value): void
    {
        if (is_array($value)) {
            $query->whereIn($this->column, $value);

            return;
        }

        $query->where($this->column, $value);
    }
}
