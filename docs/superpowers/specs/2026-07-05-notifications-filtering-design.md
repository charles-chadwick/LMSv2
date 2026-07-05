# Notifications Filtering — Design

**Date:** 2026-07-05
**Branch:** feature/extend-filtering
**Status:** Approved (pending spec review)

## Goal

Extend the reusable filtering system (`Filterable` trait + `FilterBar.vue`) to the
Notifications list page, the highest-value list page that currently has no
filtering. Users get read/unread, type, and date-range filters over their full
notification history (with pagination), consistent with the Courses, Catalog,
Enrollments, Roster, and Users lists.

## Context

- The Notifications page (`NotificationController@index` → `Pages/Notifications/Index.vue`)
  currently renders `$user->notifications()->latest()->limit(50)` as a flat array,
  with no filtering or pagination.
- Notifications use Laravel's built-in `Illuminate\Notifications\DatabaseNotification`
  model, which cannot carry the `Filterable` trait as-is.
- Stored notification types live in the `data->type` JSON key. Three types are
  persisted: `new_question`, `new_reply`, `new_message`. `UserInvitation` is
  mail-only (`via()` returns `['mail']`) and is never stored, so it is not a
  filterable type.
- The `broadcastType` clobbering issue (broadcast payload overwrites `data['type']`
  with the class name unless `broadcastType()` is overridden) currently affects
  `NewDiscussionQuestion` and `NewDiscussionReply` — only `NewMessage` overrides it.

## Approach

**Chosen: custom notification model reusing the `Filterable` trait** (over inline
`where` clauses or a dedicated filter class). It is the point of the "reusable
filtering" initiative, reuses all three existing Filter strategies, and a custom
`DatabaseNotification` subclass is a standard, low-risk Laravel extension point.

## Components

### 1. `App\Enums\NotificationType` (new)

Backed enum, single source of truth for notification type strings.

```
NewQuestion => 'new_question'
NewReply    => 'new_reply'
NewMessage  => 'new_message'
```

- Uses the existing `App\Enums\Concerns\HasSelectOptions` trait so
  `NotificationType::options()` feeds the filter's select options.
- Provides human labels for the select (e.g. "Questions", "Replies", "Messages").

### 2. `App\Models\Notification` (new)

```php
class Notification extends Illuminate\Notifications\DatabaseNotification
{
    use Filterable;

    protected function filterableFields(): array
    {
        return [
            'type'       => new ExactFilter('data->type'),
            'read'       => new CallbackFilter(/* 'read' => whereNotNull(read_at); 'unread' => whereNull(read_at) */),
            'created_at' => new RangeFilter('created_at', asDate: true),
        ];
    }
}
```

- `ExactFilter('data->type')` uses Laravel JSON arrow syntax; supports multi-select
  via `whereIn`.
- `read` CallbackFilter maps the string values `'read'` / `'unread'` to
  `read_at IS NOT NULL` / `read_at IS NULL`.

### 3. `User::notifications()` override

Override the relation to return a `MorphMany` of `App\Models\Notification` so the
relation is filterable wherever it is used.

### 4. `NotificationController@index` (change)

```php
$notifications = $request->user()->notifications()
    ->withFilters($request->input('filters'))
    ->paginate(self::PER_PAGE)
    ->through($existingFlatMap);

return Inertia::render('Notifications/Index', [
    'notifications'  => $notifications,
    'filters'        => $request->input('filters', []),
    'filterOptions'  => $this->filterOptions(),
]);
```

- Private `filterOptions()` returns three option descriptors:
  - `type` — select, multiple, options from `NotificationType::options()`
  - `read` — select, options `[{value:'read',label:'Read'},{value:'unread',label:'Unread'}]`
  - `created_at` — daterange
- The latest-50 cap is replaced by pagination (`self::PER_PAGE`).
- No `search` key is echoed back (filter-only page).

### 5. Notification classes (change)

`NewDiscussionQuestion`, `NewDiscussionReply`, `NewMessage`:
- `toArray()` uses `NotificationType::X->value` instead of string literals.
- Each defines `broadcastType()` returning that same value, so broadcast and stored
  types agree (fixes the clobbering for the two discussion notifications).

### 6. `Pages/Notifications/Index.vue` (change)

- Add `<FilterBar :filters="filters" :filter-options="filterOptions" :searchable="false" />`.
- Switch the list from a flat `notifications` array to paginated `notifications.data`
  plus pagination controls (matching the other list pages).
- `notificationLabel`, mark-read, mark-all-read, and open-notification behavior are
  unchanged.

## Data Flow

1. User selects filters in `FilterBar` → `router.get(pathname, { filters })`.
2. `NotificationController@index` reads `filters`, calls
   `notifications()->withFilters($filters)`.
3. `Filterable::scopeWithFilters` consults only declared fields, skips empty values,
   delegates to each Filter strategy.
4. Paginated, mapped results + echoed `filters` + `filterOptions` render the page.

## Error Handling / Edge Cases

- Undeclared filter params are ignored by `scopeWithFilters` (whitelist by design).
- Empty / `''` / `[]` filter values are skipped (no-op filter).
- No filter input → unfiltered, paginated history (behavior superset of today's).
- No input validation layer exists today for filters (read raw from request); this
  page follows the existing convention. A shared `FilterRequest` is explicitly out
  of scope here (tracked separately).

## Testing

`tests/Feature/Notifications/NotificationFilterTest.php` (Pest feature test),
mirroring `EnrollmentFilterTest`:

- Seed a user with notifications spanning each type, both read states, and
  a spread of `created_at` dates.
- Assert `type` filter (single and multi) narrows correctly.
- Assert `read` / `unread` filter narrows correctly.
- Assert `created_at` daterange narrows correctly.
- Assert an undeclared param is ignored.
- Assert pagination works with an active filter.

## Out of Scope

- Search box / full-text over notification bodies.
- Notification-type management UI.
- Changes to notification dispatch beyond centralizing type strings.
- Shared `FilterRequest` validation layer and the `filterOptions()` centralization
  refactor (the agreed follow-up, its own spec).
