# Reusable Filtering — Design

**Date:** 2026-07-04
**Status:** Approved, ready for planning

## Summary

List pages need to filter records by fields like **status** and **role**, and the
mechanism must be **reusable** so any model/list can declare its own filterable
fields — including fields that are not plain columns. The two motivating fields
are exactly the hard cases:

- **role** is a Spatie relationship (the `roles` pivot), not a column on `users`.
- **status** is *derived* from `email_verified_at` (null → "Invited", set →
  "Active"), not stored as a column at all.

So the reusable mechanism must handle own-columns, relationship-columns, derived
fields, **range/comparison** operators, and **multi-value** selection.

This mirrors the existing `Searchable` trait (homegrown, `scopeWithSearch`) with
a parallel `Filterable` trait (`scopeWithFilters`), plus a reusable Vue
`FilterBar` component. It is a cross-cutting query primitive first, adopted on
the Users list page as its first consumer.

## Architecture: Filter strategy objects

A small `Filter` interface with one method, and a handful of concrete strategies.
A model declares `filterableFields(): array` mapping each filter param to a
strategy instance. Chosen over stringly-typed array config (awkward for derived
fields) and over `spatie/laravel-query-builder` (new dependency needing approval;
diverges from the homegrown `Searchable` convention already in the codebase).

### Files

- `app/Models/Concerns/Filterable.php` — the trait.
- `app/Models/Concerns/Filters/Filter.php` — the interface.
- `app/Models/Concerns/Filters/ExactFilter.php`
- `app/Models/Concerns/Filters/RelationFilter.php`
- `app/Models/Concerns/Filters/RangeFilter.php`
- `app/Models/Concerns/Filters/CallbackFilter.php`

(Strategies live under the existing `app/Models/Concerns/` area — no new base
folder, per project convention.)

### `Filter` interface

```php
interface Filter
{
    /**
     * Apply this filter's constraint for the given value to the query.
     */
    public function apply(Builder $query, mixed $value): void;
}
```

### Strategies

- **`ExactFilter(string $column)`** — scalar value → `where($column, $value)`;
  array value → `whereIn($column, $values)` (multi-value).
- **`RelationFilter(string $relation, string $column)`** — normalizes the value
  to an array and applies
  `whereHas($relation, fn ($q) => $q->whereIn($column, $values))`.
  Handles **role** via the Spatie `roles` relation (`name` column).
- **`RangeFilter(string $column, bool $asDate = false)`** — value is an
  array shape `array{from?: string|null, to?: string|null}`. Applies
  `>=`/`<=` bounds, skipping any absent bound. When `$asDate` is true it uses
  `whereDate(...)` so a date-only `to` is inclusive of that whole day.
- **`CallbackFilter(Closure $callback)`** — escape hatch; `apply()` calls
  `($this->callback)($query, $value)`. Used for **derived status**.

### `Filterable` trait

```php
trait Filterable
{
    /**
     * Map of filter param => Filter strategy. Override per model.
     *
     * @return array<string, Filter>
     */
    abstract protected function filterableFields(): array;

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
```

Behavior notes:

- Only **declared** fields are consulted; unknown keys in the request are
  ignored (safe — no arbitrary-column filtering).
- Empty values (`null`, `''`, `[]`) are skipped so an empty control is a no-op.
- Each field's constraint ANDs with the others; multi-value ORs within a field
  via `whereIn`.
- A `RangeFilter` value that is present but has both bounds empty results in no
  constraint (both bounds skipped).

## Model wiring — `User`

Add `Filterable` to the `User` trait list and:

```php
/**
 * @return array<string, Filter>
 */
protected function filterableFields(): array
{
    return [
        'role' => new RelationFilter('roles', 'name'),
        'status' => new CallbackFilter(function (Builder $query, mixed $value): void {
            $values = (array) $value;

            $query->where(function (Builder $query) use ($values): void {
                if (in_array('Active', $values, true)) {
                    $query->orWhereNotNull('email_verified_at');
                }

                if (in_array('Invited', $values, true)) {
                    $query->orWhereNull('email_verified_at');
                }
            });
        }),
        'created_at' => new RangeFilter('created_at', asDate: true),
    ];
}
```

The `status` callback is multi-value-aware and wraps its OR group so it ANDs
cleanly with other filters. If neither known status value is present the group is
empty (matches everything) — acceptable, since the empty-value skip in the trait
prevents an all-empty status from reaching here.

