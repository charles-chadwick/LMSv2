# Readable Course & Lesson Seeder Data — Design

**Date:** 2026-07-05
**Status:** Approved

## Goal

Make the seeded database more readable by giving Courses, Modules, and Lessons
real computer-science titles, and populating their body text (course
description/summary, module description, lesson content) with random amounts of
Rick and Morty dialogue with bad words censored (`****`).

## Context

- There is **no `Page` model**. LMS content is `Course → Module → Lesson`;
  `Lesson.content` is the "page" body.
- Existing infrastructure:
  - `database/seeders/RickAndMortyDialogue.php` — `next()` returns a clean,
    title-length dialogue line (bad-word lines excluded). Used for discussion
    titles/bodies.
  - `database/seeders/FilterData.php` — `BAD_WORDS`, `hasBadWords()`,
    `censor()` (replaces bad words with `*` of equal length).
  - `database/rickandmorty/rickandmorty-scripts.csv` — single `line` column,
    ~1905 dialogue lines.
- Factory defaults currently use Faker:
  - `CourseFactory`: `title`=`catchPhrase()`, `summary`=`sentence()`,
    `description`=`paragraphs(3)`.
  - `ModuleFactory`: `title`=`sentence(3)`, `description`=`optional()->sentence()`.
  - `LessonFactory`: `title`=`sentence(4)`, `content`=`paragraphs(4)`.

## Key Decisions

1. **Scope:** Courses **+ Modules + Lessons** get CS titles; Course
   `description`/`summary`, Module `description`, and Lesson `content` get
   censored dialogue.
2. **CS titles source:** Curated hardcoded lists (deterministic, authentic).
3. **Bad words:** **Censor with asterisks** via `FilterData::censor` (not
   line-exclusion), honouring the requested `(*****)` behaviour.
4. **Application via factory states, NOT changed factory defaults.** The
   filter/search feature tests are pinned to deterministic Faker data and the
   CS title list is finite (uniqueness-collision risk). States make the
   *seeded* DB readable while leaving factory defaults — and therefore existing
   tests — untouched.

## Components

### 1. `database/seeders/ComputerScienceTitles.php` (new)

Mirrors `RickAndMortyDialogue`'s static style.

- Curated `const` arrays:
  - `COURSE_TITLES` (~60): e.g. "Introduction to Algorithms",
    "Operating Systems", "Compiler Construction", "Computer Networks".
  - `MODULE_TOPICS` (~40): e.g. "Memory Management", "Sorting & Searching".
  - `LESSON_TOPICS` (~80): e.g. "Red-Black Trees", "Deadlock Avoidance".
- `nextCourse(): string` — pops from a lazily-built shuffled queue so seeded
  courses receive **distinct** titles. When the queue empties, fall back to a
  random title suffixed with an incrementing numeral (safe: ~40–80 courses vs
  ~60 titles).
- `nextModule(): string` / `nextLesson(): string` — return `random()` from
  their pools (repeats across courses are acceptable).

Shuffle uses the seeder run; determinism is not required for seed data.

### 2. `database/seeders/RickAndMortyDialogue.php` (extend)

Add `censoredBody(int $minLines, int $maxLines): string`:

- Lazily cache the **full** script line pool (separate static from the existing
  clean-title pool built by `load()`), reading the same CSV.
- Pick a random line count in `[minLines, maxLines]`.
- Run each chosen line through `FilterData::censor`, strip stray `"` quotes,
  trim, and `implode("\n\n")` into a paragraph-style block.

The existing `next()` and its clean-title pool remain unchanged.

### 3. Factory states (new states, defaults unchanged)

- `CourseFactory::readableContent(): static`
  - `title` = `ComputerScienceTitles::nextCourse()`
  - `slug` = `Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999)`
  - `summary` = one censored dialogue line (`censoredBody(1, 1)`)
  - `description` = `RickAndMortyDialogue::censoredBody(3, 6)`
- `ModuleFactory::readableContent(): static`
  - `title` = `ComputerScienceTitles::nextModule()`
  - `description` = short censored dialogue (`censoredBody(1, 2)`)
- `LessonFactory::readableContent(): static`
  - `title` = `ComputerScienceTitles::nextLesson()`
  - `slug` = `Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999)`
  - `content` = `RickAndMortyDialogue::censoredBody(4, 8)`

Each state regenerates its own `slug` so the slug matches the CS title rather
than the leftover Faker title produced by `definition()`.

### 4. `database/seeders/DatabaseSeeder.php` (edit)

Chain `->readableContent()` onto the Course, Module, and Lesson factory calls
in `run()` / `buildCourse()`. Discussion seeding is unchanged.

## Data Flow

`DatabaseSeeder::run()`
→ `Course::factory()->published()->readableContent()` (CS title + censored
  description/summary)
→ `buildCourse()` → `Module::factory()->readableContent()` and
  `Lesson::factory()->readableContent()` (CS titles + censored body)
→ dialogue text flows from `RickAndMortyDialogue::censoredBody()` which pulls
  random CSV lines → `FilterData::censor` → paragraphs.

## Error Handling / Edge Cases

- **Slug uniqueness:** numeric suffix keeps slugs unique even when a CS title
  repeats.
- **Course title exhaustion:** `nextCourse()` numeral fallback guarantees a
  value beyond the pool size.
- **Empty censor result:** censoring never removes whole lines, so
  `censoredBody` always returns non-empty text for `minLines >= 1`.

## Testing

Add a Pest test (`tests/Unit` or `tests/Feature`) covering:

- `ComputerScienceTitles`: pools are non-empty; repeated `nextCourse()` calls
  yield **distinct** titles within pool size.
- `CourseFactory::readableContent()` / `ModuleFactory::readableContent()` /
  `LessonFactory::readableContent()`: `title` belongs to the matching CS pool;
  `slug` is derived from the title; body fields contain **no un-censored bad
  words** (`FilterData::hasBadWords()` is false on the produced body).
- `RickAndMortyDialogue::censoredBody()`: returns non-empty text with bad words
  censored.

Run the affected tests with `php artisan test --compact --filter=...`.

## Out of Scope

- Changing factory `definition()` defaults.
- Altering discussion seeding.
- Any UI/frontend change.
