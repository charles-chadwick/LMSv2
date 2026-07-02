# Authentication — Design Spec

**Date:** 2026-07-02
**Status:** Approved for planning
**Phase:** First application-layer slice (auth foundation)

## Context

LMSv2 has a complete data/domain layer (16 models, migrations, 7 enums, 5 Loris Leiva
Actions, factories, seeders, Spatie roles/permissions) but no application layer: no
authentication, no real routes, no controllers, no policies, and only a placeholder
`Welcome.vue`. There is no way to log in. This phase builds the authentication backbone
that every later feature slice depends on.

## Decisions (locked)

| Decision | Choice |
|----------|--------|
| Scaffolding | **Hand-rolled** — controllers, form requests, routes, and Vue pages built manually to match the codebase's hand-built domain layer. No auth starter kit, no new dependencies. |
| Account creation | **Admin-only provisioning.** No public registration page. Accounts come from seeders now; an admin user-management UI is a later slice. |
| Phase scope | **Login + password reset + verification + logout backbone only.** Admin user-creation/management UI is explicitly deferred. |
| Features | Password reset (email link), remember-me, email verification, login rate limiting. |
| Post-login landing | **Minimal role-aware dashboard stub** at `/dashboard` behind an authenticated layout. Establishes the authenticated shell later slices fill in. |

## Non-Goals (deferred to later slices)

- Public / self-service registration.
- Admin user-management CRUD (create users, assign roles through the UI).
- Per-role distinct dashboards (single stub for now).
- Authorization policies for domain resources (separate slice; foundation via Spatie `Gate::before` already exists).

## Architecture

Thin controllers that render Inertia pages and delegate. Validation and auth logic live in
Form Requests, following Laravel's `LoginRequest` pattern. Everything uses framework
built-ins (`Auth`, `Password`, `MustVerifyEmail`, `RateLimiter`) — no new packages.

### Routes (`routes/web.php`)

Existing public `GET /` (`home`) is unchanged.

**Guest middleware group:**
- `GET  /login` → `AuthenticatedSessionController@create` (name `login`)
- `POST /login` → `AuthenticatedSessionController@store`
- `GET  /forgot-password` → `PasswordResetLinkController@create` (name `password.request`)
- `POST /forgot-password` → `PasswordResetLinkController@store` (name `password.email`)
- `GET  /reset-password/{token}` → `NewPasswordController@create` (name `password.reset`)
- `POST /reset-password` → `NewPasswordController@store` (name `password.store`)

**Auth middleware group:**
- `POST /logout` → `AuthenticatedSessionController@destroy` (name `logout`)
- `GET  /verify-email` → `EmailVerificationPromptController` (name `verification.notice`)
- `GET  /verify-email/{id}/{hash}` → `VerifyEmailController` (`signed`, `throttle:6,1`, name `verification.verify`)
- `POST /email/verification-notification` → `EmailVerificationNotificationController@store` (`throttle:6,1`, name `verification.send`)
- `GET  /dashboard` → `DashboardController@index` (`verified`, name `dashboard`)

### Controllers (`app/Http/Controllers/Auth/`)

- `AuthenticatedSessionController` — `create()` renders `Auth/Login`; `store(LoginRequest)` authenticates, regenerates session, redirects to `dashboard`; `destroy()` logs out, invalidates session.
- `PasswordResetLinkController` — `create()` renders `Auth/ForgotPassword`; `store()` sends reset link via `Password::sendResetLink`.
- `NewPasswordController` — `create()` renders `Auth/ResetPassword` with token+email; `store()` resets via `Password::reset`.
- `EmailVerificationPromptController` — `__invoke()` redirects verified users to `dashboard`, else renders `Auth/VerifyEmail`.
- `VerifyEmailController` — `__invoke(EmailVerificationRequest)` marks verified, redirects to `dashboard`.
- `EmailVerificationNotificationController` — `store()` resends verification email.
- `DashboardController` — `index()` renders `Dashboard` (role-aware; roles come from shared props).

