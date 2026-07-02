# Authentication Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add hand-rolled authentication (login, logout, password reset, email verification) landing on a role-aware dashboard stub, so the seeded accounts can sign in and every later feature slice has an auth backbone to build on.

**Architecture:** Thin controllers in `app/Http/Controllers/Auth/` render Inertia pages and delegate. Auth + validation logic lives in Form Requests (Laravel's `LoginRequest` pattern). All framework built-ins — `Auth`, `Password`, `MustVerifyEmail`, `RateLimiter` — no new dependencies. Vue pages use Inertia v3 `useForm`; pages auto-resolve from `resources/js/Pages` via `@inertiajs/vite`.

**Tech Stack:** Laravel 13, Inertia Laravel v3, Vue 3, Inertia Vue v3, Tailwind v4, Spatie Permission, Pest 4.

## Global Constraints

- PHP 8.4. Constructor property promotion; explicit return types and param type hints on every method.
- Naming (per CLAUDE.md): variables `snake_case`, methods/functions `camelCase`, classes `TitleCase`. Enum keys `TitleCase`.
- Curly braces on all control structures, even single-line bodies.
- No new Composer/npm dependencies.
- Create files with `php artisan make:` where a generator exists; pass `--no-interaction`.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes, before each commit.
- Tests are Pest feature tests. Run with `php artisan test --compact`.
- Prefer named routes and the `route()` helper for URL generation.
- Vue components have a single root element.
- Commit message trailer: `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.

## File Structure

**New — PHP:**
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` — login create/store, logout destroy
- `app/Http/Controllers/Auth/PasswordResetLinkController.php` — forgot-password
- `app/Http/Controllers/Auth/NewPasswordController.php` — reset-password
- `app/Http/Controllers/Auth/EmailVerificationPromptController.php` — verify-email notice
- `app/Http/Controllers/Auth/VerifyEmailController.php` — verify signed link
- `app/Http/Controllers/Auth/EmailVerificationNotificationController.php` — resend verification
- `app/Http/Controllers/DashboardController.php` — dashboard stub
- `app/Http/Requests/Auth/LoginRequest.php` — credentials + rate limiting

**New — Vue:**
- `resources/js/Layouts/GuestLayout.vue`
- `resources/js/Layouts/AuthenticatedLayout.vue`
- `resources/js/Pages/Auth/Login.vue`
- `resources/js/Pages/Auth/ForgotPassword.vue`
- `resources/js/Pages/Auth/ResetPassword.vue`
- `resources/js/Pages/Auth/VerifyEmail.vue`
- `resources/js/Pages/Dashboard.vue`

**New — Tests:**
- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Feature/Auth/PasswordResetTest.php`
- `tests/Feature/Auth/EmailVerificationTest.php`
- `tests/Feature/DashboardTest.php`

**Modified:**
- `app/Models/User.php` — implement `MustVerifyEmail`
- `app/Http/Middleware/HandleInertiaRequests.php` — share `auth.user.roles`
- `routes/web.php` — auth + dashboard routes
- `tests/Pest.php` — enable `RefreshDatabase` for Feature suite

---

### Task 1: Auth backbone (User contract, shared roles, test DB)

**Files:**
- Modify: `app/Models/User.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php:37-49`
- Modify: `tests/Pest.php`
- Test: `tests/Feature/Auth/AuthenticationTest.php` (created here, extended in Task 2)

**Interfaces:**
- Produces: shared Inertia prop `auth.user` = `{ id, name, email, roles: string[] }` where `roles` comes from `getRoleNames()`. `User` implements `Illuminate\Contracts\Auth\MustVerifyEmail`.

- [ ] **Step 1: Enable RefreshDatabase for the Feature suite**

In `tests/Pest.php`, uncomment/enable the trait so feature tests get a fresh migrated DB:

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Auth/AuthenticationTest.php`:

```php
<?php

use App\Models\User;

test('authenticated user has roles shared to inertia', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.email', $user->email)
            ->where('auth.user.roles', ['student'])
        );
});

test('user model requires email verification', function (): void {
    expect(new User)->toBeInstanceOf(Illuminate\Contracts\Auth\MustVerifyEmail::class);
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --compact --filter=AuthenticationTest`
Expected: FAIL — `auth.user.roles` missing / User is not a `MustVerifyEmail`.

- [ ] **Step 4: Implement — User implements MustVerifyEmail**

In `app/Models/User.php`: uncomment/add the import and add the contract to the class declaration.

```php
use Illuminate\Contracts\Auth\MustVerifyEmail;
```

```php
class User extends Authenticatable implements HasMedia, MustVerifyEmail
```

- [ ] **Step 5: Implement — share roles**

In `app/Http/Middleware/HandleInertiaRequests.php`, update the `auth.user` prop:

```php
'auth' => [
    'user' => $request->user()
        ? [
            ...$request->user()->only('id', 'name', 'email'),
            'roles' => $request->user()->getRoleNames()->all(),
        ]
        : null,
],
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --compact --filter=AuthenticationTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/User.php app/Http/Middleware/HandleInertiaRequests.php tests/Pest.php tests/Feature/Auth/AuthenticationTest.php
git commit -m "Add auth backbone: MustVerifyEmail contract and shared roles

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Core session loop (login, logout, dashboard, layouts)

**Files:**
- Create: `app/Http/Requests/Auth/LoginRequest.php`
- Create: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Create: `app/Http/Controllers/DashboardController.php`
- Create: `resources/js/Layouts/GuestLayout.vue`, `resources/js/Layouts/AuthenticatedLayout.vue`
- Create: `resources/js/Pages/Auth/Login.vue`, `resources/js/Pages/Dashboard.vue`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/DashboardTest.php`

**Interfaces:**
- Consumes: shared `auth.user` from Task 1.
- Produces: named routes `login` (GET/POST), `logout` (POST), `dashboard` (GET). `LoginRequest::authenticate(): void` and `LoginRequest::throttleKey(): string`. After login, redirect to `route('dashboard')`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Auth/AuthenticationTest.php`:

```php
use Illuminate\Support\Facades\RateLimiter;

test('login screen renders for guests', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

test('users can authenticate with valid credentials', function (): void {
    $user = User::factory()->student()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('users cannot authenticate with an invalid password', function (): void {
    $user = User::factory()->student()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login is rate limited after five failed attempts', function (): void {
    $user = User::factory()->student()->create();

    foreach (range(1, 5) as $ignored) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    expect(RateLimiter::tooManyAttempts(
        strtolower($user->email).'|127.0.0.1', 5
    ))->toBeTrue();
});

test('remember me sets the remember cookie', function (): void {
    $user = User::factory()->student()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ])->assertCookie(Auth::guard()->getRecallerName());

    $this->assertAuthenticatedAs($user);
});

test('authenticated users can log out', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});
```

Add the imports at the top of the file:

```php
use Illuminate\Support\Facades\Auth;
```

Create `tests/Feature/DashboardTest.php`:

```php
<?php

use App\Models\User;

test('guests are redirected to login from the dashboard', function (): void {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('authenticated users can view the dashboard', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard'));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter="AuthenticationTest|DashboardTest"`
Expected: FAIL — routes `login`/`dashboard` not defined.

- [ ] **Step 3: Create the LoginRequest**

Create `app/Http/Requests/Auth/LoginRequest.php`:

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
```

- [ ] **Step 4: Create the AuthenticatedSessionController**

Create `app/Http/Controllers/Auth/AuthenticatedSessionController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
```

- [ ] **Step 5: Create the DashboardController**

Create `app/Http/Controllers/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard');
    }
}
```

- [ ] **Step 6: Wire the routes**

Replace `routes/web.php` with:

```php
<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'name' => 'Charles',
    ]);
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
```

- [ ] **Step 7: Create GuestLayout.vue**

Create `resources/js/Layouts/GuestLayout.vue`:

```vue
<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-gray-50 px-4 text-gray-900">
        <Link href="/" class="mb-6 text-xl font-semibold">LMS</Link>

        <div class="w-full max-w-md rounded-lg bg-white p-8 shadow">
            <slot />
        </div>
    </div>
