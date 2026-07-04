# Profile Change-Password Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a logged-in user change their own password from their profile page, supplying only a new password and confirmation.

**Architecture:** Add a `PUT users/{user}/password` route handled by a new `UserProfileController@updatePassword`, authorized by the existing `UserPolicy@update` via a new `UpdatePasswordRequest`. The `User` model's `password` cast (`'hashed'`) auto-hashes on save. The frontend adds a second owner-only form card to `Profile/Show.vue`.

**Tech Stack:** Laravel 13, Inertia 3, Vue 3, Pest 4. Validation uses `Illuminate\Validation\Rules\Password::defaults()`.

## Global Constraints

- No current-password field — the form accepts new password + confirmation only.
- Password strength: `Password::defaults()` (matches the reset-password flow).
- No session/device invalidation on change.
- Naming per project standards: `snake_case` variables, `camelCase` methods, `TitleCase` classes.
- PHP: curly braces on all control structures; explicit return types & param type hints; constructor property promotion where applicable.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes.
- Tests are Pest; run with `php artisan test --compact --filter=...`.

---

### Task 1: Backend — request, route, controller method

**Files:**
- Create: `app/Http/Requests/Profile/UpdatePasswordRequest.php`
- Modify: `app/Http/Controllers/UserProfileController.php` (add `updatePassword`, import the request)
- Modify: `routes/web.php` (add the route beside the other `users/{user}` routes)
- Test: `tests/Feature/Profile/UserProfileTest.php` (extend)

**Interfaces:**
- Consumes: `UserPolicy@update` (owner-only; admins via global `Gate::before`), `User` model with `password` cast `'hashed'`.
- Produces: named route `users.password.update` (`PUT users/{user}/password`); controller method `updatePassword(UpdatePasswordRequest $request, User $user): RedirectResponse` redirecting `back()->with('status', 'password-updated')`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Profile/UserProfileTest.php`:

```php
test('a user can change their own password', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->put(route('users.password.update', $user), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'password-updated');

    expect(Hash::check('new-secure-password', $user->refresh()->password))->toBeTrue();
});

test('changing password requires a matching confirmation', function (): void {
    $user = User::factory()->student()->create();
    $original = $user->password;

    $this->actingAs($user)
        ->put(route('users.password.update', $user), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'does-not-match',
        ])
        ->assertSessionHasErrors('password');

    expect($user->refresh()->password)->toBe($original);
});

