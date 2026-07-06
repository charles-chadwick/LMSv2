# FilterOptions Centralization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the duplicated `filterOptions()` descriptor boilerplate in six list controllers with a single `App\Http\Filters\FilterOption` value object.

**Architecture:** A `FilterOption` immutable value object with named constructors (`select`, `dateRange`) and `toArray()`/`toArrayList()` produces the exact descriptor arrays `FilterBar.vue` consumes. Each controller's `filterOptions()` becomes a `FilterOption::toArrayList([...])` list. No change to query logic (`filterableFields()`, `Filter` strategies) or `FilterBar.vue`.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3, Pest v4. Tests run on MariaDB (`lms_v2_testing`, DatabaseTruncation).

## Global Constraints

- Variables `snake_case`, methods `camelCase`, classes `TitleCase`.
- Curly braces on all control structures; explicit return types + param type hints; PHPDoc array shapes.
- Prefer OOP; the value object has a private constructor + named constructors.
- Pure presentation-layer dedup: do NOT touch `filterableFields()`, the `Filter` strategy classes, or `FilterBar.vue`.
- Descriptor output must stay byte-identical to today's, with ONE intentional exception: the Notification `read` descriptor gains `multiple => false` (it currently omits the key).
- `FilterOption` lives at `app/Http/Filters/FilterOption.php` (subfolder of existing `app/Http`; not a new base folder).
- After PHP changes run `vendor/bin/pint --dirty --format agent`.
- Run focused tests with `php artisan test --compact --filter=...`; full suite once before committing.

---

### Task 1: `FilterOption` value object

**Files:**
- Create: `app/Http/Filters/FilterOption.php`
- Test: `tests/Unit/Http/Filters/FilterOptionTest.php`

**Interfaces:**
- Produces:
  - `FilterOption::select(string $key, string $label, array $options, bool $multiple = true): self`
  - `FilterOption::dateRange(string $key, string $label): self`
  - `FilterOption->toArray(): array` — select → `['key','label','type'=>'select','multiple','options']`; daterange → `['key','label','type'=>'daterange']`
  - `FilterOption::toArrayList(list<self> $options): list<array>`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Http/Filters/FilterOptionTest.php`:

```php
<?php

use App\Http\Filters\FilterOption;

it('builds a multi-select descriptor by default', function () {
    $options = [['value' => 'Draft', 'label' => 'Draft']];

    expect(FilterOption::select('status', 'Status', $options)->toArray())->toBe([
        'key' => 'status',
        'label' => 'Status',
        'type' => 'select',
        'multiple' => true,
        'options' => $options,
    ]);
});

it('builds a single-select descriptor when multiple is false', function () {
    $options = [['value' => 'read', 'label' => 'Read']];

    expect(FilterOption::select('read', 'Status', $options, multiple: false)->toArray())->toBe([
        'key' => 'read',
        'label' => 'Status',
        'type' => 'select',
        'multiple' => false,
        'options' => $options,
    ]);
});

it('builds a daterange descriptor without multiple or options keys', function () {
    expect(FilterOption::dateRange('created_at', 'Created')->toArray())->toBe([
        'key' => 'created_at',
        'label' => 'Created',
        'type' => 'daterange',
    ]);
});

