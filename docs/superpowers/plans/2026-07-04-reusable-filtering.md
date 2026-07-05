# Reusable Filtering Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a reusable, relationship-aware filtering mechanism (a `Filterable` trait + filter-strategy objects + a generic `FilterBar` Vue component) and apply it to the Users list to filter by role, status, and created-date.

**Architecture:** Parallel to the existing `Searchable` trait. A `Filter` interface with four strategy implementations (`ExactFilter`, `RelationFilter`, `RangeFilter`, `CallbackFilter`); a `Filterable` trait exposing `scopeWithFilters(?array $filters)` that applies each model's declared `filterableFields()` map. The controller passes current `filters` values + a declarative `filterOptions` list; a single-owner `FilterBar.vue` renders search + filter controls and drives one combined `router.get`.

**Tech Stack:** Laravel 13, Eloquent query scopes, Spatie Permission (roles relationship), Inertia v3 + Vue 3, reka-ui `DropdownMenuCheckboxItem`, Pest 4, Tailwind v4.

## Global Constraints

- Naming: variables `snake_case`, methods/functions `camelCase`, classes `TitleCase`.
- PHP: curly braces on ALL control structures (no ternary-as-statement); explicit return types + param type hints; constructor property promotion; PHPDoc over inline comments; array-shape PHPDoc.
- Reuse the existing pattern: filter classes live under `app/Models/Concerns/Filters/`, trait at `app/Models/Concerns/Filterable.php` — no new base folder.
- `scopeWithFilters` consults ONLY the model's declared `filterableFields()` keys (unknown request keys ignored); empty values (`null`, `''`, `[]`) are skipped.
- No new dependencies; the multi-select uses the existing `ui/dropdown-menu` `DropdownMenuCheckboxItem`.
- Vue components have a single root element; `SearchInput.vue` must remain unchanged (other pages use it).
- Tests: MariaDB + `DatabaseTruncation` (configured in `tests/Pest.php`). Every test file touching roles MUST `beforeEach(fn () => $this->seed(RolePermissionSeeder::class))`.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes, before each commit.
- Run tests with `php artisan test --compact --filter=...`; frontend changes must `npm run build` cleanly.

---

### Task 1: `Filter` interface + `ExactFilter` + `RelationFilter`

**Files:**
- Create: `app/Models/Concerns/Filters/Filter.php`
- Create: `app/Models/Concerns/Filters/ExactFilter.php`
- Create: `app/Models/Concerns/Filters/RelationFilter.php`
- Test: `tests/Feature/Filtering/FilterStrategiesTest.php`

**Interfaces:**
- Produces:
  - `interface Filter { public function apply(Builder $query, mixed $value): void; }`
  - `ExactFilter(string $column)` — scalar → `where`, array → `whereIn`.
  - `RelationFilter(string $relation, string $column)` — `whereHas($relation, whereIn($column, (array) $value))`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filtering/FilterStrategiesTest.php`:

```php
<?php

use App\Models\Concerns\Filters\ExactFilter;
use App\Models\Concerns\Filters\RelationFilter;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('filters an exact column by a scalar value', function () {
    $match = User::factory()->create(['email' => 'match@example.com']);
    User::factory()->create(['email' => 'other@example.com']);

    $query = User::query();
    (new ExactFilter('email'))->apply($query, 'match@example.com');

    expect($query->pluck('id')->all())->toBe([$match->id]);
});