## Controller — `UserManagementController@index`

Add filtering alongside the existing search, and expose the current values plus
the declarative option list that drives the generic UI:

```php
$users = User::query()
    ->when(
        ! $viewer->hasRole(UserRole::Admin->value),
        fn ($query) => $query->where('created_by', $viewer->id),
    )
    ->with('roles', 'media')
    ->withSearch($request->query('search'))
    ->withFilters($request->input('filters'))
    ->latest()
    ->paginate(self::PER_PAGE)
    ->withQueryString()
    ->through(fn (User $user): array => UserManagementResource::make($user)->resolve($request));

return Inertia::render('Users/Index', [
    'users' => $users,
    'filters' => [
        'search' => $request->query('search'),
        ...$request->input('filters', []),
    ],
    'filterOptions' => $this->filterOptions(),
]);
```

A private `filterOptions(): array` returns the declarative list (see shape
below). Role options come from `UserRole::cases()`; status is Active/Invited;
`created_at` is a daterange.

`filterOptions` shape (array of):

```php
[
    'key' => 'role',
    'label' => 'Role',
    'type' => 'select',        // 'select' | 'daterange'
    'multiple' => true,
    'options' => [['value' => 'Student', 'label' => 'Student'], ...],
],
[
    'key' => 'status',
    'label' => 'Status',
    'type' => 'select',
    'multiple' => true,
    'options' => [['value' => 'Active', 'label' => 'Active'], ['value' => 'Invited', 'label' => 'Invited']],
],
[
    'key' => 'created_at',
    'label' => 'Created',
    'type' => 'daterange',
],
```

`filters` values sent back to the client: `role`/`status` are arrays (possibly
empty/absent), `created_at` is `{from, to}` (absent when unset), `search` a
string.

## Frontend — reusable `FilterBar.vue`

`resources/js/Components/FilterBar.vue`. A generic, self-contained component and
the **single owner** of list navigation.

Props:
- `filters` — current applied values (includes `search`).
- `filterOptions` — the declarative list from the controller.

Renders:
- A debounced search input (same 300ms behavior as `SearchInput`).
- One control per option:
  - `select` (with `multiple: true`) → a checkbox dropdown built on the existing
    `ui/dropdown-menu` primitives; the trigger shows the label + a count badge
    when active.
  - `daterange` → two native `<input type="date">` (from / to) writing
    `{ from, to }` under the option key.
- A "Clear filters" action, shown when any filter (excluding search) is active.

Navigation: any change builds one query and navigates:

```js
router.get(
    window.location.pathname,
    buildQuery(),   // { search, filters: { role: [...], status: [...], created_at: { from, to } } }
    { preserveState: true, preserveScroll: true, replace: true },
);
```

`buildQuery` omits empty values and always resets pagination (no `page` key).
Because the controller round-trips the full current `filters` prop, the component
derives the next query from a single source of truth (props), avoiding races
between the search box and filter controls.

The Users page (`resources/js/Pages/Users/Index.vue`) swaps its standalone
`SearchInput` for `FilterBar`, passing `filters` and the new `filterOptions`
prop. `SearchInput` is left untouched for pages that only search (e.g. Courses).

## Testing (Pest feature tests; seed `RolePermissionSeeder` in `beforeEach`)

Filter behavior exercised through the `User` model / index endpoint:

- Filter by a single role → only that role returned.
- Filter by multiple roles (`whereIn`) → union returned.
- Filter by derived status Active vs Invited (using verified/unverified users).
- Filter by multiple statuses → union returned.
- Filter by `created_at` range (from/to, inclusive `to` by date).
- Unknown filter key is ignored (no error, no effect).
- Empty filter value is a no-op (returns the unfiltered set).
- Filters combine with `search` (AND) and with the instructor `created_by`
  scope.
- Index feature test: `GET users?filters[role][]=Student` returns only students;
  the `filterOptions` prop is present with the expected keys.

Frontend: `npm run build` must succeed (compiles `FilterBar.vue` and the updated
Users page).

## Out of Scope

- Saved / named filters; per-user filter persistence.
- Adopting `Filterable` on other list pages (the trait is ready; adoption
  elsewhere is a follow-up).
- Free-form operator selection in the UI (operators are fixed per declared
  field).
- New multi-select UI dependency — the checkbox dropdown is built on existing
  primitives.
