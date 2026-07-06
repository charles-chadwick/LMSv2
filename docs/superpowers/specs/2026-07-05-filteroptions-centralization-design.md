# FilterOptions Centralization — Design

**Date:** 2026-07-05
**Branch:** (new, off main)
**Status:** Approved (pending spec review)

## Goal

Remove the duplicated `filterOptions()` descriptor-building boilerplate copy-pasted
across six list controllers by introducing a single `FilterOption` value object.
Pure presentation-layer dedup — no change to filter behavior or query logic.

## Context

Six controllers each declare a private `filterOptions(): array` returning a list of
UI descriptors for `FilterBar.vue`:

- `CourseController` — status, level
- `CourseCatalogController` — level
- `EnrollmentController` — status
- `Course\RosterController` — status
- `UserManagementController` — role, status (inline Active/Invited), created_at
- `NotificationController` — type, read (inline unread/read), created_at

Every descriptor repeats the same array shape:
`['key' => …, 'label' => …, 'type' => 'select', 'multiple' => true, 'options' => …]`
for selects, and `['key' => …, 'label' => …, 'type' => 'daterange']` for date ranges.
~9 descriptor literals total. The `filterableFields()` methods on models
(`Course`, `Enrollment`, `User`, `Notification`) and the query `Filter` strategy
classes are the query side and are NOT touched here.

Scope decision: **dedup only, no validation layer.** The `Filterable` trait already
whitelists declared keys and binds all values (no injection surface; malformed values
no-op), so a `FilterRequest` is out of scope (YAGNI).

## Approach

**Chosen: `FilterOption` value objects with named constructors** (over co-locating
descriptors on models, or making `Filter` strategies self-describe). It removes the
real duplication — the descriptor array shape — with the smallest surface area and
keeps query and presentation concerns separate, matching how the `Filter` strategy
classes are query-only today. The residual (a filter key appearing in both
`filterableFields()` and `filterOptions()`) is minor and accepted.

## Components

### `App\Http\Filters\FilterOption` (new)

Placement: `app/Http/Filters/` — HTTP-layer presentation, near the controllers that
consume it. A subfolder of the existing `app/Http` base folder (no new base folder).

Immutable value object, private constructor, named constructors:

```php
final class FilterOption
{
    private function __construct(
        private string $key,
        private string $label,
        private string $type,
        private ?array $options,
        private ?bool $multiple,
    ) {}

    /**
     * @param  list<array{value: string, label: string}>  $options
     */
    public static function select(string $key, string $label, array $options, bool $multiple = true): self
    {
        return new self($key, $label, 'select', $options, $multiple);
    }

    public static function dateRange(string $key, string $label): self
    {
        return new self($key, $label, 'daterange', null, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $descriptor = ['key' => $this->key, 'label' => $this->label, 'type' => $this->type];

        if ($this->type === 'select') {
            $descriptor['multiple'] = $this->multiple;
            $descriptor['options'] = $this->options;
        }

        return $descriptor;
    }

    /**
     * @param  list<self>  $options
     * @return list<array<string, mixed>>
     */
    public static function toArrayList(array $options): array
    {
        return array_map(fn (self $option): array => $option->toArray(), $options);
    }
}
```

### The six controllers (change)

Each `filterOptions()` becomes a `FilterOption::toArrayList([...])` of named
constructors. Example (`CourseController`):

```php
private function filterOptions(): array
{
    return FilterOption::toArrayList([
        FilterOption::select('status', 'Status', CourseStatus::options()),
        FilterOption::select('level', 'Level', CourseLevel::options()),
    ]);
}
```

Per-controller mapping:

| Controller | FilterOption calls |
|---|---|
| `CourseController` | `select('status','Status',CourseStatus::options())`, `select('level','Level',CourseLevel::options())` |
| `CourseCatalogController` | `select('level','Level',CourseLevel::options())` |
| `EnrollmentController` | `select('status','Status',EnrollmentStatus::options())` |
| `Course\RosterController` | `select('status','Status',EnrollmentStatus::options())` |
| `UserManagementController` | `select('role','Role',UserRole::options())`, `select('status','Status',[Active,Invited])`, `dateRange('created_at','Created')` |
| `NotificationController` | `select('type','Type',NotificationType::options())`, `select('read','Status',[unread,read], multiple: false)`, `dateRange('created_at','Received')` |

Inline option arrays (user Active/Invited, notification unread/read) stay inline,
passed to `FilterOption::select`. No new enums.

## Behavior Change (intentional, minimal)

The Notification `read` descriptor currently omits the `multiple` key; it will now
emit `multiple => false`. `FilterBar.vue` ignores the `multiple` flag (renders selects
as checkbox dropdowns regardless), and no test asserts the key's absence, so this is a
harmless normalization. Every other descriptor's output is byte-identical.

## Out of Scope

- `filterableFields()` on models and the query `Filter` strategy classes — unchanged.
- `FilterBar.vue` and all filter/query behavior — unchanged.
- Filter-input validation layer / `FilterRequest`.
- New enums for the inline option lists.

## Testing

- **New:** `tests/Unit/Http/Filters/FilterOptionTest.php` — asserts:
  - `select($k,$l,$opts)` → `['key'=>$k,'label'=>$l,'type'=>'select','multiple'=>true,'options'=>$opts]`
  - `select(..., multiple: false)` → `multiple => false`
  - `dateRange($k,$l)` → `['key'=>$k,'label'=>$l,'type'=>'daterange']` (no `multiple`/`options` keys)
  - `toArrayList([...])` maps each `->toArray()` in order
- **Regression net:** existing filter feature tests already assert `filterOptions`
  keys/shape and must still pass unchanged: `CourseFilterTest`, `EnrollmentFilterTest`,
  `CatalogFilterTest`, `UserFilterTest`, `NotificationFilterTest`.
- Full suite green + pint clean before merge.

## Success Criteria

- No descriptor array literal (`'type' => 'select'`, `'type' => 'daterange'`) remains
  in any controller; all go through `FilterOption`.
- All existing filter feature tests pass unchanged (except any that would assert the
  `read` descriptor lacks `multiple` — none do).
- Full suite green, pint clean.
