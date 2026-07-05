<?php

namespace App\Models\Concerns;

use App\Models\Concerns\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    /**
     * Map of filter param => Filter strategy. Override per model.
     *
     * @return array<string, Filter>
     */
    abstract protected function filterableFields(): array;

    /**
     * Apply the request's filters, consulting only declared fields.
     *
     * @param  array<string, mixed>|null  $filters
     */
    public function scopeWithFilters(Builder $query, ?array $filters): Builder
    {
        $filters ??= [];

        foreach ($this->filterableFields() as $param => $filter) {
            if (! array_key_exists($param, $filters)) {
                continue;
            }

            $value = $filters[$param];

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $filter->apply($query, $value);
        }

        return $query;
    }
}
