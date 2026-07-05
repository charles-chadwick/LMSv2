<?php

namespace App\Models\Concerns\Filters;

use Illuminate\Database\Eloquent\Builder;

class RangeFilter implements Filter
{
    public function __construct(private string $column, private bool $asDate = false) {}

    /**
     * @param  array{from?: string|null, to?: string|null}|mixed  $value
     */
    public function apply(Builder $query, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

        if ($from !== null && $from !== '') {
            if ($this->asDate) {
                $query->whereDate($this->column, '>=', $from);
            } else {
                $query->where($this->column, '>=', $from);
            }
        }

        if ($to !== null && $to !== '') {
            if ($this->asDate) {
                $query->whereDate($this->column, '<=', $to);
            } else {
                $query->where($this->column, '<=', $to);
            }
        }
    }
}
