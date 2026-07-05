# Readable Seeder Data Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give seeded Courses, Modules, and Lessons real computer-science titles and populate their body text with random, bad-word-censored Rick and Morty dialogue.

**Architecture:** A new `ComputerScienceTitles` seeder helper supplies curated CS titles; `RickAndMortyDialogue` gains a `censoredBody()` method that concatenates random censored script lines. New factory *states* (`readableContent()`) apply these to Course/Module/Lesson without touching factory `definition()` defaults, so existing deterministic tests are unaffected. `DatabaseSeeder` chains the states onto its factory calls.

**Tech Stack:** Laravel 13, PHP 8.4, Pest 4, Eloquent factories, MariaDB test DB (`DatabaseTruncation`).

## Global Constraints

- Naming: `snake_case` variables, `camelCase` methods, `TitleCase` classes.
- Prefer OOP; explicit return types and param type hints on every method.
- Curly braces on all control structures; PHPDoc over inline comments.
- Do NOT change factory `definition()` defaults — apply via states only.
- Do NOT alter discussion seeding.
- Run `vendor/bin/pint --dirty --format agent` before finalizing.
- Run tests with `php artisan test --compact --filter=...`.

---

### Task 1: `ComputerScienceTitles` helper

**Files:**
- Create: `database/seeders/ComputerScienceTitles.php`
- Test: `tests/Feature/Seeders/ComputerScienceTitlesTest.php`

**Interfaces:**
- Produces:
  - `ComputerScienceTitles::nextCourse(): string` — distinct title per call until pool exhausts, then `"<random title> <n>"`.
  - `ComputerScienceTitles::nextModule(): string` — random module topic.
  - `ComputerScienceTitles::nextLesson(): string` — random lesson topic.
  - `ComputerScienceTitles::reset(): void` — clears the internal course queue (test hook).
  - `const COURSE_TITLES`, `const MODULE_TOPICS`, `const LESSON_TOPICS` (string arrays).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Seeders/ComputerScienceTitlesTest.php`:

```php
<?php

use Database\Seeders\ComputerScienceTitles;

it('exposes non-empty curated title pools', function (): void {
    expect(ComputerScienceTitles::COURSE_TITLES)->not->toBeEmpty()
        ->and(ComputerScienceTitles::MODULE_TOPICS)->not->toBeEmpty()
        ->and(ComputerScienceTitles::LESSON_TOPICS)->not->toBeEmpty();
});

it('returns distinct course titles until the pool is exhausted', function (): void {
    ComputerScienceTitles::reset();

    $count = count(ComputerScienceTitles::COURSE_TITLES);
    $titles = collect(range(1, $count))->map(fn (): string => ComputerScienceTitles::nextCourse());

    expect($titles->unique())->toHaveCount($count);
});

it('still returns a value after the course pool is exhausted', function (): void {
    ComputerScienceTitles::reset();

    $count = count(ComputerScienceTitles::COURSE_TITLES);
    collect(range(1, $count))->each(fn () => ComputerScienceTitles::nextCourse());

    expect(ComputerScienceTitles::nextCourse())->toBeString()->not->toBeEmpty();
});

