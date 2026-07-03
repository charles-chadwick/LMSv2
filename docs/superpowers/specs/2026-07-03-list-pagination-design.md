# List Pagination — Design

**Date:** 2026-07-03
**Status:** Approved, ready for implementation plan

## Summary

Add classic numbered pagination to the app's four flat list pages, which today
fetch every row via `->get()` and ship a bare array to the frontend. Each index
query switches to `->paginate($perPage)->through($map)`, and a single shared
`Pagination.vue` renders numbered page links consistently across grids and tables.

## Decisions (from brainstorming)

- **Style:** numbered pages (Laravel `paginate()` + page-number links), used
  uniformly on every list — not prev/next-only, not infinite scroll.
- **Scope:** Catalog, Courses index, My Courses (Enrollments), and Roster.
- **Out of scope:** Curriculum and Dashboard (not flat lists); no page-size
  selector, no filtering/sorting this pass.

## Architecture

### 1. Backend — `paginate()->through()` on the four index queries

Each controller replaces `->get()->map($fn)` with
`->paginate($perPage)->through($fn)->withQueryString()`. `through()` applies the
existing per-row transform (unchanged, including the
`UserSummaryResource::make(...)->resolve()` calls) while returning a paginator;
`withQueryString()` keeps any current query params on the generated links
(future-proofing for later filters/sorts).

Page-size constants (one per controller):

| Controller | Prop | Per page |
| --- | --- | --- |
| `CourseCatalogController@index` | `courses` | 12 |
| `CourseController@index` | `courses` | 15 |
| `EnrollmentController@index` | `enrollments` | 15 |
| `Course/RosterController@index` | `students` | 20 |

Because these are plain paginators (not API-resource collections), the prop
serializes to Laravel's **flat** paginator shape:

```json
{
  "data": [ ... ],
  "current_page": 1,
  "last_page": 5,
  "per_page": 12,
  "total": 51,
  "from": 1,
  "to": 12,
  "prev_page_url": null,
  "next_page_url": "http://.../catalog?page=2",
  "path": "http://.../catalog",
  "first_page_url": "...",
  "last_page_url": "...",
  "links": [
    { "url": null, "label": "&laquo; Previous", "active": false },
    { "url": "http://.../catalog?page=1", "label": "1", "active": true },
    { "url": "http://.../catalog?page=2", "label": "2", "active": false },
    { "url": "http://.../catalog?page=2", "label": "Next &raquo;", "active": false }
  ]
}
```

Notes:
- `RosterController@index` currently returns `students` inside the `Inertia::render`
  alongside `course`. The paginator replaces the `students` value only; `course`
  is unchanged.
- The Roster header count and empty-state read `students` today; they move to
  `students.total` / `students.data` (see frontend).

### 2. Frontend — shared `Pagination.vue`

New `resources/js/Components/Pagination.vue`:

- **Props:** `paginator` (the flat paginator object).
- **Prev/Next:** chevron buttons driven by `paginator.prev_page_url` /
  `paginator.next_page_url`; rendered disabled (non-link) when the URL is `null`.
- **Numbered links:** iterate `paginator.links`, skipping the first and last
  entries (Laravel's Previous/Next), rendering the remaining entries as page
  buttons. Entries whose `url` is `null` (the `…` ellipsis) render as inert
  spacers; the entry with `active: true` gets the active style.
- Each link is an Inertia `<Link>` to its `url` with `preserve-scroll` and
  `preserve-state`, so paging keeps scroll position and any local UI state.
- Renders nothing when `paginator.last_page === 1` (or `total === 0`).
- Styling follows the existing `ui/button` + `cn()` conventions; icons from
  `lucide-vue-next` (`ChevronLeft` / `ChevronRight`), matching the app.

Per-page Vue changes:

| Page | Iterate | Count source | Empty check |
| --- | --- | --- | --- |
| `Catalog/Index.vue` | `courses.data` | `courses.total` | `courses.total === 0` |
| `Courses/Index.vue` | `courses.data` | `courses.total` | `courses.total === 0` |
| `Enrollments/Index.vue` | `enrollments.data` | `enrollments.total` | `enrollments.total === 0` |
| `Courses/Roster.vue` | `students.data` | `students.total` | `students.total === 0` |

Each page drops `<Pagination :paginator="list" />` immediately below its grid or
table. Prop types change from `Array` to `Object`. Existing empty-state markup is
reused, just re-driven by `total`.

## Data flow

1. Controller runs the scoped/ordered query, calls `->paginate($perPage)`,
   transforms rows via `->through($fn)`, applies `->withQueryString()`.
2. Inertia serializes the paginator into the page prop.
3. The Vue page renders `prop.data`; `<Pagination>` renders links from the
   paginator metadata.
4. Clicking a page link is a standard Inertia GET to `?page=N`
   (`preserve-scroll`, `preserve-state`); the controller returns that page's slice.

## Error handling

- Out-of-range `?page=N` (beyond `last_page`) yields an empty `data` array with
  valid metadata — the page renders its normal empty-state; no error.
- `page` is a standard Laravel paginator query param; non-integer values are
  coerced/ignored by the paginator.
- Authorization on each index is unchanged (Roster still authorizes
  `viewRoster`, etc.).

## Testing (Pest feature tests)

- **Update existing index tests** that assert against the old array shape
  (e.g. `->has('courses', N)`, `students.0.id`) to the `.data.*` paths.
- **New assertions per paginated index:**
  - The prop carries paginator keys: `data`, `links`, `total`, `current_page`,
    `last_page`.
  - Seed more than one page worth of rows; assert page 1 returns `$perPage`
    items and the correct `total` / `last_page`.
  - Assert `?page=2` returns the next slice (different first id than page 1).
- Roster: assert the paginated `students.data.0.user.*` shape still resolves via
  `UserSummaryResource` (regression guard for the `through()` transform).

## Out of scope (possible follow-ups)

- Page-size selector.
- Column sorting / filtering (the `withQueryString()` groundwork is laid).
- Infinite scroll or prev/next-only variants.
- Pagination for Curriculum/Dashboard (not flat lists).
