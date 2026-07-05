<?php

namespace App\Models\Concerns\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class CallbackFilter implements Filter
{
    public function __construct(private Closure $callback) {}

    public function apply(Builder $query, mixed $value): void
    {
        ($this->callback)($query, $value);
    }
}