</template>
```

- [ ] **Step 8: Create AuthenticatedLayout.vue**

Create `resources/js/Layouts/AuthenticatedLayout.vue`:

```vue
<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const user = computed(() => usePage().props.auth.user);
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-900">
        <nav class="border-b bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <Link :href="route('dashboard')" class="font-semibold">LMS</Link>

                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-600">{{ user.name }}</span>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="text-sm text-red-600 hover:underline"
                    >
                        Log out
                    </Link>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-6xl px-4 py-8">
            <slot />
        </main>
    </div>
</template>
```

- [ ] **Step 9: Create Login.vue**

Create `resources/js/Pages/Auth/Login.vue`:

```vue
<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <h1 class="mb-6 text-lg font-semibold">Log in</h1>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    class="mt-1 block w-full rounded border-gray-300 shadow-sm"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    class="mt-1 block w-full rounded border-gray-300 shadow-sm"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.remember" type="checkbox" class="rounded border-gray-300" />
                Remember me
            </label>

            <div class="flex items-center justify-between">
                <Link :href="route('password.request')" class="text-sm text-blue-600 hover:underline">
                    Forgot password?
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Log in
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
```

Note: `route('password.request')` is created in Task 3. The `@` alias resolves to `resources/js` — confirm it works when Vite builds; if the alias is not configured, use a relative import `../../Layouts/GuestLayout.vue`.

- [ ] **Step 10: Create Dashboard.vue**

Create `resources/js/Pages/Dashboard.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const user = computed(() => usePage().props.auth.user);
const roles = computed(() => user.value.roles ?? []);
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Dashboard" />

        <h1 class="text-2xl font-semibold">Welcome back, {{ user.name }}</h1>
        <p class="mt-2 text-gray-600">
            You are signed in as
            <span class="font-medium capitalize">{{ roles.join(', ') || 'no role' }}</span>.
        </p>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 11: Run the tests to verify they pass**