it('maps a list of options to their array descriptors in order', function () {
    $list = FilterOption::toArrayList([
        FilterOption::select('status', 'Status', [['value' => 'Draft', 'label' => 'Draft']]),
        FilterOption::dateRange('created_at', 'Created'),
    ]);

    expect($list)->toBe([
        [
            'key' => 'status',
            'label' => 'Status',
            'type' => 'select',
            'multiple' => true,
            'options' => [['value' => 'Draft', 'label' => 'Draft']],
        ],
        [
            'key' => 'created_at',
            'label' => 'Created',
            'type' => 'daterange',
        ],
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FilterOptionTest`
Expected: FAIL with "Class App\Http\Filters\FilterOption not found".

- [ ] **Step 3: Write the implementation**

Create `app/Http/Filters/FilterOption.php`:

```php
<?php

namespace App\Http\Filters;

final class FilterOption
{
    /**
     * @param  list<array{value: string, label: string}>|null  $options
     */
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
        $descriptor = [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
        ];

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

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=FilterOptionTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Filters/FilterOption.php tests/Unit/Http/Filters/FilterOptionTest.php
git commit -m "feat: add FilterOption value object for filter descriptors"
```

---

### Task 2: Migrate all six controllers to `FilterOption`

**Files:**
- Modify: `app/Http/Controllers/CourseController.php`
- Modify: `app/Http/Controllers/CourseCatalogController.php`
- Modify: `app/Http/Controllers/EnrollmentController.php`
- Modify: `app/Http/Controllers/Course/RosterController.php`
- Modify: `app/Http/Controllers/UserManagementController.php`
- Modify: `app/Http/Controllers/NotificationController.php`
- Test (regression, unchanged): `tests/Feature/Courses/CourseFilterTest.php`, `tests/Feature/Enrollments/EnrollmentFilterTest.php`, `tests/Feature/Catalog/CatalogFilterTest.php`, `tests/Feature/Users/UserFilterTest.php`, `tests/Feature/Notifications/NotificationFilterTest.php`

**Interfaces:**
- Consumes: `FilterOption::select`, `FilterOption::dateRange`, `FilterOption::toArrayList` (Task 1).

**Approach:** This is a mechanical, behavior-preserving migration. The existing filter feature tests assert `filterOptions` keys/shape and are the regression net — they must pass unchanged. Each controller keeps its existing enum imports (still used for `::options()`); add `use App\Http\Filters\FilterOption;` to each.

- [ ] **Step 1: Confirm the regression net passes before changes**

Run: `php artisan test --compact --filter=Filter`
Expected: PASS (baseline — the filter feature tests are currently green).

- [ ] **Step 2: Migrate `CourseController`**

Add `use App\Http\Filters\FilterOption;` to the imports. Replace the `filterOptions()` method body with:

```php
    private function filterOptions(): array
    {
        return FilterOption::toArrayList([
            FilterOption::select('status', 'Status', CourseStatus::options()),
            FilterOption::select('level', 'Level', CourseLevel::options()),
        ]);
    }
```

- [ ] **Step 3: Migrate `CourseCatalogController`**

Add `use App\Http\Filters\FilterOption;`. Replace `filterOptions()` with:

```php
    private function filterOptions(): array
    {
        return FilterOption::toArrayList([
            FilterOption::select('level', 'Level', CourseLevel::options()),
        ]);
    }
```

- [ ] **Step 4: Migrate `EnrollmentController`**

Add `use App\Http\Filters\FilterOption;`. Replace `filterOptions()` with:

```php
    private function filterOptions(): array
    {
        return FilterOption::toArrayList([
            FilterOption::select('status', 'Status', EnrollmentStatus::options()),
        ]);
    }
```

- [ ] **Step 5: Migrate `Course\RosterController`**

Add `use App\Http\Filters\FilterOption;`. Replace `filterOptions()` with:

```php
    private function filterOptions(): array
    {
        return FilterOption::toArrayList([
            FilterOption::select('status', 'Status', EnrollmentStatus::options()),
        ]);
    }
```

- [ ] **Step 6: Migrate `UserManagementController`**

Add `use App\Http\Filters\FilterOption;`. Replace `filterOptions()` with:

```php
    private function filterOptions(): array
    {
        return FilterOption::toArrayList([
            FilterOption::select('role', 'Role', UserRole::options()),
            FilterOption::select('status', 'Status', [
                ['value' => 'Active', 'label' => 'Active'],
                ['value' => 'Invited', 'label' => 'Invited'],
            ]),
            FilterOption::dateRange('created_at', 'Created'),
        ]);
    }
```

- [ ] **Step 7: Migrate `NotificationController`**

Add `use App\Http\Filters\FilterOption;`. Replace `filterOptions()` with:

```php
    private function filterOptions(): array
    {
        return FilterOption::toArrayList([
            FilterOption::select('type', 'Type', NotificationType::options()),
            FilterOption::select('read', 'Status', [
                ['value' => 'unread', 'label' => 'Unread'],
                ['value' => 'read', 'label' => 'Read'],
            ], multiple: false),
            FilterOption::dateRange('created_at', 'Received'),
        ]);
    }
```

- [ ] **Step 8: Run the filter regression suite**

Run: `php artisan test --compact --filter=Filter`
Expected: PASS (unchanged — `CourseFilterTest`, `EnrollmentFilterTest`, `CatalogFilterTest`, `UserFilterTest`, `NotificationFilterTest` all green; the `read` descriptor gaining `multiple => false` is not asserted by any test).

- [ ] **Step 9: Verify no descriptor literals remain**

Run: `grep -rn "'type' => 'select'\|'type' => 'daterange'" app/Http/Controllers`
Expected: no output (every descriptor now goes through `FilterOption`).

- [ ] **Step 10: Run the full suite**

Run: `php artisan test --compact`
Expected: PASS (no regressions).

- [ ] **Step 11: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/CourseController.php app/Http/Controllers/CourseCatalogController.php app/Http/Controllers/EnrollmentController.php app/Http/Controllers/Course/RosterController.php app/Http/Controllers/UserManagementController.php app/Http/Controllers/NotificationController.php
git commit -m "refactor: build filter descriptors via FilterOption across list controllers"
```

---

## Self-Review

**Spec coverage:**
- `App\Http\Filters\FilterOption` value object with `select`/`dateRange`/`toArray`/`toArrayList` → Task 1. ✓
- All six controllers migrated (Course, Catalog, Enrollment, Roster, UserManagement, Notification) → Task 2, Steps 2-7. ✓
- Inline option lists (user Active/Invited, notification unread/read) stay inline → Task 2 Steps 6-7. ✓
- `read` normalization to `multiple => false` → Task 2 Step 7 (`multiple: false`), asserted in Task 1 Step 1 test 2. ✓
- No new enums, no validation layer, `filterableFields()`/`Filter`/`FilterBar.vue` untouched → not in any task (out of scope). ✓
- Unit test for FilterOption → Task 1. Regression net (existing filter feature tests) → Task 2 Steps 1, 8. ✓
- No-descriptor-literals success criterion → Task 2 Step 9. ✓

**Placeholder scan:** No TBD/TODO; every code step shows full code. ✓

**Type consistency:** `FilterOption::select`/`dateRange`/`toArrayList` signatures used in Task 2 match Task 1's definitions exactly; `toArray()` output shape used in the Task 1 tests matches the controller descriptors the regression tests assert. ✓
