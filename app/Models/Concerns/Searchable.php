<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Searchable
{
    /**
     * Fields searched with LIKE. Each is an own column or a "relation.column" path.
     *
     * @return list<string>
     */
    abstract protected function searchableFields(): array;

    /**
     * Fields searched with full text (MATCH..AGAINST on MariaDB/MySQL, LIKE on SQLite).
     * Each is an own column or a "relation.column" path.
     *
     * @return list<string>
     */
    protected function fullTextSearchableFields(): array
    {
        return [];
    }

    public function scopeWithSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            foreach ($this->searchableFields() as $field) {
                $this->applyLikeSearch($query, $field, $term);
            }

            foreach ($this->fullTextSearchableFields() as $field) {
                $this->applyFullTextSearch($query, $field, $term);
            }
        });
    }

    private function applyLikeSearch(Builder $query, string $field, string $term): void
    {
        if (str_contains($field, '.')) {
            [$relation, $column] = explode('.', $field, 2);

            $query->orWhereHas($relation, fn (Builder $query) => $query->where($column, 'like', "%{$term}%"));

            return;
        }

        $query->orWhere($field, 'like', "%{$term}%");
    }

    private function applyFullTextSearch(Builder $query, string $field, string $term): void
    {
        $uses_full_text = in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true);

        if (str_contains($field, '.')) {
            [$relation, $column] = explode('.', $field, 2);

            $query->orWhereHas($relation, function (Builder $query) use ($column, $term, $uses_full_text): void {
                if ($uses_full_text) {
                    $query->whereFullText($column, $term);
                } else {
                    $query->where($column, 'like', "%{$term}%");
                }
            });

            return;
        }

        if ($uses_full_text) {
            $query->orWhereFullText($field, $term);
        } else {
            $query->orWhere($field, 'like', "%{$term}%");
        }
    }
}