test('changing password rejects a weak password', function (): void {
    $user = User::factory()->student()->create();
    $original = $user->password;

    $this->actingAs($user)
        ->put(route('users.password.update', $user), [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertSessionHasErrors('password');

    expect($user->refresh()->password)->toBe($original);
});

test('a user cannot change another users password', function (): void {
    $user = User::factory()->student()->create();
    $other = User::factory()->student()->create();
    $original = $other->password;

    $this->actingAs($user)
        ->put(route('users.password.update', $other), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
        ->assertForbidden();

    expect($other->refresh()->password)->toBe($original);
});
```

Add the `Hash` import at the top of the test file (alongside the existing `use` lines):

```php
use Illuminate\Support\Facades\Hash;
```

Note: `Password::defaults()` requires 8+ characters by default, so `'short'` (5 chars) fails and `'new-secure-password'` passes. If the app has configured stricter defaults (uppercase/symbols), adjust the passing password in these tests to satisfy them.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter='change their own password|matching confirmation|weak password|cannot change another'`
Expected: FAIL — route `users.password.update` is not defined.

- [ ] **Step 3: Create the form request**

Create `app/Http/Requests/Profile/UpdatePasswordRequest.php`:

```php
<?php

namespace App\Http\Requests\Profile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * Only the profile's owner (or an admin, via Gate::before) may change the password.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
```

- [ ] **Step 4: Add the controller method**

In `app/Http/Controllers/UserProfileController.php`, add the import near the other request imports:

```php
use App\Http\Requests\Profile\UpdatePasswordRequest;
```

Add this method after `update()`:

```php
/**
 * Change the owner's password. The 'hashed' cast hashes the value on save.
 */
public function updatePassword(UpdatePasswordRequest $request, User $user): RedirectResponse
{
    $user->update($request->validated());

    return back()->with('status', 'password-updated');
}
```

- [ ] **Step 5: Register the route**

In `routes/web.php`, directly below the existing `Route::patch('users/{user}', ...)->name('users.update');` line, add:

```php
Route::put('users/{user}/password', [UserProfileController::class, 'updatePassword'])->name('users.password.update');
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact --filter='change their own password|matching confirmation|weak password|cannot change another'`
Expected: PASS (4 passing).

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Profile/UpdatePasswordRequest.php app/Http/Controllers/UserProfileController.php routes/web.php tests/Feature/Profile/UserProfileTest.php
git commit -m "Add change-password endpoint to user profile"
```

---

### Task 2: Frontend — password card in Profile/Show.vue

**Files:**
- Modify: `resources/js/Pages/Profile/Show.vue`

**Interfaces:**
- Consumes: named route `users.password.update` from Task 1; `props.profile.id`; existing `@/Components/ui/*` (`Input`, `Label`, `Button`) and `useForm`.
- Produces: no downstream consumers (final UI task).

- [ ] **Step 1: Add the password form state**

In the `<script setup>` block of `resources/js/Pages/Profile/Show.vue`, after the `messageForm` declaration (around line 31), add:

```js
const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

const submitPassword = () => {
    passwordForm.put(route('users.password.update', props.profile.id), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};
```

- [ ] **Step 2: Add the password card to the template**

In the owner-only column (`<div v-if="can_edit" class="lg:col-span-2">`), immediately after the closing `</form>` of the "Account details" form (line 170) and before that column's closing `</div>` (line 171), insert a second form card:

```html
                <form class="mt-6 rounded-2xl border bg-card p-6 shadow-sm" @submit.prevent="submitPassword">
                    <h2 class="font-display text-base font-bold tracking-tight">Password</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Choose a new password for your account.</p>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label for="password">New password</Label>
                            <Input id="password" v-model="passwordForm.password" type="password" autocomplete="new-password" />
                            <p v-if="passwordForm.errors.password" class="text-sm text-destructive">
                                {{ passwordForm.errors.password }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="password_confirmation">Confirm new password</Label>
                            <Input id="password_confirmation" v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" />
                        </div>
                    </div>

                    <div class="mt-6 flex items-center gap-3">
                        <Button type="submit" :disabled="passwordForm.processing">Update password</Button>
                        <transition
                            enter-active-class="transition ease-out duration-300"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in duration-200"
                            leave-to-class="opacity-0"
                        >
                            <p v-if="passwordForm.recentlySuccessful" class="text-sm text-emerald-600">Password updated.</p>
                        </transition>
                    </div>
                </form>
```

- [ ] **Step 3: Verify it builds**

Run: `npm run build`
Expected: builds without errors; the new card compiles into the Profile/Show chunk.

- [ ] **Step 4: Manual smoke check (optional but recommended)**

Log in, visit your own profile, and confirm the "Password" card appears below "Account details", that a mismatched confirmation shows an error under the new-password field, and that a valid change shows "Password updated." Errors surface via `passwordForm.errors.password` (Laravel's `confirmed` rule attaches the message to `password`).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Profile/Show.vue
git commit -m "Add change-password card to profile page"
```

---

## Self-Review

**Spec coverage:**
- No current-password check → Task 1 request rules omit it. ✓
- `Password::defaults()` strength → Task 1 Step 3. ✓
- No session invalidation → nothing added; controller only updates password. ✓
- Route `PUT users/{user}/password` named `users.password.update` → Task 1 Step 5. ✓
- Owner-only authorization via `UserPolicy@update` → Task 1 `authorize()`. ✓
- Second owner-only form card in `Profile/Show.vue` → Task 2. ✓
- Tests: happy path, confirmation mismatch, weak password, cross-user 403 → Task 1 Step 1. ✓

**Placeholder scan:** No TBD/TODO; all code shown in full. ✓

**Type consistency:** Route name `users.password.update`, method `updatePassword`, request `UpdatePasswordRequest`, and form field names `password` / `password_confirmation` are identical across backend, tests, and frontend. ✓
