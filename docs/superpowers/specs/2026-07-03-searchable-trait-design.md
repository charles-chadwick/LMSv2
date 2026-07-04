# Reusable `Searchable` Trait + Inertia List Search — Design

**Date:** 2026-07-03
**Branch:** feature/list-pagination
**Status:** Approved

## Summary

Add a reusable, declarative search capability to Eloquent models via a `Searchable`
trait, and wire it into the Courses management list page as an Inertia-driven,
debounced list filter. The trait supports searching own columns and related-model
columns (dot-notation), with per-field opt-in to full-text search that degrades to
`LIKE` on SQLite. Courses is the first consumer.

## Goals

- One reusable trait any model can adopt to become searchable.
- Search across relationships (e.g. a course by its instructor's name).
- Real full-text search on MariaDB for name-style fields; correct behavior on the
  SQLite test database.
- A reusable frontend search input that filters any Inertia index page via the URL.

## Non-Goals

- Multi-level relationship paths (e.g. `a.b.c`) — single-level `relation.column` only.
- Search relevance ranking / ordering by `MATCH` score (YAGNI for now).
- Global/omnisearch across multiple models. This is per-model list filtering.
- Converting the existing `StudentSearch.vue` JSON typeahead (unrelated widget).

## Conventions Established

- **Model traits that expose a query scope name it `scopeWith*`** (usage: `->withSearch(...)`).
  This is a forward-looking convention; there are no other model scope traits to retrofit.
- New model concern traits live in `app/Models/Concerns` (mirrors the existing
  `App\Actions\Concerns` namespace pattern).

## Architecture

### 1. Trait: `app/Models/Concerns/Searchable.php`

Declarative, method-based (models declare fields by implementing methods):

```php
namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Searchable
{
    /**
     * LIKE-searched fields. Each entry is an own column or a "relation.column" path.
     *
     * @return list<string>
     */
    abstract protected function searchableFields(): array;

    /**
     * Full-text-searched fields (MATCH..AGAINST on MariaDB/MySQL, LIKE fallback on SQLite).
     * Each entry is an own column or a "relation.column" path. Defaults to none.
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
}
```

Private helpers:

- `applyLikeSearch(Builder $query, string $field, string $term)`:
  - Own column → `$query->orWhere($field, 'like', "%{$term}%")`.
  - `relation.column` → `$query->orWhereHas($relation, fn ($q) => $q->where($column, 'like', "%{$term}%"))`.
- `applyFullTextSearch(Builder $query, string $field, string $term)`:
  - If driver is MariaDB/MySQL: use `orWhereFullText` (own column) or
    `orWhereHas($relation, fn ($q) => $q->whereFullText($column, $term))`.
  - Otherwise (SQLite): fall back to the same `LIKE` behavior as `applyLikeSearch`.
- Driver detection: `in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)`.

**Why a nested `where(fn)` wrapper:** all field conditions are OR'd together but must be
grouped so they AND correctly with the caller's existing constraints (e.g. the
`instructor_id = ?` role filter in `CourseController::index`).

### 2. Course model

```php
use App\Models\Concerns\Searchable;

class Course extends Model implements HasMedia
{
    use Searchable;

    protected function searchableFields(): array
    {
        return ['title', 'slug'];
    }

    protected function fullTextSearchableFields(): array
    {
        return ['instructor.name'];
    }
}
```

### 3. Migration: full-text index for `users.name`

`users.name` is currently a **VIRTUAL** generated column
(`concat_ws(' ', first_name, last_name)`). MariaDB cannot index a virtual generated
column for full text, so convert it to **STORED** and add a `FULLTEXT` index. Guarded
to skip SQLite (which keeps the virtual column and uses the `LIKE` fallback):

```php
use Illuminate\Support\Facades\DB;

public function up(): void
{
    if (DB::getDriverName() === 'sqlite') {
        return;
    }

    Schema::table('users', function (Blueprint $table) {
        $table->string('name')->storedAs("concat_ws(' ', first_name, last_name)")->change();
        $table->fullText('name');
    });
}

public function down(): void
{
    if (DB::getDriverName() === 'sqlite') {
        return;
    }

    Schema::table('users', function (Blueprint $table) {
        $table->dropFullText(['name']);
        $table->string('name')->virtualAs("concat_ws(' ', first_name, last_name)")->change();
    });
}
```