it('returns module and lesson topics from their pools', function (): void {
    expect(ComputerScienceTitles::MODULE_TOPICS)->toContain(ComputerScienceTitles::nextModule())
        ->and(ComputerScienceTitles::LESSON_TOPICS)->toContain(ComputerScienceTitles::nextLesson());
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ComputerScienceTitlesTest`
Expected: FAIL — class `Database\Seeders\ComputerScienceTitles` not found.

- [ ] **Step 3: Write the implementation**

Create `database/seeders/ComputerScienceTitles.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Support\Collection;

class ComputerScienceTitles
{
    /**
     * Curated, real computer-science course titles.
     *
     * @var list<string>
     */
    public const COURSE_TITLES = [
        'Introduction to Algorithms', 'Data Structures', 'Operating Systems',
        'Computer Networks', 'Database Systems', 'Compiler Construction',
        'Computer Architecture', 'Discrete Mathematics', 'Theory of Computation',
        'Artificial Intelligence', 'Machine Learning', 'Deep Learning',
        'Computer Graphics', 'Human-Computer Interaction', 'Software Engineering',
        'Distributed Systems', 'Cryptography and Security', 'Programming Languages',
        'Functional Programming', 'Object-Oriented Design', 'Web Development',
        'Mobile Application Development', 'Cloud Computing', 'Parallel Computing',
        'Embedded Systems', 'Digital Logic Design', 'Numerical Methods',
        'Linear Algebra for Computing', 'Probability and Statistics',
        'Natural Language Processing', 'Computer Vision', 'Reinforcement Learning',
        'Information Retrieval', 'Data Mining', 'Big Data Analytics',
        'Operating System Design', 'Network Security', 'Ethical Hacking',
        'Quantum Computing', 'Bioinformatics', 'Robotics', 'Game Development',
        'Computer Systems Engineering', 'Formal Methods', 'Automata Theory',
        'Graph Theory', 'Optimization', 'Signal Processing', 'Real-Time Systems',
        'Microservices Architecture', 'DevOps and Continuous Delivery',
        'Version Control and Collaboration', 'Introduction to Programming',
        'Advanced Algorithms', 'Computational Geometry', 'Blockchain Fundamentals',
        'Internet of Things', 'Computer Ethics', 'Information Systems',
        'Systems Programming',
    ];

    /**
     * Curated module-level topic titles.
     *
     * @var list<string>
     */
    public const MODULE_TOPICS = [
        'Foundations and Notation', 'Memory Management', 'Sorting and Searching',
        'Recursion and Iteration', 'Graphs and Trees', 'Dynamic Programming',
        'Greedy Algorithms', 'Hashing and Hash Tables', 'Concurrency and Threads',
        'Process Scheduling', 'File Systems', 'Virtual Memory', 'Network Protocols',
        'Transport Layer', 'Relational Modeling', 'Query Optimization',
        'Normalization', 'Transactions and Concurrency Control', 'Lexical Analysis',
        'Syntax Parsing', 'Semantic Analysis', 'Code Generation', 'Type Systems',
        'Boolean Logic', 'Combinational Circuits', 'Sequential Circuits',
        'Caching Strategies', 'Load Balancing', 'Fault Tolerance',
        'Encryption Fundamentals', 'Public Key Infrastructure',
        'Neural Network Basics', 'Feature Engineering', 'Model Evaluation',
        'Regularization Techniques', 'Vectorization', 'State Management',
        'Testing Strategies', 'Deployment Pipelines', 'Performance Profiling',
    ];

    /**
     * Curated lesson-level topic titles.
     *
     * @var list<string>
     */
    public const LESSON_TOPICS = [
        'Binary Search Trees', 'Red-Black Trees', 'AVL Trees', 'B-Trees',
        'Heaps and Priority Queues', 'Linked Lists', 'Stacks and Queues',
        'Hash Maps', 'Bloom Filters', 'Tries', 'Depth-First Search',
        'Breadth-First Search', "Dijkstra's Shortest Path", 'Bellman-Ford Algorithm',
        'Floyd-Warshall Algorithm', 'Minimum Spanning Trees', 'Topological Sorting',
        'Union-Find', 'Quicksort', 'Merge Sort', 'Heap Sort', 'Radix Sort',
        'Counting Sort', 'Binary Search', 'Two Pointers Technique', 'Sliding Window',
        'Memoization', 'Tabulation', 'Knapsack Problem', 'Longest Common Subsequence',
        'Edit Distance', 'Matrix Chain Multiplication', 'Deadlock Avoidance',
        'Mutual Exclusion', 'Semaphores and Monitors', 'Paging and Segmentation',
        'Page Replacement Algorithms', 'Context Switching', 'Interrupt Handling',
        'TCP Handshake', 'IP Addressing and Subnetting', 'Routing Algorithms',
        'DNS Resolution', 'HTTP and HTTPS', 'Socket Programming', 'SQL Joins',
        'Indexing Strategies', 'ACID Properties', 'Two-Phase Commit',
        'Deadlock Detection', 'Finite State Machines', 'Regular Expressions',
        'Context-Free Grammars', 'Abstract Syntax Trees', 'Register Allocation',
        'Garbage Collection', 'Reference Counting', 'Pointer Arithmetic',
        'Cache Coherence', 'Pipelining', 'Branch Prediction',
        'Floating Point Representation', "Two's Complement", 'Gradient Descent',
        'Backpropagation', 'Overfitting and Underfitting', 'Cross-Validation',
        'Convolutional Layers', 'Attention Mechanisms', 'Tokenization',
        'Word Embeddings', 'Public and Private Keys', 'Hashing and Salting',
        'Digital Signatures', 'SQL Injection Defense', 'Cross-Site Scripting',
        'RESTful Endpoints', 'Dependency Injection', 'Unit Testing Basics',
        'Continuous Integration',
    ];

    /**
     * Remaining, shuffled course titles yet to be handed out.
     *
     * @var Collection<int, string>|null
     */
    private static ?Collection $courseQueue = null;

    /**
     * How many times the course pool has been exhausted and reused.
     */
    private static int $overflow = 0;

    /**
     * A distinct course title while the pool lasts, then a numbered fallback.
     */
    public static function nextCourse(): string
    {
        if (self::$courseQueue === null) {
            self::$courseQueue = collect(self::COURSE_TITLES)->shuffle();
        }

        if (self::$courseQueue->isNotEmpty()) {
            return self::$courseQueue->pop();
        }

        self::$overflow++;

        return collect(self::COURSE_TITLES)->random().' '.self::$overflow;
    }

    /**
     * A random module-level topic title.
     */
    public static function nextModule(): string
    {
        return collect(self::MODULE_TOPICS)->random();
    }

    /**
     * A random lesson-level topic title.
     */
    public static function nextLesson(): string
    {
        return collect(self::LESSON_TOPICS)->random();
    }

    /**
     * Reset the course queue (used to keep test runs deterministic).
     */
    public static function reset(): void
    {
        self::$courseQueue = null;
        self::$overflow = 0;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=ComputerScienceTitlesTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders/ComputerScienceTitles.php tests/Feature/Seeders/ComputerScienceTitlesTest.php
git commit -m "feat: add ComputerScienceTitles seeder helper

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Censored dialogue body

**Files:**
- Modify: `database/seeders/FilterData.php` (make `censor()` case-insensitive)
- Modify: `database/seeders/RickAndMortyDialogue.php` (add `censoredBody()`)
- Test: `tests/Feature/Seeders/CensoredBodyTest.php`

**Interfaces:**
- Consumes: `FilterData::censor(string): string`, `FilterData::hasBadWords(string): bool`.
- Produces: `RickAndMortyDialogue::censoredBody(int $minLines, int $maxLines): string` — non-empty paragraph block of censored script lines.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Seeders/CensoredBodyTest.php`:

```php
<?php

use Database\Seeders\FilterData;
use Database\Seeders\RickAndMortyDialogue;

it('builds non-empty censored dialogue with no un-censored bad words', function (): void {
    foreach (range(1, 25) as $ignored) {
        $body = RickAndMortyDialogue::censoredBody(3, 6);

        expect($body)->toBeString()->not->toBeEmpty()
            ->and(FilterData::hasBadWords($body))->toBeFalse();
    }
});

it('produces a single line for a one-line body', function (): void {
    $body = RickAndMortyDialogue::censoredBody(1, 1);

    expect($body)->not->toContain("\n\n");
});

it('censors bad words regardless of case', function (): void {
    expect(FilterData::censor('That is SHIT and shit'))
        ->not->toContain('SHIT')
        ->not->toContain('shit');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CensoredBodyTest`
Expected: FAIL — `censoredBody` undefined (and the case test fails on current case-sensitive `censor`).

- [ ] **Step 3: Make `censor()` case-insensitive**

In `database/seeders/FilterData.php`, change the body of `censor()` to use `str_ireplace`:

```php
    public static function censor(string $string): string
    {
        foreach (self::BAD_WORDS as $bad_word) {
            $string = str_ireplace($bad_word, str_repeat('*', strlen($bad_word)), $string);
        }

        return $string;
    }
```

- [ ] **Step 4: Add `censoredBody()` to `RickAndMortyDialogue`**

In `database/seeders/RickAndMortyDialogue.php`, add a second cached pool and the method. Add the property alongside the existing `$pool`:

```php
    /**
     * Every script line (unfiltered), cached for censored body generation.
     *
     * @var Collection<int, string>
     */
    private static Collection $allLines;
```

Add these methods to the class:

```php
    /**
     * A paragraph-style block of between $minLines and $maxLines random script
     * lines, each with bad words censored to asterisks.
     */
    public static function censoredBody(int $minLines, int $maxLines): string
    {
        if (! isset(self::$allLines)) {
            self::loadAll();
        }

        $count = random_int($minLines, min($maxLines, self::$allLines->count()));

        return self::$allLines->random($count)
            ->map(fn (string $line): string => trim(str_replace('"', '', FilterData::censor($line))))
            ->filter()
            ->implode("\n\n");
    }

    /**
     * Load every non-empty script line into the {@see self::$allLines} pool.
     */
    private static function loadAll(): void
    {
        $handle = fopen(database_path('rickandmorty/rickandmorty-scripts.csv'), 'r');

        fgetcsv($handle, 0, ',', '"', ''); // Skip the header row.

        $lines = collect();

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line = trim($row[0] ?? '');

            if ($line !== '') {
                $lines->push($line);
            }
        }

        fclose($handle);

        self::$allLines = $lines->values();
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=CensoredBodyTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders/FilterData.php database/seeders/RickAndMortyDialogue.php tests/Feature/Seeders/CensoredBodyTest.php
git commit -m "feat: add case-insensitive censored dialogue body generator

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Factory `readableContent()` states

**Files:**
- Modify: `database/factories/CourseFactory.php`
- Modify: `database/factories/ModuleFactory.php`
- Modify: `database/factories/LessonFactory.php`
- Test: `tests/Feature/Seeders/ReadableContentStatesTest.php`

**Interfaces:**
- Consumes: `ComputerScienceTitles::nextCourse/nextModule/nextLesson`, `RickAndMortyDialogue::censoredBody`.
- Produces:
  - `CourseFactory::readableContent(): static`
  - `ModuleFactory::readableContent(): static`
  - `LessonFactory::readableContent(): static`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Seeders/ReadableContentStatesTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Database\Seeders\ComputerScienceTitles;
use Database\Seeders\FilterData;
use Illuminate\Support\Str;

it('gives a course a CS title, matching slug and censored body', function (): void {
    ComputerScienceTitles::reset(); // Ensure the first pick comes from the pool, not the numbered fallback.

    $course = Course::factory()->readableContent()->create();

    expect(ComputerScienceTitles::COURSE_TITLES)->toContain($course->title)
        ->and($course->slug)->toStartWith(Str::slug($course->title))
        ->and(FilterData::hasBadWords($course->description))->toBeFalse()
        ->and(FilterData::hasBadWords($course->summary))->toBeFalse();
});

it('gives a module a CS topic title and censored description', function (): void {
    $module = Module::factory()->readableContent()->create();

    expect(ComputerScienceTitles::MODULE_TOPICS)->toContain($module->title)
        ->and(FilterData::hasBadWords($module->description))->toBeFalse();
});

it('gives a lesson a CS topic title, matching slug and censored content', function (): void {
    $lesson = Lesson::factory()->readableContent()->create();

    expect(ComputerScienceTitles::LESSON_TOPICS)->toContain($lesson->title)
        ->and($lesson->slug)->toStartWith(Str::slug($lesson->title))
        ->and(FilterData::hasBadWords($lesson->content))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ReadableContentStatesTest`
Expected: FAIL — `readableContent` undefined on the factories.

- [ ] **Step 3: Add the `CourseFactory` state**

In `database/factories/CourseFactory.php`, add imports and the state method. Add to the `use` block:

```php
use Database\Seeders\ComputerScienceTitles;
use Database\Seeders\RickAndMortyDialogue;
```

Add this method to the class:

```php
    /**
     * Replace Faker content with a real CS title and censored dialogue body.
     */
    public function readableContent(): static
    {
        return $this->state(function (array $attributes): array {
            $title = ComputerScienceTitles::nextCourse();

            return [
                'title' => $title,
                'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
                'summary' => RickAndMortyDialogue::censoredBody(1, 1),
                'description' => RickAndMortyDialogue::censoredBody(3, 6),
            ];
        });
    }
```

- [ ] **Step 4: Add the `ModuleFactory` state**

In `database/factories/ModuleFactory.php`, add to the `use` block:

```php
use Database\Seeders\ComputerScienceTitles;
use Database\Seeders\RickAndMortyDialogue;
```

Add this method:

```php
    /**
     * Replace Faker content with a real CS topic and censored description.
     */
    public function readableContent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => ComputerScienceTitles::nextModule(),
            'description' => RickAndMortyDialogue::censoredBody(1, 2),
        ]);
    }
```

- [ ] **Step 5: Add the `LessonFactory` state**

In `database/factories/LessonFactory.php`, add to the `use` block:

```php
use Database\Seeders\ComputerScienceTitles;
use Database\Seeders\RickAndMortyDialogue;
```

Add this method:

```php
    /**
     * Replace Faker content with a real CS topic, matching slug and censored body.
     */
    public function readableContent(): static
    {
        return $this->state(function (array $attributes): array {
            $title = ComputerScienceTitles::nextLesson();

            return [
                'title' => $title,
                'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
                'content' => RickAndMortyDialogue::censoredBody(4, 8),
            ];
        });
    }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=ReadableContentStatesTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/factories/CourseFactory.php database/factories/ModuleFactory.php database/factories/LessonFactory.php tests/Feature/Seeders/ReadableContentStatesTest.php
git commit -m "feat: add readableContent factory states for course/module/lesson

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Wire states into `DatabaseSeeder`

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Consumes: the three `readableContent()` factory states from Task 3.

- [ ] **Step 1: Apply `readableContent()` to the course factory call**

In `database/seeders/DatabaseSeeder.php` `run()`, update the course creation:

```php
        $instructors->each(function (User $instructor) use ($students): void {
            Course::factory()
                ->published()
                ->readableContent()
                ->count(2)
                ->for($instructor, 'instructor')
                ->create()
                ->each(fn (Course $course) => $this->buildCourse($course, $students));
        });
```

- [ ] **Step 2: Apply `readableContent()` to modules and lessons**

In `buildCourse()`, update the module and lesson creation:

```php
        $modules = Module::factory()
            ->count(3)
            ->for($course)
            ->readableContent()
            ->sequence($this->positionSequence())
            ->create();

        $lessons = $modules->flatMap(fn (Module $module) => Lesson::factory()
            ->count(3)
            ->for($module)
            ->readableContent()
            ->sequence($this->positionSequence())
            ->create());
```

- [ ] **Step 3: Run the seeder to verify it completes**

Run: `php artisan migrate:fresh --seed`
Expected: completes without error.

- [ ] **Step 4: Spot-check the seeded data**

Run: `php artisan tinker --execute 'App\Models\Course::query()->take(3)->get(["title","summary"])->each(fn($c) => print($c->title." | ".$c->summary."\n")); App\Models\Lesson::query()->take(3)->pluck("title")->each(fn($t) => print($t."\n"));'`
Expected: CS course titles with censored dialogue summaries, CS lesson titles.

- [ ] **Step 5: Run the full seeder test suite plus a smoke of dependent suites**

Run: `php artisan test --compact --filter=Seeders`
Expected: PASS.

Run: `php artisan test --compact`
Expected: PASS (confirms factory-default-dependent tests are untouched).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders/DatabaseSeeder.php
git commit -m "feat: seed readable CS titles and censored dialogue for courses/modules/lessons

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:**
- CS titles for Course/Module/Lesson → Task 1 (helper) + Task 3 (states) + Task 4 (wiring). ✓
- Censored dialogue for description/summary/content → Task 2 + Task 3. ✓
- Bad words censored `(*****)` → Task 2 (`censor` case-insensitive + `censoredBody`). ✓
- Applied via states, defaults unchanged → Task 3 (states only) + Task 4. ✓
- Tests → Tasks 1–3 unit/feature tests + Task 4 full-suite regression. ✓
- Discussions unchanged → not modified. ✓

**Placeholder scan:** No TBD/TODO; all code blocks concrete. ✓

**Type consistency:** `nextCourse/nextModule/nextLesson/reset` and `censoredBody(int,int)` signatures match across Tasks 1–3. `readableContent(): static` consistent across all three factories and Task 4 usage. ✓