### Form Requests (`app/Http/Requests/Auth/`)

- `LoginRequest` — rules (`email`, `password`); `authenticate()` method with rate limiting keyed on `email|ip`, throttled to 5 attempts, fires `Lockout` event, uses `remember` boolean; `ensureIsNotRateLimited()` + `throttleKey()`.
- Password-reset validation inline in controllers or dedicated requests as fits Laravel convention (`email` for link, `token`/`email`/`password` confirmed for reset).

### Model change

`app/Models/User.php` — implement `MustVerifyEmail` (contract currently commented out). Add
to the `implements` list alongside `HasMedia`. `email_verified_at` cast already present.

### Shared Inertia data

`app/Http/Middleware/HandleInertiaRequests.php` — extend `share()` `auth.user` to include
`roles` via `$request->user()?->getRoleNames()` so Vue can render role-aware UI. This is the
only existing file modified (besides `User`, routes, and the seeder).

### Frontend (`resources/js/`)

Layouts (`Layouts/`):
- `GuestLayout.vue` — centered card shell for auth forms.
- `AuthenticatedLayout.vue` — authenticated shell with nav + logout button; the reusable shell later feature slices populate.

Pages (`Pages/Auth/` + `Pages/Dashboard.vue`):
- `Auth/Login.vue` — email, password, remember checkbox, link to forgot-password.
- `Auth/ForgotPassword.vue` — email; shows status.
- `Auth/ResetPassword.vue` — token+email (hidden), new password + confirmation.
- `Auth/VerifyEmail.vue` — resend button + logout.
- `Dashboard.vue` — greets user by name, shows role(s), inside `AuthenticatedLayout`.

Forms use Inertia v3 `useForm` / `<Form>` conventions (per `inertia-vue-development` skill).

### Seeder change

`DatabaseSeeder` — ensure seeded users have `email_verified_at` set so existing dev logins
(`admin@`, `instructor@`, `student@example.com`) are not locked out by the new `verified`
middleware. Confirm/adjust `UserFactory` default (unverified state available if needed).

## Data Flow

1. Guest hits protected route → `auth` middleware redirects to `login`.
2. `Auth/Login` posts credentials → `LoginRequest::authenticate()` checks rate limit, attempts
   login with remember flag → success regenerates session, redirects to `dashboard`.
3. `verified` middleware on `dashboard`: unverified users redirect to `verification.notice`.
4. `Dashboard` reads `auth.user` (incl. roles) from shared props, renders role-aware greeting.
5. Forgot-password → email link → `reset-password/{token}` → new password → redirect to login.

## Error Handling

- Failed login: `LoginRequest` throws `ValidationException` on `email` with a generic
  "credentials do not match" message; Inertia surfaces it as a form error.
- Rate limit exceeded: `Lockout` event fired; validation error with seconds-remaining message.
- Verification links: `signed` + `throttle:6,1` guard tampering and abuse.
- Password reset: framework status messages surfaced to the Vue page.

## Testing (Pest feature tests, built test-first)

- Login screen renders for guests.
- Users can authenticate with valid credentials → redirected to dashboard.
- Invalid credentials rejected; user stays unauthenticated.
- Rate limiting locks out after 5 failed attempts.
- Remember-me sets the remember cookie.
- Logout invalidates the session.
- Password reset: request link, reset with valid token, reject invalid token.
- Email verification: prompt shown to unverified, valid signed link verifies, invalid rejected.
- `dashboard` requires auth and verification; guests redirected to `login`.
- Shared props include the authenticated user's roles.

## Files Touched

**New:** 7 auth controllers + `DashboardController`, `LoginRequest` (+ any reset requests),
`GuestLayout.vue`, `AuthenticatedLayout.vue`, `Auth/*.vue` (4), `Dashboard.vue`, feature tests.

**Modified:** `routes/web.php`, `app/Models/User.php`, `HandleInertiaRequests.php`,
`DatabaseSeeder.php` (and `UserFactory` if a verified/unverified state is needed).

**No new dependencies.**
