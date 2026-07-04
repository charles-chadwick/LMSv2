# User Management (CRUD) — Design

**Date:** 2026-07-04
**Status:** Approved, ready for planning

## Summary

Admins and instructors need to provision and manage user accounts. Today users
exist only via the database seeder — there is no application-layer provisioning.
This slice adds full user CRUD:

- **Admins** manage every user (any role, including other admins) and can change
  roles.
- **Instructors** can create brand-new **student** accounts and manage only the
  students they personally created.

New accounts are provisioned by **email invitation**: the creator does not set a
password. The invitee receives a link, sets their own password, and is verified
and logged in on acceptance.

This is distinct from the existing roster/enrollment flow (`RosterController`,
`EnrollStudent`), which enrolls *already-existing* students into courses. This
slice is about the user *accounts* themselves.

## Roles & Permissions

| Actor | List (index) sees | Create | Edit / Delete |
|---|---|---|---|
| **Admin** | All users (any role) | Any role incl. admin; may change roles | Any user (soft-delete); **cannot delete self** |
| **Instructor** | Only students they created | Students only (role forced) | Only students where `created_by = self`; **cannot change roles** |

- Admins pass every authorization check via the existing `Gate::before`
  (see `AppServiceProvider`). Policy methods therefore only encode the
  **instructor** rules.
- The role a creator may assign is enforced in the FormRequest, keyed off the
  actor's role (instructor → forced `Student`; admin → any `UserRole`).

## Data Model

Migration adds two columns to `users`:

- `deleted_at` — enables `SoftDeletes`.
- `created_by` — nullable self-referential FK to `users.id`, `nullOnDelete`.
  Null for seeded / self-provisioned accounts.

`User` model changes:

- Add `SoftDeletes` and `Searchable` traits.
  - `searchableFields()` → `['first_name', 'last_name', 'email']` (LIKE).
- Relations: `creator()` (`belongsTo` self via `created_by`) and
  `createdUsers()` (`hasMany` self).
- `created_by` is **not** fillable — it is set explicitly by the `InviteUser`
  action. Role is assigned via Spatie, never mass-assigned. The existing
  `#[Fillable(['first_name','last_name','email','password'])]` is unchanged.

**Derived status (no new column):** account status is computed from
`email_verified_at`:

- `null` → **Invited** (invitation sent, not yet accepted)
- set → **Active**

### Behavior that comes "for free" from SoftDeletes

- The auth provider's `retrieveByCredentials` excludes trashed rows, so a
  soft-deleted user **cannot log in** — no extra guard needed.
- Route-model binding excludes trashed rows, so management/profile routes for a
  deleted user return **404**.

**Trade-off (unique email index):** a soft-deleted user's email remains reserved
by the `users.email` unique index, so the same email cannot be re-invited while
the row is trashed. This is acceptable — it is the same person; restoring reuses
the existing row. (Same class of interaction as the enrollment soft-delete /
unique-index gotcha, but here the reservation is the desired behavior.)

## Invitation Flow

Reuses Laravel's password-broker token infrastructure with a **dedicated** guest
accept route (kept semantically separate from "forgot password").

1. **`InviteUser` action** (`app/Actions/InviteUser.php`):
   - `User::create` with a random unusable password (`Str::password()` /
     hashed random) and `email_verified_at = null`.
   - Assign the requested role (Spatie).
   - Set `created_by = actor->id`.
   - Send the `UserInvitation` notification carrying a password-broker token
     (`Password::createToken($user)`).
2. **`UserInvitation` notification** — mailable linking to
   `GET /invitation/{token}?email=...`.
3. **Accept (guest routes):**
   - `GET /invitation/{token}` → `Invitations/Accept.vue` (shows the
     set-password form; prefilled/read-only email).
   - `POST /invitation` → validate the token via the password broker, set the
     password (strong-password rules, same as the profile change-password
     slice), mark `email_verified_at = now()`, log the user in, redirect to
     `dashboard`.
4. **Resend:** pending invites (status = Invited) expose a resend action that
   re-issues a token and re-sends `UserInvitation`.

## Routes & Controller

A single resourceful `UserManagementController` (thin — authorizes via policy,
delegates creation to `InviteUser`):