Mirror the exact driver-guarding and expression used by the original migration that
created the `name` generated column (confirm during implementation).

### 4. Controller wiring: `CourseController::index`

Add the search scope into the existing chain and pass the current term back to the view:

```php
$courses = Course::query()
    ->when(! $user->hasRole(UserRole::Admin->value),
        fn ($query) => $query->where('instructor_id', $user->id))
    ->withSearch($request->query('search'))
    ->latest()
    ->paginate(self::PER_PAGE, ['id', 'title', 'slug', 'status', 'level'])
    ->withQueryString();

return Inertia::render('Courses/Index', [
    'courses' => $courses,
    'filters' => ['search' => $request->query('search')],
]);
```

`->withQueryString()` is already present, so `search` survives pagination links.
`->withSearch()` with an empty/null term is a no-op (returns all).

### 5. Frontend: reusable `resources/js/Components/SearchInput.vue`

Debounced (300 ms) text input built on the existing `@/Components/ui/input` primitive
and the lucide `Search` icon (same building blocks as `StudentSearch.vue`).

- Props: `initial` (seed from `filters.search` for deep-links/refresh), `placeholder`,
  optional `paramName` (default `search`).
- On debounced input, performs an Inertia visit against the current page:

```js
router.get(window.location.pathname, { [paramName]: term }, {
  preserveState: true,
  preserveScroll: true,
  replace: true,
})
```

- Operates on the current URL — no route prop, so it drops into any index page.
- Sending only `{ search }` drops the `page` param, resetting to page 1 on a new
  search (correct behavior).
- `replace: true` avoids polluting browser history per keystroke.

Mounted at the top of `Courses/Index.vue`, seeded with `filters.search`.

## Data Flow

```
user types
  → SearchInput debounce (300ms)
  → Inertia GET /courses?search=<term>
  → CourseController::index: ->withSearch($term) applies grouped OR (LIKE + full-text)
  → paginate()->withQueryString()
  → Inertia::render('Courses/Index', { courses, filters })
  → Courses/Index.vue re-renders courses.data + <Pagination>
```

## Error / Edge Handling

- Empty / whitespace-only term → scope is a no-op, full list returned.
- `%` / `_` in term are treated literally by MariaDB `LIKE`; acceptable for this filter
  (not user-controlled wildcards of concern). No escaping added unless a test surfaces a need.
- New search resets pagination to page 1 by omitting `page`.
- Role filter (`instructor_id`) always ANDs with the grouped search conditions.

## Testing

Feature tests (Pest), run on SQLite `:memory:`:

1. `withSearch` matches on `title`.
2. `withSearch` matches on `slug`.
3. `withSearch` matches on `instructor.name` (relationship path; LIKE fallback on SQLite).
4. Non-matching term excludes rows.
5. Empty / whitespace term returns all rows.
6. Search ANDs with the role filter — an instructor sees only their own matching courses,
   not another instructor's matching course.
7. `CourseController::index` returns the filtered paginator and echoes `filters.search`
   in the Inertia props.

**Full-text caveat:** SQLite has no `MATCH…AGAINST`, so tests exercise the **LIKE
fallback** branch of `applyFullTextSearch`. The MariaDB full-text path is verified
manually / in a MariaDB CI environment. Where feasible, add a focused assertion that the
driver branch is selected correctly, but do not rely on SQLite to execute `whereFullText`.

## Files

- **New:** `app/Models/Concerns/Searchable.php`
- **New:** `database/migrations/<timestamp>_add_fulltext_index_to_users_name.php`
- **New:** `resources/js/Components/SearchInput.vue`
- **New:** `tests/Feature/Courses/CourseSearchTest.php` (or extend the existing course index test)
- **Edit:** `app/Models/Course.php`
- **Edit:** `app/Http/Controllers/CourseController.php`
- **Edit:** `resources/js/Pages/Courses/Index.vue`
