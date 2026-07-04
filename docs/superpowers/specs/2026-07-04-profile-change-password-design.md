# Profile — Change Password

## Goal

Let a logged-in user change their own account password from their profile
page (`Profile/Show.vue`). No such feature exists today; only the
unauthenticated forgot/reset-password flow does.

## Decisions

- **No current-password check.** The form asks only for a new password and a
  confirmation. (Trade-off acknowledged: this is less strict than requiring the
  current password; chosen deliberately for simplicity.)
- **Strength rules:** reuse `Illuminate\Validation\Rules\Password::defaults()`
  so validation matches the existing reset-password flow.
- **No session invalidation.** Other active sessions/devices stay logged in.
  The hand-rolled Breeze-style auth has no multi-session invalidation today and
  we are not adding it here.

## Backend

### Route

Add inside the existing `auth` + `verified` group in `routes/web.php`,
alongside the other `users/{user}` routes:

```php
Route::put('users/{user}/password', [UserProfileController::class, 'updatePassword'])
    ->name('users.password.update');
```

Keyed by `{user}` (route-model binding), consistent with `users.update`.

### Form Request

New `App\Http\Requests\Profile\UpdatePasswordRequest` (same namespace as
`UpdateProfileRequest`):

- `authorize()`: `return $this->user()->can('update', $this->route('user'));`
  — reuses `UserPolicy@update` (owner; admins via the global `Gate::before`),
  identical to profile edit authorization.
- `rules()`:

  ```php
  return [
      'password' => ['required', 'confirmed', Password::defaults()],
  ];
  ```

  No `current_password` field.

### Controller

Add `updatePassword` to `UserProfileController`:

```php
public function updatePassword(UpdatePasswordRequest $request, User $user): RedirectResponse
{
    $user->update($request->validated());

    return back()->with('status', 'password-updated');
}
```

The `password` cast is `'hashed'`, so assigning the validated plaintext
auto-hashes it — no manual `Hash::make`. The distinct `'password-updated'`
status lets the UI show password-specific success feedback separate from the
profile-update flash.

## Frontend — `Profile/Show.vue`

- Add a second `<form>` card titled "Password", placed below the existing
  "Account details" card, inside the owner-only `v-if="can_edit"` column.
- New form state:

  ```js
  const passwordForm = useForm({ password: '', password_confirmation: '' })
  ```

- Two `Input type="password"` fields (New password, Confirm new password) using
  the existing `@/Components/ui/*` components (`Input`, `Label`, `Button`).
- Submit:

  ```js
  passwordForm.put(route('users.password.update', props.profile.id), {
      preserveScroll: true,
      onSuccess: () => passwordForm.reset(),
  })
  ```

- Per-field errors via `passwordForm.errors.password`; a
  `passwordForm.recentlySuccessful` confirmation transition mirroring the
  existing profile form.

## Testing — extend `tests/Feature/Profile/UserProfileTest.php`

- Owner can update their own password; assert `Hash::check(newPassword, ...)`
  passes for the reloaded user.
- New password must be confirmed: a mismatched confirmation yields a validation
  error and leaves the password unchanged.
- Weak password is rejected by `Password::defaults()`.
- A different (non-admin) user cannot update someone else's password (403),
  mirroring the existing cross-user profile-update test.

## Out of scope

- Requiring the current password.
- Logging out other sessions/devices.
- Any schema change or new dependency.
