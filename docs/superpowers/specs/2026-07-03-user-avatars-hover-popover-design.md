# User Avatars + Hover Popover — Design

**Date:** 2026-07-03
**Status:** Approved, ready for implementation plan

## Summary

Display a user avatar next to their name everywhere a name appears in the UI. The
avatar + name act as a trigger: hovering (or focusing) opens a popover showing a
larger avatar, the user's name, their role, and a **Go to profile** button. Avatars
are real uploaded images (Spatie MediaLibrary) with a deterministic initials
fallback. A minimal profile page provides both the popover button's destination and
the avatar upload UI.

## Decisions (from brainstorming)

- **Avatar type:** real image uploads, initials fallback when no image.
- **Locations covered:** Roster students, instructor on catalog (cards + detail),
  student-search dropdown, and the nav user menu (refactored to the shared component).
- **Upload home:** a minimal profile page.
- **Who can upload:** own avatar only.

## Architecture

### 1. Avatar storage — `User` model (Spatie MediaLibrary, already installed)

- Register a **single-file** `avatar` media collection (`singleFile()`), so a new
  upload replaces the previous image.
- Two **non-queued** conversions (generate synchronously, no queue worker needed):
  - `thumb` — ~64px square, used inline next to names.
  - `preview` — ~160px square, used in the popover and profile page.
- Accessors on `User`:
  - `avatar_thumb_url`: `?string` — `getFirstMediaUrl('avatar', 'thumb')` or `null`.
  - `avatar_preview_url`: `?string` — `getFirstMediaUrl('avatar', 'preview')` or `null`.

### 2. Consistent user shape — `UserSummaryResource`

A single Eloquent API Resource is the one source of truth for how a user is shaped
for the frontend. Chosen over ad-hoc per-controller arrays to prevent drift.

Shape:

```php
[
    'id'             => int,
    'name'           => string,
    'role'           => string,   // first Spatie role, capitalized; fallback "Member"
    'avatar_thumb'   => ?string,
    'avatar_preview' => ?string,
]
```

Wired into:

- `HandleInertiaRequests` → `auth.user` (adds avatar urls + keeps existing `can`).
- `Course/RosterController` → each student row.
- The student-search endpoint backing `StudentSearch.vue` → each result.
- `CourseCatalogController` → **instructor changes from a bare `name` string to the
  resource object** on both index and show. Requires updating `Catalog/Index.vue`
  and `Catalog/Show.vue` bindings.

> Note: `auth.user` currently also carries `can: { create_courses }`. That stays;
> the resource covers identity/avatar fields and `can` is merged alongside it in
> `HandleInertiaRequests`.

### 3. Frontend shared components

Consolidates the initials logic currently duplicated in `AuthenticatedLayout.vue`
and `Roster.vue`.

- **`lib/user.js`** — `getInitials(name)` and deterministic `avatarColor(user)`
  (hash of id/name → fixed palette). Mirrors the `lib/utils.js` pattern.
- **`Components/UserAvatar.vue`** — wraps the existing reka-ui `Avatar`; renders
  `AvatarImage(:src)` with an initials `AvatarFallback` colored by `avatarColor`.
  Props: `user` (summary shape), `size` (`sm` | `md` | `lg`).
- **`Components/ui/hover-card/`** — scaffold shadcn-style wrappers over reka-ui's
  `HoverCard` primitive (`HoverCard`, `HoverCardTrigger`, `HoverCardContent`).
- **`Components/UserHoverCard.vue`** — trigger slot = `UserAvatar` + name inline;
  content = `preview` avatar, name, role, and a **Go to profile** button linking to
  `route('users.show', user.id)`. Used in Roster rows, catalog instructor, and the
  nav user menu.
  - The student-search dropdown uses `UserAvatar` only (no popover inside a
    dropdown).
  - Hover cards open on hover/focus; on touch devices the profile is still reachable
    by tapping the name/avatar link.

### 4. Profile page

- **`GET /users/{user}`** → `UserProfileController@show` → Inertia `Profile/Show`.
  - Read-only for anyone: preview avatar, name, role.
  - If the viewed user is the authenticated user, the page also renders the edit
    form (name/email + avatar upload/remove).
- Own-only edit endpoints (policy/authorization = must be the same user):
  - `PATCH /users/{user}` — update name/email.
  - `POST /users/{user}/avatar` — upload avatar. Validation: mimes jpeg/png/webp,
    max 2 MB.
  - `DELETE /users/{user}/avatar` — remove avatar.
- Every popover's **Go to profile** button targets `route('users.show', id)`, so it
  is always meaningful; the current user's own page additionally shows the editor.

## Data flow

1. Controller loads `User` models (with `roles` eager-loaded to avoid N+1 on role
   name) and wraps them in `UserSummaryResource`.
2. Inertia serializes the resource into page props.
3. `UserAvatar` renders image-or-initials; `UserHoverCard` composes it with the
   popover content.
4. Profile edit posts multipart to the avatar endpoint; MediaLibrary stores the file
   and synchronously builds `thumb`/`preview`; the model's accessors expose fresh
   URLs on the next render.

## Error handling

- Missing/failed avatar image → `AvatarImage` load error falls back to initials
  (reka-ui `AvatarFallback`).
- Upload validation failures return standard Laravel validation errors to the
  Inertia form.
- Non-owner hitting an edit endpoint → 403 via authorization.
- Users with no Spatie role → `role` = "Member".

## Testing (Pest feature tests)

- Avatar upload stores the file and generates `thumb` + `preview`; a second upload
  replaces the first; delete clears it.
- Non-owner receives 403 on update/upload/delete endpoints.
- `UserSummaryResource`: role fallback to "Member"; null avatar urls when no image.
- Catalog index/show, roster, and student-search responses carry the new user shape
  (instructor is an object, not a string).
- Profile show renders the editor for self and read-only for others.

## Out of scope (possible follow-ups)

- Admin-managed avatars for other users.
- Gravatar / external avatar sources.
- Cropping UI; queued conversions.
- A richer public profile (bio, enrolled/authored courses).