Run: `php artisan test --compact --filter="AuthenticationTest|DashboardTest"`
Expected: PASS (all authentication + dashboard tests).

- [ ] **Step 12: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http routes/web.php resources/js tests/Feature
git commit -m "Add login, logout, and role-aware dashboard

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Password reset

**Files:**
- Create: `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- Create: `app/Http/Controllers/Auth/NewPasswordController.php`
- Create: `resources/js/Pages/Auth/ForgotPassword.vue`, `resources/js/Pages/Auth/ResetPassword.vue`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/PasswordResetTest.php`

**Interfaces:**
- Produces: named routes `password.request` (GET), `password.email` (POST), `password.reset` (GET), `password.store` (POST). Uses framework `Password` broker and `Notifications\Messages\MailMessage` reset notification.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Auth/PasswordResetTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('forgot password screen renders', function (): void {
    $this->get('/forgot-password')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
});

test('a reset link can be requested', function (): void {
    Notification::fake();
    $user = User::factory()->student()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('the reset password screen renders with a token', function (): void {
    Notification::fake();
    $user = User::factory()->student()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (object $notification) {
        $this->get('/reset-password/'.$notification->token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ResetPassword'));

        return true;
    });
});

test('the password can be reset with a valid token', function (): void {
    Notification::fake();
    $user = User::factory()->student()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'));

        return true;
    });
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=PasswordResetTest`
Expected: FAIL — `/forgot-password` not defined.

- [ ] **Step 3: Create PasswordResetLinkController**

Create `app/Http/Controllers/Auth/PasswordResetLinkController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
```

- [ ] **Step 4: Create NewPasswordController**

Create `app/Http/Controllers/Auth/NewPasswordController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request): void {
                $user->forceFill([
                    'password' => Hash::make($request->string('password')),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
```

- [ ] **Step 5: Add the routes**

In `routes/web.php`, inside the existing `Route::middleware('guest')->group(...)`, add:

```php
Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
```

Add the imports at the top:

```php
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
```

- [ ] **Step 6: Create ForgotPassword.vue**

Create `resources/js/Pages/Auth/ForgotPassword.vue`:

```vue
<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: String,
});

const form = useForm({ email: '' });

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot password" />

        <h1 class="mb-2 text-lg font-semibold">Forgot password</h1>
        <p class="mb-6 text-sm text-gray-600">
            Enter your email and we'll send you a password reset link.
        </p>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">{{ status }}</div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    class="mt-1 block w-full rounded border-gray-300 shadow-sm"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div class="flex items-center justify-between">
                <Link :href="route('login')" class="text-sm text-blue-600 hover:underline">Back to login</Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Email reset link
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
```

- [ ] **Step 7: Create ResetPassword.vue**

Create `resources/js/Pages/Auth/ResetPassword.vue`:

```vue
<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset password" />

        <h1 class="mb-6 text-lg font-semibold">Reset password</h1>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    class="mt-1 block w-full rounded border-gray-300 shadow-sm"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">New password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autofocus
                    class="mt-1 block w-full rounded border-gray-300 shadow-sm"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium">Confirm password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    class="mt-1 block w-full rounded border-gray-300 shadow-sm"
                />
            </div>

            <div class="flex justify-end">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Reset password
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=PasswordResetTest`
Expected: PASS (4 tests).

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Auth routes/web.php resources/js/Pages/Auth tests/Feature/Auth/PasswordResetTest.php
git commit -m "Add password reset flow

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Email verification

**Files:**
- Create: `app/Http/Controllers/Auth/EmailVerificationPromptController.php`
- Create: `app/Http/Controllers/Auth/VerifyEmailController.php`
- Create: `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`
- Create: `resources/js/Pages/Auth/VerifyEmail.vue`
- Modify: `routes/web.php` (add verification routes; add `verified` middleware to `dashboard`)
- Test: `tests/Feature/Auth/EmailVerificationTest.php`

**Interfaces:**
- Consumes: `User implements MustVerifyEmail` (Task 1). `dashboard` route (Task 2).
- Produces: named routes `verification.notice` (GET), `verification.verify` (GET, signed), `verification.send` (POST). `dashboard` gains the `verified` middleware.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Auth/EmailVerificationTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

test('the verification prompt renders for unverified users', function (): void {
    $user = User::factory()->student()->unverified()->create();

    $this->actingAs($user)
        ->get('/verify-email')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/VerifyEmail'));
});

test('verified users are redirected from the prompt to the dashboard', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->get('/verify-email')
        ->assertRedirect(route('dashboard'));
});

test('email can be verified with a valid signed link', function (): void {
    Event::fake();
    $user = User::factory()->student()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($verificationUrl)->assertRedirect(route('dashboard').'?verified=1');

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('email is not verified with an invalid hash', function (): void {
    $user = User::factory()->student()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('unverified users are redirected from the dashboard to the verification notice', function (): void {
    $user = User::factory()->student()->unverified()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('verification.notice'));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter=EmailVerificationTest`
Expected: FAIL — `/verify-email` not defined.

- [ ] **Step 3: Create EmailVerificationPromptController**

Create `app/Http/Controllers/Auth/EmailVerificationPromptController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        return Inertia::render('Auth/VerifyEmail', [
            'status' => session('status'),
        ]);
    }
}
```

- [ ] **Step 4: Create VerifyEmailController**

Create `app/Http/Controllers/Auth/VerifyEmailController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard').'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(route('dashboard').'?verified=1');
    }
}
```

- [ ] **Step 5: Create EmailVerificationNotificationController**

Create `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
```

- [ ] **Step 6: Add routes and the `verified` middleware**

In `routes/web.php`, add the imports:

```php
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\VerifyEmailController;
```

Inside the `Route::middleware('auth')->group(...)`, add the verification routes and add `verified` to the dashboard route:

```php
Route::get('verify-email', EmailVerificationPromptController::class)->name('verification.notice');

Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('verification.send');
```

Change the dashboard route line to:

```php
Route::get('dashboard', [DashboardController::class, 'index'])->middleware('verified')->name('dashboard');
```

- [ ] **Step 7: Create VerifyEmail.vue**

Create `resources/js/Pages/Auth/VerifyEmail.vue`:

```vue
<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    status: String,
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <GuestLayout>
        <Head title="Verify email" />

        <h1 class="mb-2 text-lg font-semibold">Verify your email</h1>
        <p class="mb-6 text-sm text-gray-600">
            Before continuing, please verify your email by clicking the link we just sent you.
            If you didn't receive it, we'll gladly send another.
        </p>

        <div v-if="verificationLinkSent" class="mb-4 text-sm font-medium text-green-600">
            A new verification link has been sent to your email address.
        </div>

        <div class="flex items-center justify-between">
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="text-sm text-red-600 hover:underline"
            >
                Log out
            </Link>
            <button
                type="button"
                :disabled="form.processing"
                class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                @click="submit"
            >
                Resend verification email
            </button>
        </div>
    </GuestLayout>
</template>
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=EmailVerificationTest`
Expected: PASS (5 tests).

- [ ] **Step 9: Run the whole suite**

Run: `php artisan test --compact`
Expected: PASS — all auth, dashboard, and the pre-existing example tests.

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Auth routes/web.php resources/js/Pages/Auth/VerifyEmail.vue tests/Feature/Auth/EmailVerificationTest.php
git commit -m "Add email verification flow

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: Build assets and manual smoke check

**Files:** none (verification only)

- [ ] **Step 1: Build the frontend**

Run: `npm run build`
Expected: build succeeds, all new Vue pages compile with no unresolved imports. If the `@` alias fails to resolve, add it to `vite.config.js` `resolve.alias` (`'@': fileURLToPath(new URL('./resources/js', import.meta.url))`) or switch the page imports to relative paths, then rebuild.

- [ ] **Step 2: Full test suite green**

Run: `php artisan test --compact`
Expected: PASS.

- [ ] **Step 3: Commit any alias/config fix**

```bash
git add vite.config.js
git commit -m "Configure @ alias for resources/js

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

(Skip if no change was needed.)

---

## Self-Review Notes

- **Spec coverage:** login/logout (Task 2), password reset (Task 3), email verification (Task 4), remember-me + rate limiting (Task 2 `LoginRequest`), role-aware dashboard + authenticated layout (Task 2), shared roles + `MustVerifyEmail` (Task 1), tests throughout. Seeder change from the spec was **dropped** — `UserFactory` already defaults `email_verified_at` to `now()`, so seeded users are verified; no change required.
- **`@` alias risk:** flagged in Task 2 Step 9 and Task 5 Step 1 with a concrete fallback.
- **Route ordering dependency:** `password.request` is referenced by `Login.vue` (Task 2) but defined in Task 3. This only matters at click-time, not build-time — Ziggy resolves routes at runtime and the reset link isn't exercised until Task 3. Acceptable; noted here.
- **`verified` middleware** is deliberately added to `dashboard` only in Task 4, after the `verification.notice` route exists, to avoid a broken intermediate redirect target.