```
GET    users                       users.index             (paginated + searchable, policy-scoped)
GET    users/create                users.create
POST   users                       users.store             (→ InviteUser)
GET    users/{user}/edit           users.edit
PUT    users/{user}                users.management.update
DELETE users/{user}                users.destroy           (soft delete)
POST   users/{user}/resend-invite  users.invite.resend
```

Guest invitation routes:

```
GET    invitation/{token}          invitation.create
POST   invitation                  invitation.store
```

### Collision handling with the existing profile controller

`UserProfileController` already owns `GET users/{user}` (`users.show`) and
`PATCH users/{user}` (`users.update`).

- Constrain the profile binding route with `->whereNumber('user')` so
  `GET users/create` resolves to the management `create` action rather than
  binding `{user} = "create"`.
- Register `users/create` before / alongside `users/{user}` as needed for
  correct matching.
- Management update uses **`PUT`**; profile update keeps **`PATCH`** — same URL,
  different verb, distinct route names (`users.management.update` vs
  `users.update`). No path or name collision.
- Profile's own-only `UserPolicy::update` is left untouched.

## Authorization (`UserPolicy`)

Extend the existing policy (which currently only has own-only `update`). New
methods encode instructor rules only (admins short-circuit via `Gate::before`):

- `viewAny(User $actor): bool` — instructor → `true` (list is scoped to their
  own students in the controller query).
- `create(User $actor): bool` — instructor → `true` (role restricted to Student
  in the FormRequest).
- `manage(User $actor, User $target): bool` — instructor → target is a Student
  **and** `target->created_by === actor->id`. Used for `edit` and `update`.
- `delete(User $actor, User $target): bool` — same as `manage`, **and**
  `! $actor->is($target)` (self-delete blocked for everyone, admins included via
  an explicit check in the controller/policy since `Gate::before` would
  otherwise allow it).

**Self-delete guard:** because `Gate::before` grants admins everything, the
"cannot delete self" rule is enforced explicitly (in `delete` policy logic that
runs even for admins, or a controller guard) rather than relying on the policy
alone.

## Frontend (Inertia / Vue)

- `resources/js/Pages/Users/Index.vue` — paginated, searchable table (reuses
  `Pagination.vue` and the search input pattern). Columns: avatar, name, email,
  role, status (Invited/Active), actions (edit, delete, resend-invite for
  pending). Rows are policy-scoped server-side.
- `resources/js/Pages/Users/Create.vue` — form: `first_name`, `last_name`,
  `email`, `role`. Role selector is shown only to admins; instructors submit a
  fixed `Student` role. No password field (invitation flow).
- `resources/js/Pages/Users/Edit.vue` — form: `first_name`, `last_name`,
  `email`, `role` (role editable by admins only). Delete action available per
  policy.
- `resources/js/Pages/Invitations/Accept.vue` — guest set-password form
  (password + confirmation, strong rules), read-only email.

**Resource:** a new `UserManagementResource` for list/edit payloads
(id, first_name, last_name, name, email, role, status, avatar, created_by/creator
name, timestamps). The shared `UserSummaryResource` — deliberately email-less and
used across the app — is left untouched. Honor the nested-resource `->resolve()`
wrapping gotcha when embedding in prop arrays.

**Navigation:** add a "Users" link (to `users.index`) in the app nav, visible to
admins and instructors.

## Testing (Pest feature tests)

Seed `RolePermissionSeeder` in `beforeEach` (RefreshDatabase does not seed).

- Admin creates an instructor and a student → invitation notification sent,
  user persisted with `email_verified_at = null`, `created_by = admin`.
- Instructor creates a student → role forced to Student, `created_by =
  instructor`.
- Instructor **cannot** create an instructor/admin (role restriction).
- Instructor can edit/delete their own student but **not** another instructor's
  student (403).
- Instructor **cannot** change a student's role.
- Index scoping: admin sees all; instructor sees only their own students.
- Soft-deleted user cannot log in and their routes 404.
- Invitation accept: valid token sets password, marks verified, logs in,
  redirects to dashboard; invalid/expired token rejected.
- Resend invite re-issues a token for a pending user.
- Self-delete is blocked (including for admins).

## Out of Scope

- Bulk actions, CSV import.
- Hard delete (soft-delete only).
- Last-admin protection (only **self**-delete is guarded).
- Avatar upload during creation (users manage their own avatar later via the
  existing profile flow).
- Public self-registration (remains disabled).