it('filters an exact column by an array of values with whereIn', function () {
    $a = User::factory()->create(['email' => 'a@example.com']);
    $b = User::factory()->create(['email' => 'b@example.com']);
    User::factory()->create(['email' => 'c@example.com']);

    $query = User::query();
    (new ExactFilter('email'))->apply($query, ['a@example.com', 'b@example.com']);

    expect($query->pluck('id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

it('filters by a single related value', function () {
    $student = User::factory()->student()->create();
    User::factory()->instructor()->create();

    $query = User::query();
    (new RelationFilter('roles', 'name'))->apply($query, 'Student');

    expect($query->pluck('id')->all())->toBe([$student->id]);
});

it('filters by multiple related values with whereIn', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    User::factory()->admin()->create();

    $query = User::query();
    (new RelationFilter('roles', 'name'))->apply($query, ['Student', 'Instructor']);

    expect($query->pluck('id')->sort()->values()->all())
        ->toBe(collect([$student->id, $instructor->id])->sort()->values()->all());
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FilterStrategiesTest`
Expected: FAIL (`App\Models\Concerns\Filters\ExactFilter` not found).

- [ ] **Step 3: Create the interface**

`app/Models/Concerns/Filters/Filter.php`:

```php
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
```

- [ ] **Step 4: Create `ExactFilter`**

`app/Models/Concerns/Filters/ExactFilter.php`:

```php
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
```

- [ ] **Step 5: Create `RelationFilter`**

`app/Models/Concerns/Filters/RelationFilter.php`:

```php
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
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=FilterStrategiesTest`
Expected: PASS (4 passing).

- [ ] **Step 7: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Concerns/Filters/Filter.php app/Models/Concerns/Filters/ExactFilter.php app/Models/Concerns/Filters/RelationFilter.php tests/Feature/Filtering/FilterStrategiesTest.php
git commit -m "feat: add Filter interface, ExactFilter, RelationFilter"
```

---

### Task 2: `RangeFilter` + `CallbackFilter`

**Files:**
- Create: `app/Models/Concerns/Filters/RangeFilter.php`
- Create: `app/Models/Concerns/Filters/CallbackFilter.php`
- Test: `tests/Feature/Filtering/FilterStrategiesTest.php` (append)

**Interfaces:**
- Consumes: `Filter` interface (Task 1).
- Produces:
  - `RangeFilter(string $column, bool $asDate = false)` — value `array{from?, to?}`; applies `>=`/`<=` (via `whereDate` when `$asDate`), skipping empty bounds; non-array value is a no-op.
  - `CallbackFilter(Closure $callback)` — `apply()` calls `($this->callback)($query, $value)`.

- [ ] **Step 1: Write the failing test (append to the existing file)**

Append these `it(...)` blocks to `tests/Feature/Filtering/FilterStrategiesTest.php` (add the two `use` imports at the top with the others):

```php
use App\Models\Concerns\Filters\CallbackFilter;
use App\Models\Concerns\Filters\RangeFilter;
```

```php
it('filters a date column by an inclusive range', function () {
    $inside = User::factory()->create(['created_at' => '2026-03-15 14:00:00']);
    User::factory()->create(['created_at' => '2026-03-10 09:00:00']);
    User::factory()->create(['created_at' => '2026-03-20 09:00:00']);

    $query = User::query();
    (new RangeFilter('created_at', asDate: true))->apply($query, ['from' => '2026-03-15', 'to' => '2026-03-15']);

    expect($query->pluck('id')->all())->toBe([$inside->id]);
});

it('applies only the provided range bound', function () {
    $recent = User::factory()->create(['created_at' => '2026-03-20 09:00:00']);
    User::factory()->create(['created_at' => '2026-03-01 09:00:00']);

    $query = User::query();
    (new RangeFilter('created_at', asDate: true))->apply($query, ['from' => '2026-03-15']);

    expect($query->pluck('id')->all())->toBe([$recent->id]);
});

it('is a no-op when the range value is not an array', function () {
    User::factory()->count(2)->create();

    $query = User::query();
    (new RangeFilter('created_at'))->apply($query, 'not-an-array');

    expect($query->count())->toBe(2);
});

it('runs the given callback with the query and value', function () {
    $match = User::factory()->create(['email' => 'callback@example.com']);
    User::factory()->create(['email' => 'nope@example.com']);

    $query = User::query();
    (new CallbackFilter(fn ($query, $value) => $query->where('email', $value)))->apply($query, 'callback@example.com');

    expect($query->pluck('id')->all())->toBe([$match->id]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FilterStrategiesTest`
Expected: FAIL (`RangeFilter` / `CallbackFilter` not found).

- [ ] **Step 3: Create `RangeFilter`**

`app/Models/Concerns/Filters/RangeFilter.php`:

```php
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
```

- [ ] **Step 4: Create `CallbackFilter`**

`app/Models/Concerns/Filters/CallbackFilter.php`:

```php
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
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=FilterStrategiesTest`
Expected: PASS (8 passing total).

- [ ] **Step 6: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Concerns/Filters/RangeFilter.php app/Models/Concerns/Filters/CallbackFilter.php tests/Feature/Filtering/FilterStrategiesTest.php
git commit -m "feat: add RangeFilter and CallbackFilter"
```

---

### Task 3: `Filterable` trait + wire `User::filterableFields()`

**Files:**
- Create: `app/Models/Concerns/Filterable.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Filtering/FilterableTraitTest.php`

**Interfaces:**
- Consumes: all four filter strategies (Tasks 1-2); `User` already uses `Searchable`.
- Produces:
  - `Filterable` trait with `abstract protected function filterableFields(): array` and `scopeWithFilters(Builder $query, ?array $filters): Builder`.
  - `User::filterableFields()` → `['role' => RelationFilter, 'status' => CallbackFilter, 'created_at' => RangeFilter]`.
  - `User::query()->withFilters([...])` usable by the controller.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filtering/FilterableTraitTest.php`:

```php
<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('filters users by role', function () {
    $student = User::factory()->student()->create();
    User::factory()->instructor()->create();

    expect(User::query()->withFilters(['role' => ['Student']])->pluck('id')->all())->toBe([$student->id]);
});

it('filters users by multiple roles', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    User::factory()->admin()->create();

    $ids = User::query()->withFilters(['role' => ['Student', 'Instructor']])->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$student->id, $instructor->id])->sort()->values()->all());
});

it('filters users by derived status', function () {
    $active = User::factory()->create(['email_verified_at' => now()]);
    $invited = User::factory()->create(['email_verified_at' => null]);

    expect(User::query()->withFilters(['status' => ['Active']])->pluck('id')->all())->toBe([$active->id]);
    expect(User::query()->withFilters(['status' => ['Invited']])->pluck('id')->all())->toBe([$invited->id]);
    expect(User::query()->withFilters(['status' => ['Active', 'Invited']])->count())->toBe(2);
});

it('filters users by created_at range', function () {
    $inside = User::factory()->create(['created_at' => '2026-03-15 10:00:00']);
    User::factory()->create(['created_at' => '2026-01-01 10:00:00']);

    $ids = User::query()->withFilters(['created_at' => ['from' => '2026-03-01', 'to' => '2026-03-31']])->pluck('id')->all();

    expect($ids)->toBe([$inside->id]);
});

it('ignores unknown filter keys and empty values', function () {
    User::factory()->count(3)->create();

    expect(User::query()->withFilters(['bogus' => 'x', 'role' => [], 'status' => ''])->count())->toBe(3);
    expect(User::query()->withFilters(null)->count())->toBe(3);
});

it('combines filters with search', function () {
    $zoe = User::factory()->student()->create(['first_name' => 'Zoe']);
    User::factory()->student()->create(['first_name' => 'Amy']);
    User::factory()->instructor()->create(['first_name' => 'Zane']);

    $ids = User::query()->withSearch('Z')->withFilters(['role' => ['Student']])->pluck('id')->all();

    expect($ids)->toBe([$zoe->id]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FilterableTraitTest`
Expected: FAIL (`withFilters` scope undefined).

- [ ] **Step 3: Create the `Filterable` trait**

`app/Models/Concerns/Filterable.php`:

```php
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
```

- [ ] **Step 4: Wire `User`**

In `app/Models/User.php`, add these imports (with the existing `App\Models\Concerns\Searchable` import and the Eloquent imports):

```php
use App\Models\Concerns\Filterable;
use App\Models\Concerns\Filters\CallbackFilter;
use App\Models\Concerns\Filters\Filter;
use App\Models\Concerns\Filters\RangeFilter;
use App\Models\Concerns\Filters\RelationFilter;
```

Add `Filterable` to the trait `use` list (keep it consistent with the existing alphabetical grouping):

```php
    use CausesActivity, Filterable, HasFactory, HasRoles, InteractsWithMedia, LogsActivity, Notifiable, Searchable, SoftDeletes;
```

Add this method next to `searchableFields()`:

```php
    /**
     * Filterable fields for the management user list.
     *
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

Ensure `use Illuminate\Database\Eloquent\Builder;` is present (add it if not already imported).

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=FilterableTraitTest`
Expected: PASS (6 passing).

- [ ] **Step 6: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Concerns/Filterable.php app/Models/User.php tests/Feature/Filtering/FilterableTraitTest.php
git commit -m "feat: add Filterable trait and wire User filter fields"
```

---

### Task 4: Controller filtering + `filterOptions` prop

**Files:**
- Modify: `app/Http/Controllers/UserManagementController.php`
- Test: `tests/Feature/Users/UserFilterTest.php`

**Interfaces:**
- Consumes: `User::withFilters` (Task 3); existing `UserManagementResource`.
- Produces:
  - `index` applies `->withFilters($request->input('filters'))` and returns `filters` (search + applied filter values) and `filterOptions` props.
  - Private `filterOptions(): array` returning the declarative option list.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Users/UserFilterTest.php`:

```php
<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('filters the user list by role', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();
    User::factory()->instructor()->create();

    $this->actingAs($admin)->get(route('users.index', ['filters' => ['role' => ['Student']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)
            ->where('users.data.0.id', $student->id));
});

it('filters the user list by derived status', function () {
    $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
    $invited = User::factory()->student()->create(['email_verified_at' => null]);
    User::factory()->student()->create(['email_verified_at' => now()]);

    $this->actingAs($admin)->get(route('users.index', ['filters' => ['status' => ['Invited']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)
            ->where('users.data.0.id', $invited->id));
});

it('exposes filter options with role and status choices', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('filterOptions', 3)
            ->where('filterOptions.0.key', 'role')
            ->where('filterOptions.1.key', 'status')
            ->where('filterOptions.2.key', 'created_at'));
});

it('combines a role filter with search', function () {
    $admin = User::factory()->admin()->create();
    $zoe = User::factory()->student()->create(['first_name' => 'Zoe']);
    User::factory()->student()->create(['first_name' => 'Amy']);
    User::factory()->instructor()->create(['first_name' => 'Zane']);

    $this->actingAs($admin)->get(route('users.index', ['search' => 'Z', 'filters' => ['role' => ['Student']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)
            ->where('users.data.0.id', $zoe->id));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=UserFilterTest`
Expected: FAIL (`filterOptions` prop missing / filters not applied).

- [ ] **Step 3: Update `index` and add `filterOptions`**

In `app/Http/Controllers/UserManagementController.php`, update the `index` method's query and render. Add `->withFilters($request->input('filters'))` after the `withSearch` line, and change the `Inertia::render` call:

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

Add this private method to the controller (after `roleOptions`):

```php
    /**
     * Declarative filter controls for the user list.
     *
     * @return list<array<string, mixed>>
     */
    private function filterOptions(): array
    {
        return [
            [
                'key' => 'role',
                'label' => 'Role',
                'type' => 'select',
                'multiple' => true,
                'options' => array_map(
                    fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->value],
                    UserRole::cases(),
                ),
            ],
            [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'multiple' => true,
                'options' => [
                    ['value' => 'Active', 'label' => 'Active'],
                    ['value' => 'Invited', 'label' => 'Invited'],
                ],
            ],
            [
                'key' => 'created_at',
                'label' => 'Created',
                'type' => 'daterange',
            ],
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=UserFilterTest`
Expected: PASS (4 passing).

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/UserManagementController.php tests/Feature/Users/UserFilterTest.php
git commit -m "feat: apply filters and expose filter options on user index"
```

---

### Task 5: `FilterBar.vue` + wire into Users index

**Files:**
- Create: `resources/js/Components/FilterBar.vue`
- Modify: `resources/js/Pages/Users/Index.vue`
- Verify: `npm run build`

**Interfaces:**
- Consumes: `filters` + `filterOptions` props (Task 4); `ui/dropdown-menu`, `ui/button`, `ui/badge`, `ui/input`, `SearchInput` debounce pattern.
- Produces: `FilterBar` — the single navigation owner for the list; issues one `router.get(pathname, { search, filters }, { preserveState, preserveScroll, replace })`.

- [ ] **Step 1: Create `FilterBar.vue`**

`resources/js/Components/FilterBar.vue`:

```vue
<script setup>
import { reactive, ref, computed, watch, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuCheckboxItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Search, ChevronDown, X } from 'lucide-vue-next';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
    filterOptions: {
        type: Array,
        default: () => [],
    },
});

const search = ref(props.filters.search ?? '');

const values = reactive({});
props.filterOptions.forEach((option) => {
    if (option.type === 'daterange') {
        const current = props.filters[option.key] ?? {};
        values[option.key] = { from: current.from ?? '', to: current.to ?? '' };
    } else {
        values[option.key] = [...(props.filters[option.key] ?? [])];
    }
});

const buildQuery = () => {
    const query = {};
    const term = search.value.trim();
    if (term !== '') {
        query.search = term;
    }

    const filters = {};
    props.filterOptions.forEach((option) => {
        if (option.type === 'daterange') {
            const range = {};
            if (values[option.key].from) {
                range.from = values[option.key].from;
            }
            if (values[option.key].to) {
                range.to = values[option.key].to;
            }
            if (Object.keys(range).length > 0) {
                filters[option.key] = range;
            }
        } else if (values[option.key].length > 0) {
            filters[option.key] = values[option.key];
        }
    });

    if (Object.keys(filters).length > 0) {
        query.filters = filters;
    }

    return query;
};

const apply = () => {
    router.get(window.location.pathname, buildQuery(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

let debounce_timer = null;
watch(search, () => {
    if (debounce_timer) {
        clearTimeout(debounce_timer);
    }
    debounce_timer = setTimeout(apply, 300);
});
onBeforeUnmount(() => {
    if (debounce_timer) {
        clearTimeout(debounce_timer);
    }
});

const toggleValue = (key, value) => {
    const current = values[key];
    const index = current.indexOf(value);
    if (index === -1) {
        current.push(value);
    } else {
        current.splice(index, 1);
    }
    apply();
};

const isChecked = (key, value) => values[key].includes(value);

const activeCount = (key) => values[key].length;

const hasActiveFilters = computed(() =>
    props.filterOptions.some((option) =>
        option.type === 'daterange'
            ? Boolean(values[option.key].from || values[option.key].to)
            : values[option.key].length > 0,
    ),
);

const clearFilters = () => {
    props.filterOptions.forEach((option) => {
        if (option.type === 'daterange') {
            values[option.key].from = '';
            values[option.key].to = '';
        } else {
            values[option.key] = [];
        }
    });
    apply();
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative w-full max-w-xs">
            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="search" type="search" placeholder="Search…" class="pl-9" />
        </div>

        <template v-for="option in filterOptions" :key="option.key">
            <DropdownMenu v-if="option.type === 'select'">
                <DropdownMenuTrigger as-child>
                    <Button variant="outline" class="gap-1.5">
                        {{ option.label }}
                        <Badge v-if="activeCount(option.key) > 0" variant="secondary" class="ml-0.5">
                            {{ activeCount(option.key) }}
                        </Badge>
                        <ChevronDown class="size-4 opacity-60" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="w-48">
                    <DropdownMenuLabel>{{ option.label }}</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuCheckboxItem
                        v-for="choice in option.options"
                        :key="choice.value"
                        :model-value="isChecked(option.key, choice.value)"
                        @update:model-value="toggleValue(option.key, choice.value)"
                        @select="(event) => event.preventDefault()"
                    >
                        {{ choice.label }}
                    </DropdownMenuCheckboxItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <div v-else-if="option.type === 'daterange'" class="flex items-center gap-1.5">
                <span class="text-sm text-muted-foreground">{{ option.label }}</span>
                <Input v-model="values[option.key].from" type="date" class="w-auto" @change="apply" />
                <span class="text-muted-foreground">–</span>
                <Input v-model="values[option.key].to" type="date" class="w-auto" @change="apply" />
            </div>
        </template>

        <Button v-if="hasActiveFilters" variant="ghost" size="sm" class="text-muted-foreground" @click="clearFilters">
            <X class="size-4" />
            Clear filters
        </Button>
    </div>
</template>
```

- [ ] **Step 2: Wire it into the Users index**

In `resources/js/Pages/Users/Index.vue`:

Replace the `SearchInput` import line with a `FilterBar` import:

```js
import FilterBar from '@/Components/FilterBar.vue';
```

Add a `filterOptions` prop to `defineProps` (after the `filters` prop):

```js
    filterOptions: {
        type: Array,
        default: () => [],
    },
```

Replace the search block in the template:

```vue
        <div class="mb-4">
            <SearchInput :initial="filters.search ?? ''" placeholder="Search users…" />
        </div>
```

with:

```vue
        <div class="mb-4">
            <FilterBar :filters="filters" :filter-options="filterOptions" />
        </div>
```

- [ ] **Step 3: Build to verify the frontend compiles**

Run: `npm run build`
Expected: builds without errors.

- [ ] **Step 4: Run the user index/filter tests to confirm nothing regressed**

Run: `php artisan test --compact --filter=UserFilterTest && php artisan test --compact --filter=UserIndexTest`
Expected: PASS (both suites green — the page still renders with the new prop).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/FilterBar.vue resources/js/Pages/Users/Index.vue
git commit -m "feat: add reusable FilterBar and wire into users list"
```

---

### Task 6: Full-suite regression

**Files:** run the whole suite (no new files unless a regression surfaces).

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test --compact`
Expected: PASS (all green — the new `withFilters` scope, controller props, and Users page changes did not break existing Users/Courses/Profile tests).

- [ ] **Step 2: If any test regressed, fix inline**

Likely suspects if red:
- Users index tests → confirm the `filterOptions` prop and `filters` shape didn't change the assertions the earlier Users tests rely on (they assert `users.total` / `users.data`, which are unchanged).
- Pint/build → re-run `vendor/bin/pint --dirty --format agent` and `npm run build`.

Re-run the specific failing file with `--filter` until green.

- [ ] **Step 3: Final commit (only if fixes were needed)**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "test: fix regressions from reusable filtering"
```

---

## Self-Review

**1. Spec coverage:**
- `Filter` interface + `ExactFilter`/`RelationFilter`/`RangeFilter`/`CallbackFilter` → Tasks 1-2, each with dedicated tests (ExactFilter exercised directly so it is not dead code). ✓
- `Filterable` trait (`scopeWithFilters`, declared-fields-only, empty-skip) → Task 3. ✓
- `User` field map (role/status/created_at, multi-value + derived) → Task 3. ✓
- Controller `withFilters` + `filters`/`filterOptions` props + `filterOptions()` → Task 4. ✓
- `FilterBar.vue` (search + select/daterange, single-owner navigation, clear filters) + Users page swap, `SearchInput` untouched → Task 5. ✓
- Testing matrix (role single/multi, status derived multi, range inclusive, unknown/empty ignored, combine with search, endpoint + filterOptions) → Tasks 1-4. ✓
- Out of scope (saved filters, other pages, operator UI, new deps) → not implemented. ✓

**2. Placeholder scan:** No TBD/TODO; every code step has complete code + exact commands. ✓

**3. Type consistency:**
- `Filter::apply(Builder, mixed): void` — identical signature across all four strategies and their call sites. ✓
- `scopeWithFilters(Builder, ?array): Builder` — defined Task 3, consumed Task 4 (`->withFilters($request->input('filters'))`). ✓
- `filterOptions` item shape (`key`/`label`/`type`/`multiple`/`options`) — produced Task 4, consumed by `FilterBar` Task 5 (`option.type === 'select' | 'daterange'`, `option.options`, `choice.value`/`choice.label`). ✓
- Filter param keys (`role`/`status`/`created_at`) — identical between `User::filterableFields()` (Task 3), `filterOptions()` (Task 4), and the query the FilterBar builds (Task 5). ✓
- `created_at` value shape `{from, to}` — RangeFilter (Task 2), trait wiring (Task 3), controller round-trip (Task 4), FilterBar daterange (Task 5). ✓
