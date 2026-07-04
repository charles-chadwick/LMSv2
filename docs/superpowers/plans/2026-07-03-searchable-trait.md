# Reusable Searchable Trait + Inertia List Search — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a reusable `Searchable` Eloquent trait (LIKE + driver-aware full-text, including relationships) and wire it into the Courses management list as a debounced, Inertia-driven URL filter.

**Architecture:** A `Searchable` trait exposes a `scopeWithSearch($term)` query scope. Models declare LIKE fields via an abstract `searchableFields()` and opt specific fields into full-text via `fullTextSearchableFields()`. Full-text uses `MATCH…AGAINST` on MariaDB/MySQL and falls back to `LIKE` on SQLite (the test DB). A reusable `SearchInput.vue` performs a debounced Inertia `GET` against the current URL; `CourseController::index` applies the scope and echoes the term back as `filters.search`.

**Tech Stack:** Laravel 13, PHP 8.4, MariaDB (prod) / SQLite `:memory:` (tests), Inertia v3 + Vue 3, Pest 4, Tailwind v4.

## Global Constraints

- Variables `snake_case`; methods/functions `camelCase`; classes `TitleCase` (global standard).
- PHP: curly braces on all control structures; explicit return types + param type hints; constructor property promotion; PHPDoc over inline comments; array-shape PHPDoc.
- Prefer OOP; follow existing sibling-file conventions.
- New model concern traits live in `app/Models/Concerns` (mirrors `App\Actions\Concerns`).
- Model trait query scopes are named `scopeWith*` (usage `->withSearch(...)`).
- Run `vendor/bin/pint --dirty --format agent` after PHP changes.
- Tests run on SQLite `:memory:`; full-text falls back to LIKE there — never assert `whereFullText` executes on SQLite.
- Run affected tests with `php artisan test --compact --filter=...`.

---

### Task 1: `Searchable` trait + Course adoption

**Files:**
- Create: `app/Models/Concerns/Searchable.php`
- Modify: `app/Models/Course.php` (add `use Searchable;` + field-declaration methods)
- Test: `tests/Feature/Courses/CourseSearchTest.php`

**Interfaces:**
- Produces: `scopeWithSearch(Builder $query, ?string $term): Builder` (usage `Model::query()->withSearch($term)`); `abstract protected function searchableFields(): array`; `protected function fullTextSearchableFields(): array` (defaults to `[]`).
- Consumes: `Course` factory (`published()`, `instructor()` on `User`), `User` factory with `first_name`/`last_name` (the `name` column is a generated concat).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Courses/CourseSearchTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\User;

use function Pest\Laravel\assertDatabaseCount;

it('matches courses on the title column', function () {
    $match = Course::factory()->create(['title' => 'Advanced Welding Techniques']);
    $other = Course::factory()->create(['title' => 'Intro to Pottery']);

    $ids = Course::query()->withSearch('welding')->pluck('id');

    expect($ids)->toContain($match->id)->not->toContain($other->id);
});

it('matches courses on the slug column', function () {
    $match = Course::factory()->create(['slug' => 'unique-welding-slug-1']);
    $other = Course::factory()->create(['slug' => 'pottery-basics-2']);

    $ids = Course::query()->withSearch('unique-welding-slug')->pluck('id');

    expect($ids)->toContain($match->id)->not->toContain($other->id);
});

it('matches courses by the instructor name relationship', function () {
    $instructor = User::factory()->instructor()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $match = Course::factory()->for($instructor, 'instructor')->create(['title' => 'Some Course']);
    $other = Course::factory()->create(['title' => 'Another Course']);

    $ids = Course::query()->withSearch('Lovelace')->pluck('id');

    expect($ids)->toContain($match->id)->not->toContain($other->id);
});

it('returns all rows for an empty or whitespace term', function () {
    Course::factory()->count(3)->create();

    expect(Course::query()->withSearch('')->count())->toBe(3)
        ->and(Course::query()->withSearch('   ')->count())->toBe(3)
        ->and(Course::query()->withSearch(null)->count())->toBe(3);
});

it('ands the search with an existing constraint', function () {
    $mine = User::factory()->instructor()->create();
    $theirs = User::factory()->instructor()->create();
    $wanted = Course::factory()->for($mine, 'instructor')->create(['title' => 'Shared Keyword Course']);
    Course::factory()->for($theirs, 'instructor')->create(['title' => 'Shared Keyword Course Two']);

    $ids = Course::query()
        ->where('instructor_id', $mine->id)
        ->withSearch('Shared Keyword')
        ->pluck('id');

    expect($ids)->toContain($wanted->id)->toHaveCount(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CourseSearchTest`
Expected: FAIL — `Call to undefined method …withSearch()`.

- [ ] **Step 3: Create the trait**

Create `app/Models/Concerns/Searchable.php`:

```php
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
```

- [ ] **Step 4: Adopt the trait in Course**

In `app/Models/Course.php`, add the import below the other model imports:

```php
use App\Models\Concerns\Searchable;
```

Add `Searchable` to the `use` trait list in the class body:

```php
    use HasFactory, InteractsWithMedia, LogsActivity, Searchable, SoftDeletes;
```

Add the two declaration methods (place them just after the `casts()` method):

```php
    /**
     * @return list<string>
     */
    protected function searchableFields(): array
    {
        return ['title', 'slug'];
    }

    /**
     * @return list<string>
     */
    protected function fullTextSearchableFields(): array
    {
        return ['instructor.name'];
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=CourseSearchTest`
Expected: PASS (5 passing). Full-text `instructor.name` resolves via the LIKE fallback on SQLite.

- [ ] **Step 6: Lint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: files clean / auto-fixed.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Concerns/Searchable.php app/Models/Course.php tests/Feature/Courses/CourseSearchTest.php
git commit -m "Add reusable Searchable trait; adopt in Course"
```

---

### Task 2: Full-text index migration for `users.name`

**Files:**
- Create: `database/migrations/2026_07_03_170000_add_fulltext_index_to_users_name.php`
- Test: `tests/Feature/Courses/CourseSearchTest.php` (append one assertion)

**Interfaces:**
- Consumes: existing `users.name` VIRTUAL generated column (`concat_ws(' ', first_name, last_name)`) from `2026_07_03_161716_make_users_name_a_generated_column.php`.
- Produces: on MariaDB/MySQL, `name` becomes a STORED generated column carrying a `FULLTEXT` index; on SQLite the migration is a no-op (column stays virtual).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Courses/CourseSearchTest.php`:

```php
it('keeps name as the concatenation of first and last name after migrations', function () {
    $user = User::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);

    expect($user->fresh()->name)->toBe('Grace Hopper');
});
```

- [ ] **Step 2: Run test to verify it fails**

The generated migration file does not exist yet, so create it as an empty stub first to confirm the test currently passes on the old schema, then evolve it. Skip to Step 3 — this assertion guards that the new migration does not break the generated column. If it already passes, that is expected; the migration must keep it green.

Run: `php artisan test --compact --filter="keeps name as the concatenation"`
Expected: PASS on current schema (guard baseline).

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_03_170000_add_fulltext_index_to_users_name.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert `name` to a STORED generated column and index it for full text.
     * SQLite (test DB) has no FULLTEXT support and keeps the virtual column,
     * so the Searchable trait uses its LIKE fallback there.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('name');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')
                ->storedAs("concat_ws(' ', first_name, last_name)")
                ->after('last_name');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->fullText('name');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropFullText(['name']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('name');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')
                ->virtualAs("concat_ws(' ', first_name, last_name)")
                ->after('last_name');
        });
    }
};
```

- [ ] **Step 4: Run the guard test against a fresh migration**

Run: `php artisan test --compact --filter="keeps name as the concatenation"`
Expected: PASS — on SQLite the migration no-ops, so `name` stays a working generated column.

- [ ] **Step 5: Verify migration is syntactically valid / listed**

Run: `php artisan migrate:status`
Expected: the new migration appears in the list (Ran on the test DB during the test run; pending on other connections). No errors.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_03_170000_add_fulltext_index_to_users_name.php tests/Feature/Courses/CourseSearchTest.php
git commit -m "Add STORED name column + FULLTEXT index migration (MariaDB)"
```

---

### Task 3: Wire search into `CourseController::index`

**Files:**
- Modify: `app/Http/Controllers/CourseController.php:23-41` (the `index` method)
- Test: `tests/Feature/Courses/CourseSearchTest.php` (append controller-level tests)

**Interfaces:**
- Consumes: `scopeWithSearch` (Task 1); route `courses.index` (existing named route).
- Produces: Inertia `Courses/Index` props gain `filters => ['search' => <term>]`; `courses` paginator is filtered by the scope.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Courses/CourseSearchTest.php`:

```php
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('filters the courses index by the search query and echoes the term', function () {
    $admin = User::factory()->admin()->create();
    Course::factory()->create(['title' => 'Welding Fundamentals']);
    Course::factory()->create(['title' => 'Ceramics 101']);

    actingAs($admin)
        ->get(route('courses.index', ['search' => 'welding']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Index')
            ->where('filters.search', 'welding')
            ->has('courses.data', 1)
            ->where('courses.data.0.title', 'Welding Fundamentals'));
});

it('returns the full courses index when no search term is given', function () {
    $admin = User::factory()->admin()->create();
    Course::factory()->count(2)->create();

    actingAs($admin)
        ->get(route('courses.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', null)
            ->has('courses.data', 2));
});
```

Confirm `User::factory()->admin()` exists (seen in `UserFactory`); if the admin state has a different name, adjust to match the factory.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="filters the courses index"`
Expected: FAIL — `filters.search` prop missing / `courses.data` count is 2 not 1.

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/CourseController.php`, replace the `index` method body query + render with:

```php
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Course::class);

        $user = $request->user();

        $courses = Course::query()
            ->when(
                ! $user->hasRole(UserRole::Admin->value),
                fn ($query) => $query->where('instructor_id', $user->id),
            )
            ->withSearch($request->query('search'))
            ->latest()
            ->paginate(self::PER_PAGE, ['id', 'title', 'slug', 'status', 'level'])
            ->withQueryString();

        return Inertia::render('Courses/Index', [
            'courses' => $courses,
            'filters' => ['search' => $request->query('search')],
        ]);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=CourseSearchTest`
Expected: PASS (all cases, including the two new controller tests).

- [ ] **Step 5: Lint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/CourseController.php tests/Feature/Courses/CourseSearchTest.php
git commit -m "Filter courses index via withSearch scope"
```

---

### Task 4: Reusable `SearchInput.vue` + mount on Courses index

**Files:**
- Create: `resources/js/Components/SearchInput.vue`
- Modify: `resources/js/Pages/Courses/Index.vue` (import, `filters` prop, render `<SearchInput>`)
- Test: `tests/Browser/CourseSearchInputTest.php` (Pest 4 browser test)

**Interfaces:**
- Consumes: `@/Components/ui/input` (`Input`), `lucide-vue-next` (`Search`), Inertia `router`; `filters.search` prop from Task 3.
- Produces: `SearchInput` component with props `initial: String`, `placeholder: String`, `paramName: String` (default `search`); performs debounced `router.get(window.location.pathname, { [paramName]: term }, { preserveState, preserveScroll, replace })`.

- [ ] **Step 1: Create the component**

Create `resources/js/Components/SearchInput.vue`:

```vue
<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { Input } from '@/Components/ui/input';
import { Search } from 'lucide-vue-next';

const props = defineProps({
    initial: { type: String, default: '' },
    placeholder: { type: String, default: 'Search…' },
    paramName: { type: String, default: 'search' },
});

const query = ref(props.initial);

let debounce_timer = null;

watch(query, (value) => {
    if (debounce_timer) {
        clearTimeout(debounce_timer);
    }

    debounce_timer = setTimeout(() => {
        const term = value.trim();
        const data = term === '' ? {} : { [props.paramName]: term };

        router.get(window.location.pathname, data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});

onBeforeUnmount(() => {
    if (debounce_timer) {
        clearTimeout(debounce_timer);
    }
});
</script>

<template>
    <div class="relative w-full max-w-sm">
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input
            v-model="query"
            type="search"
            :placeholder="placeholder"
            class="pl-9"
        />
    </div>
</template>
```

- [ ] **Step 2: Mount on the Courses index**

In `resources/js/Pages/Courses/Index.vue`, add the import alongside the other component imports:

```js
import SearchInput from '@/Components/SearchInput.vue';
```

Extend `defineProps` to accept `filters`:

```js
defineProps({
    courses: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: '' }),
    },
});
```

Immediately after the closing `</PageHeader>` tag in the template, add a toolbar row:

```vue
        <div class="mb-4">
            <SearchInput :initial="filters.search ?? ''" placeholder="Search courses…" />
        </div>
```

- [ ] **Step 3: Build the frontend**

Run: `npm run build`
Expected: build succeeds with no Vite/Vue compile errors.

- [ ] **Step 4: Write the browser test**

Create `tests/Browser/CourseSearchInputTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\User;

it('filters the visible course rows as the user types', function () {
    $admin = User::factory()->admin()->create();
    Course::factory()->create(['title' => 'Welding Fundamentals']);
    Course::factory()->create(['title' => 'Ceramics 101']);

    $page = visit(route('courses.index'))->actingAs($admin);

    $page->assertSee('Welding Fundamentals')
        ->assertSee('Ceramics 101')
        ->fill('search', 'welding')
        ->assertSee('Welding Fundamentals')
        ->assertDontSee('Ceramics 101')
        ->assertNoJavascriptErrors();
});
```

If this project's browser-test bootstrap differs (check a sibling test in `tests/Browser`), match its `actingAs`/`visit` ordering and helpers. If no `tests/Browser` harness exists, skip this file and rely on Task 3's feature tests for server behavior plus the `npm run build` check; note the omission in the commit message.

- [ ] **Step 5: Run the browser test**

Run: `php artisan test --compact --filter=CourseSearchInputTest`
Expected: PASS — typing `welding` filters the table to the one matching row with no JS errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/SearchInput.vue resources/js/Pages/Courses/Index.vue tests/Browser/CourseSearchInputTest.php
git commit -m "Add reusable SearchInput; wire into Courses index"
```

---

### Task 5: Full regression + lint sweep

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test --compact`
Expected: all tests pass (no regressions in `CourseManagementTest`, `CourseAuthorizationTest`, `RosterTest`).

- [ ] **Step 2: Lint sweep**

Run: `vendor/bin/pint --dirty --format agent`
Expected: clean.

- [ ] **Step 3: Final commit (only if lint changed anything)**

```bash
git add -A
git commit -m "Lint sweep for Searchable trait feature"
```

---

## Notes / Manual Verification (MariaDB full-text)

The MariaDB `MATCH…AGAINST` branch of `applyFullTextSearch` cannot be exercised by the
SQLite test suite. To verify in a MariaDB environment: run `php artisan migrate`, confirm
`SHOW INDEX FROM users` lists a `FULLTEXT` index on `name`, then hit
`/courses?search=<instructor last name>` and confirm relationship matching. This is the
only path not covered by automated tests.
