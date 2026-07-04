# User Management (CRUD) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let admins manage all users (any role) and let instructors provision + manage the student accounts they create, with accounts activated by email invitation.

**Architecture:** Thin `UserManagementController` authorizes via an extended `UserPolicy` and delegates creation to an `InviteUser` action. New users are created with a random password and `email_verified_at = null`; a `UserInvitation` notification carries a password-broker token to a dedicated guest accept page that sets the password, verifies the email, and logs the user in. Soft-deletes disable login and route binding automatically. Vue/Inertia pages mirror the existing Courses CRUD pages.

**Tech Stack:** Laravel 13, Inertia v3 + Vue 3, Spatie Permission, Laravel password broker, Pest 4, Tailwind v4, lucide-vue-next.

## Global Constraints

- Naming: variables `snake_case`, methods/functions `camelCase`, classes `TitleCase` (per user global standards).
- PHP: curly braces on all control structures; explicit return types + param type hints; constructor property promotion; PHPDoc over inline comments.
- Thin controllers authorize via policy + delegate to Actions; validation in FormRequests; policies auto-discovered.
- Admins bypass every ability via `Gate::before` (see `AppServiceProvider`) — policy methods encode only the instructor rules; anything that must apply to admins too (self-delete block) is a controller guard.
- App features live behind `auth` + `verified` middleware; invitation-accept routes live behind `guest`.
- Strong-password rule is `Password::defaults()` (min 8, mixed case, number, symbol) — already configured in `AppServiceProvider`.
- Tests run on MariaDB with `DatabaseTruncation` (configured in `tests/Pest.php`). Every test file that touches roles/permissions MUST `beforeEach(fn () => $this->seed(RolePermissionSeeder::class))` — `DatabaseTruncation` does not seed.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes, before each commit.
- Run tests with `php artisan test --compact --filter=...`.

---

### Task 1: Users table migration + model foundation

**Files:**
- Create: `database/migrations/2026_07_04_020000_add_management_columns_to_users_table.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Users/UserModelTest.php`

**Interfaces:**
- Produces:
  - `users.deleted_at` (SoftDeletes) and `users.created_by` (nullable self-FK).
  - `User::creator(): BelongsTo` and `User::createdUsers(): HasMany`.
  - `User` uses `SoftDeletes` + `Searchable`; `scopeWithSearch(?string $term)` searches `first_name`, `last_name`, `email`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Users/UserModelTest.php`:

```php
<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('soft deletes users and hides them from default queries', function () {
    $user = User::factory()->student()->create();

    $user->delete();

    expect(User::find($user->id))->toBeNull();
    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});

it('records who created a user through the creator relationship', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create(['created_by' => $admin->id]);

    expect($student->creator->is($admin))->toBeTrue();
    expect($admin->createdUsers->pluck('id')->all())->toContain($student->id);
});

it('finds users by name and email through the search scope', function () {
    $match = User::factory()->create(['first_name' => 'Zoltan', 'email' => 'zoltan@example.com']);
    User::factory()->create(['first_name' => 'Someone', 'email' => 'someone@example.com']);

    $ids = User::withSearch('zoltan')->pluck('id')->all();

    expect($ids)->toContain($match->id)->toHaveCount(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=UserModelTest`
Expected: FAIL (`created_by` column missing / `withSearch` undefined).

- [ ] **Step 3: Create the migration**

`database/migrations/2026_07_04_020000_add_management_columns_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->after('id')
                ->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropSoftDeletes();
        });
    }
};
```

- [ ] **Step 4: Update the `User` model**

In `app/Models/User.php`, add imports near the other `Illuminate\Database\Eloquent` imports:

```php
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
```

Add `Searchable` and `SoftDeletes` to the trait `use` list (keep alphabetical grouping consistent with the file):

```php
    use CausesActivity, HasFactory, HasRoles, InteractsWithMedia, LogsActivity, Notifiable, Searchable, SoftDeletes;
```

Add these methods to the class body (place after `getActivitylogOptions()`):

```php
    /**
     * Fields searched with LIKE on the management user list.
     *
     * @return list<string>
     */
    protected function searchableFields(): array
    {
        return ['first_name', 'last_name', 'email'];
    }

    /**
     * The user who provisioned this account (null for seeded accounts).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Accounts this user has provisioned.
     */
    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }
```

- [ ] **Step 5: Run migration + test to verify it passes**

Run: `php artisan migrate && php artisan test --compact --filter=UserModelTest`
Expected: PASS (3 passing).

- [ ] **Step 6: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/User.php database/migrations/2026_07_04_020000_add_management_columns_to_users_table.php tests/Feature/Users/UserModelTest.php
git commit -m "feat: add soft-deletes, created_by, and search to users"
```

---

### Task 2: Authorization — extend `UserPolicy` + share `manage_users`

**Files:**
- Modify: `app/Policies/UserPolicy.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/Users/UserPolicyTest.php`

**Interfaces:**
- Consumes: `UserRole` enum; `User::$created_by`.
- Produces:
  - `UserPolicy::viewAny(User): bool`, `create(User): bool`, `manage(User, User): bool`, `delete(User, User): bool`.
  - Shared Inertia prop `auth.user.can.manage_users: bool`.
  - Existing own-only `UserPolicy::update(User, User)` is left unchanged.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Users/UserPolicyTest.php`:

```php
<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets admins manage anyone and view the list', function () {
    $admin = User::factory()->admin()->create();
    $someone = User::factory()->student()->create();

    expect($admin->can('viewAny', User::class))->toBeTrue();
    expect($admin->can('manage', $someone))->toBeTrue();
});

it('lets instructors manage only the students they created', function () {
    $instructor = User::factory()->instructor()->create();
    $ownStudent = User::factory()->student()->create(['created_by' => $instructor->id]);
    $otherStudent = User::factory()->student()->create();
    $otherInstructor = User::factory()->instructor()->create(['created_by' => $instructor->id]);

    expect($instructor->can('viewAny', User::class))->toBeTrue();
    expect($instructor->can('create', User::class))->toBeTrue();
    expect($instructor->can('manage', $ownStudent))->toBeTrue();
    expect($instructor->can('manage', $otherStudent))->toBeFalse();
    expect($instructor->can('manage', $otherInstructor))->toBeFalse();
    expect($instructor->can('delete', $ownStudent))->toBeTrue();
});

it('denies students any management ability', function () {
    $student = User::factory()->student()->create();

    expect($student->can('viewAny', User::class))->toBeFalse();
    expect($student->can('create', User::class))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=UserPolicyTest`
Expected: FAIL (`manage`/`viewAny` methods missing → abilities deny by default or error).

- [ ] **Step 3: Extend `UserPolicy`**

Replace `app/Policies/UserPolicy.php` with:

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user may view the management user list.
     *
     * Admins pass via Gate::before; instructors get a list scoped to their
     * own students in the controller query.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Instructor->value);
    }

    /**
     * Determine whether the user may provision new accounts.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Instructor->value);
    }

    /**
     * Determine whether the user may edit/administer the target account.
     *
     * Instructors may only manage students they personally created.
     */
    public function manage(User $user, User $target): bool
    {
        return $target->hasRole(UserRole::Student->value)
            && (int) $target->created_by === $user->id;
    }

    /**
     * Determine whether the user may remove the target account.
     *
     * The "cannot delete yourself" rule is also enforced in the controller so
     * it applies to admins (who bypass this policy via Gate::before).
     */
    public function delete(User $user, User $target): bool
    {
        return ! $user->is($target) && $this->manage($user, $target);
    }

    /**
     * Determine whether the user may edit the given profile.
     *
     * A user may only manage their own profile (admins are granted access
     * globally via Gate::before).
     */
    public function update(User $user, User $model): bool
    {
        return $user->is($model);
    }
}
```

- [ ] **Step 4: Share the `manage_users` ability**

In `app/Http/Middleware/HandleInertiaRequests.php`, add the import:

```php
use App\Models\User;
```

Add one line to the `'can'` array (alongside `create_courses`):

```php
                        'can' => [
                            'create_courses' => $request->user()->can('create courses'),
                            'manage_users' => $request->user()->can('viewAny', User::class),
                        ],
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=UserPolicyTest`
Expected: PASS (3 passing).

- [ ] **Step 6: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/UserPolicy.php app/Http/Middleware/HandleInertiaRequests.php tests/Feature/Users/UserPolicyTest.php
git commit -m "feat: add user-management abilities to UserPolicy"
```

---

### Task 3: Invitation infrastructure — `InviteUser` action + `UserInvitation` notification

**Files:**
- Create: `app/Notifications/UserInvitation.php`
- Create: `app/Actions/InviteUser.php`
- Test: `tests/Feature/Users/InviteUserTest.php`

**Interfaces:**
- Consumes: `UserRole` enum; password broker; `User`.
- Produces:
  - `UserInvitation(string $token)` mailable notification linking to `route('invitation.create', ...)`.
  - `InviteUser::run(array{first_name:string,last_name:string,email:string} $attributes, UserRole $role, User $creator): User` — creates an unverified user with a random password + `created_by`, assigns the role, and sends `UserInvitation`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Users/InviteUserTest.php`:

```php
<?php

use App\Actions\InviteUser;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\UserInvitation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('provisions an unverified user, assigns the role, and sends an invitation', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    $user = InviteUser::run([
        'first_name' => 'New',
        'last_name' => 'Teacher',
        'email' => 'new.teacher@example.com',
    ], UserRole::Instructor, $admin);

    expect($user->created_by)->toBe($admin->id);
    expect($user->email_verified_at)->toBeNull();
    expect($user->hasRole(UserRole::Instructor->value))->toBeTrue();

    Notification::assertSentTo($user, UserInvitation::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=InviteUserTest`
Expected: FAIL (`App\Actions\InviteUser` not found).

- [ ] **Step 3: Create the notification**

`app/Notifications/UserInvitation.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification
{
    use Queueable;

    /**
     * @param  string  $token  Password-broker token used to accept the invite.
     */
    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('invitation.create', ['token' => $this->token])
            .'?email='.urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('You have been invited to the LMS')
            ->line('An account has been created for you.')
            ->action('Set your password', $url)
            ->line('If you did not expect this invitation, you can ignore this email.');
    }
}
```

- [ ] **Step 4: Create the action**

`app/Actions/InviteUser.php`:

```php
<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class InviteUser
{
    use AsAction;

    /**
     * Provision a new, unverified user and email them an invitation to set
     * their password. The 'hashed' cast hashes the placeholder password.
     *
     * @param  array{first_name: string, last_name: string, email: string}  $attributes
     */
    public function handle(array $attributes, UserRole $role, User $creator): User
    {
        $user = new User;
        $user->first_name = $attributes['first_name'];
        $user->last_name = $attributes['last_name'];
        $user->email = $attributes['email'];
        $user->password = Str::password(32);
        $user->created_by = $creator->id;
        $user->save();

        $user->assignRole($role->value);

        $user->notify(new UserInvitation(Password::createToken($user)));

        return $user;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=InviteUserTest`
Expected: PASS (1 passing).

- [ ] **Step 6: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/UserInvitation.php app/Actions/InviteUser.php tests/Feature/Users/InviteUserTest.php
git commit -m "feat: add InviteUser action and UserInvitation notification"
```

---

### Task 4: Invitation accept flow (guest)

**Files:**
- Create: `app/Http/Controllers/Auth/InvitationController.php`
- Create: `resources/js/Pages/Invitations/Accept.vue`
- Modify: `routes/web.php`
- Test: `tests/Feature/Users/AcceptInvitationTest.php`

**Interfaces:**
- Consumes: `UserInvitation` token (via `Password::createToken`); password broker.
- Produces:
  - `GET invitation/{token}` → `invitation.create` (renders `Invitations/Accept`).
  - `POST invitation` → `invitation.store` (sets password, verifies email, logs in, redirects `dashboard`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Users/AcceptInvitationTest.php`:

```php
<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Password;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('accepts an invitation, sets the password, verifies email, and logs in', function () {
    $user = User::factory()->student()->unverified()->create();
    $token = Password::createToken($user);

    $response = $this->post(route('invitation.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Str0ng-pass!',
        'password_confirmation' => 'Str0ng-pass!',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user->fresh());
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('rejects an invalid invitation token', function () {
    $user = User::factory()->student()->unverified()->create();

    $this->post(route('invitation.store'), [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'Str0ng-pass!',
        'password_confirmation' => 'Str0ng-pass!',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AcceptInvitationTest`
Expected: FAIL (route `invitation.store` not defined).

- [ ] **Step 3: Create the controller**

`app/Http/Controllers/Auth/InvitationController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /**
     * Show the set-password form for an invited user.
     */
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Invitations/Accept', [
            'email' => $request->query('email'),
            'token' => $token,
        ]);
    }

    /**
     * Set the invited user's password, mark them verified, and log them in.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $accepted = null;

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request, &$accepted): void {
                $user->forceFill([
                    'password' => Hash::make($request->string('password')),
                    'email_verified_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                $accepted = $user;
            }
        );

        if ($status === Password::PASSWORD_RESET && $accepted !== null) {
            Auth::login($accepted);

            return redirect()->route('dashboard')->with('status', 'Welcome! Your account is ready.');
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
```

- [ ] **Step 4: Register the guest routes**

In `routes/web.php`, add the import near the other `Auth` controller imports:

```php
use App\Http\Controllers\Auth\InvitationController;
```

Inside the existing `Route::middleware('guest')->group(...)` block, add:

```php
    Route::get('invitation/{token}', [InvitationController::class, 'create'])->name('invitation.create');
    Route::post('invitation', [InvitationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('invitation.store');
```

- [ ] **Step 5: Create the Accept page**

`resources/js/Pages/Invitations/Accept.vue`:

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
    form.post(route('invitation.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Set your password" />

        <h1 class="mb-2 text-lg font-semibold">Set your password</h1>
        <p class="mb-6 text-sm text-muted-foreground">Choose a password to activate your account.</p>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    readonly
                    class="mt-1 block w-full rounded border-gray-300 bg-gray-50 shadow-sm"
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
                    Activate account
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=AcceptInvitationTest`
Expected: PASS (2 passing).

- [ ] **Step 7: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Auth/InvitationController.php resources/js/Pages/Invitations/Accept.vue routes/web.php tests/Feature/Users/AcceptInvitationTest.php
git commit -m "feat: add invitation accept flow"
```

---

### Task 5: User list (index) + resource + nav

**Files:**
- Create: `app/Http/Controllers/UserManagementController.php`
- Create: `app/Http/Resources/UserManagementResource.php`
- Create: `resources/js/Pages/Users/Index.vue`
- Modify: `routes/web.php`
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`
- Modify: `resources/js/composables/useSectionTheme.js`
- Test: `tests/Feature/Users/UserIndexTest.php`

**Interfaces:**
- Consumes: `UserPolicy::viewAny`; `User::scopeWithSearch`.
- Produces:
  - `UserManagementController::index(Request): Response`, plus private `roleOptions(User): array` and `const PER_PAGE = 20` (reused by later tasks).
  - `UserManagementResource` → `array{id,name,first_name,last_name,email,role,status,avatar_thumb,created_at}`.
  - `GET users` → `users.index`.
  - Nav "Users" item + `users` section theme.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Users/UserIndexTest.php`:

```php
<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('shows every user to an admin', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->instructor()->create();
    User::factory()->student()->create();

    $this->actingAs($admin)->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->where('users.total', 3));
});

it('shows an instructor only the students they created', function () {
    $instructor = User::factory()->instructor()->create();
    $mine = User::factory()->student()->create(['created_by' => $instructor->id]);
    User::factory()->student()->create();

    $this->actingAs($instructor)->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)
            ->where('users.data.0.id', $mine->id));
});

it('forbids students from the user list', function () {
    $student = User::factory()->student()->create();

    $this->actingAs($student)->get(route('users.index'))->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=UserIndexTest`
Expected: FAIL (route `users.index` not defined).

- [ ] **Step 3: Create the resource**

`app/Http/Resources/UserManagementResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserManagementResource extends JsonResource
{
    /**
     * User shape for the management list and edit form (includes email/status).
     *
     * @return array{id: int, name: string, first_name: string, last_name: string, email: string, role: string, status: string, avatar_thumb: ?string, created_at: ?string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'role' => $this->getRoleNames()->first() ?? 'Member',
            'status' => $this->email_verified_at ? 'Active' : 'Invited',
            'avatar_thumb' => $this->avatar_thumb_url,
            'created_at' => $this->created_at?->toDateString(),
        ];
    }
}
```

- [ ] **Step 4: Create the controller with `index`**

`app/Http/Controllers/UserManagementController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Resources\UserManagementResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    /**
     * Users shown per management list page.
     */
    private const PER_PAGE = 20;

    /**
     * List users. Admins see everyone; instructors see only their own students.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $viewer = $request->user();

        $users = User::query()
            ->when(
                ! $viewer->hasRole(UserRole::Admin->value),
                fn ($query) => $query->where('created_by', $viewer->id),
            )
            ->with('roles', 'media')
            ->withSearch($request->query('search'))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user): array => UserManagementResource::make($user)->resolve($request));

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['search' => $request->query('search')],
        ]);
    }

    /**
     * Role options the given actor is allowed to assign.
     *
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(User $actor): array
    {
        $roles = $actor->hasRole(UserRole::Admin->value) ? UserRole::cases() : [UserRole::Student];

        return array_map(
            fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->value],
            $roles,
        );
    }
}
```

- [ ] **Step 5: Register the index route**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\UserManagementController;
```

Inside the `Route::middleware('verified')->group(...)` block, immediately **above** the existing `Route::get('users/{user}', [UserProfileController::class, 'show'])` line, add:

```php
        Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
```

- [ ] **Step 6: Add the `users` section theme**

In `resources/js/composables/useSectionTheme.js`, add a `users` entry to the `THEMES` object (after `manage`):

```js
    users: {
        key: 'users',
        label: 'Users',
        text: 'text-rose-600',
        solid: 'bg-rose-600 text-white hover:bg-rose-700',
        soft: 'bg-rose-500/10 text-rose-700',
        ring: 'focus-visible:ring-rose-500',
        accent: 'bg-rose-500',
        gradient: 'from-rose-500 to-pink-500',
        icon: 'bg-rose-500/15 text-rose-600',
        hoverBorder: 'hover:border-rose-300',
    },
```

In the same file, in `sectionForPath`, add this branch **above** the `/courses` branch:

```js
    if (pathname.startsWith('/users')) {
        return 'users';
    }
```

- [ ] **Step 7: Add the nav link**

In `resources/js/Layouts/AuthenticatedLayout.vue`:

Add `UsersRound` to the lucide import:

```js
import { LayoutDashboard, Compass, GraduationCap, BookMarked, LogOut, ChevronDown, UserRound, Mail, UsersRound } from 'lucide-vue-next';
```

Add a computed after `canCreateCourses`:

```js
const canManageUsers = computed(() => user.value.can?.manage_users ?? false);
```

In `navItems`, add before the `return items;`:

```js
    if (canManageUsers.value) {
        items.push({ label: 'Users', routeName: 'users.index', icon: UsersRound, key: 'users' });
    }
```

- [ ] **Step 8: Create the Index page**

`resources/js/Pages/Users/Index.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Head, Link } from '@inertiajs/vue3';
import { Plus, UsersRound } from 'lucide-vue-next';

defineProps({
    users: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: '' }),
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Users" />

        <PageHeader title="Users" subtitle="Create and manage instructor and student accounts.">
            <template #actions>
                <Button as-child class="bg-rose-600 text-white hover:bg-rose-700">
                    <Link :href="route('users.create')">
                        <Plus class="size-4" />
                        New user
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mb-4">
            <SearchInput :initial="filters.search ?? ''" placeholder="Search users…" />
        </div>

        <div
            v-if="users.total === 0"
            class="rounded-2xl border border-dashed bg-card p-12 text-center"
        >
            <div class="mx-auto flex size-12 items-center justify-center rounded-xl bg-rose-500/15 text-rose-600">
                <UsersRound class="size-6" />
            </div>
            <p class="mt-4 font-medium text-foreground">No users yet</p>
            <p class="mt-1 text-sm text-muted-foreground">Create your first user to get started.</p>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow class="bg-muted/40 hover:bg-muted/40">
                        <TableHead>Name</TableHead>
                        <TableHead class="w-56">Email</TableHead>
                        <TableHead class="w-32">Role</TableHead>
                        <TableHead class="w-28">Status</TableHead>
                        <TableHead class="w-20 text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="row in users.data" :key="row.id">
                        <TableCell>
                            <div class="flex items-center gap-2.5">
                                <UserAvatar :user="row" class="size-8" />
                                <span class="font-semibold text-foreground">{{ row.name }}</span>
                            </div>
                        </TableCell>
                        <TableCell class="text-muted-foreground">{{ row.email }}</TableCell>
                        <TableCell>{{ row.role }}</TableCell>
                        <TableCell>
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="row.status === 'Active' ? 'bg-emerald-500/10 text-emerald-700' : 'bg-amber-500/10 text-amber-700'"
                            >
                                {{ row.status }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right">
                            <Button as-child variant="ghost" size="sm">
                                <Link :href="route('users.edit', row.id)">Edit</Link>
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :paginator="users" />
    </AuthenticatedLayout>
</template>
```

> Note: the `New user` button and `Edit` link target routes added in Tasks 6–7. They render now; the pages arrive in those tasks.

- [ ] **Step 9: Run test + build to verify**

Run: `php artisan test --compact --filter=UserIndexTest`
Expected: PASS (3 passing).
Run: `npm run build`
Expected: builds without errors.

- [ ] **Step 10: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/UserManagementController.php app/Http/Resources/UserManagementResource.php resources/js/Pages/Users/Index.vue routes/web.php resources/js/Layouts/AuthenticatedLayout.vue resources/js/composables/useSectionTheme.js tests/Feature/Users/UserIndexTest.php
git commit -m "feat: add user management list page and nav"
```

---

### Task 6: Create user (invite) endpoint + form

**Files:**
- Create: `app/Http/Requests/User/StoreUserRequest.php`
- Create: `resources/js/Pages/Users/Create.vue`
- Modify: `app/Http/Controllers/UserManagementController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Users/CreateUserTest.php`

**Interfaces:**
- Consumes: `UserPolicy::create`; `InviteUser::run`; `roleOptions`.
- Produces:
  - `GET users/create` → `users.create`; `POST users` → `users.store`.
  - `StoreUserRequest` (role restricted to Student for non-admins).
  - Profile `users/{user}` show/update routes constrained with `->whereNumber('user')`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Users/CreateUserTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\UserInvitation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets an admin invite an instructor', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('users.store'), [
        'first_name' => 'Ivy',
        'last_name' => 'Instructor',
        'email' => 'ivy@example.com',
        'role' => UserRole::Instructor->value,
    ])->assertRedirect(route('users.index'));

    $user = User::where('email', 'ivy@example.com')->firstOrFail();
    expect($user->hasRole(UserRole::Instructor->value))->toBeTrue();
    expect($user->created_by)->toBe($admin->id);
    Notification::assertSentTo($user, UserInvitation::class);
});

it('forces the role to student when an instructor creates a user', function () {
    Notification::fake();
    $instructor = User::factory()->instructor()->create();

    $this->actingAs($instructor)->post(route('users.store'), [
        'first_name' => 'Sam',
        'last_name' => 'Student',
        'email' => 'sam@example.com',
        'role' => UserRole::Instructor->value,
    ])->assertSessionHasErrors('role');

    expect(User::where('email', 'sam@example.com')->exists())->toBeFalse();
});

it('lets an instructor invite a student', function () {
    Notification::fake();
    $instructor = User::factory()->instructor()->create();

    $this->actingAs($instructor)->post(route('users.store'), [
        'first_name' => 'Sam',
        'last_name' => 'Student',
        'email' => 'sam@example.com',
        'role' => UserRole::Student->value,
    ])->assertRedirect(route('users.index'));

    $user = User::where('email', 'sam@example.com')->firstOrFail();
    expect($user->hasRole(UserRole::Student->value))->toBeTrue();
    expect($user->created_by)->toBe($instructor->id);
});

it('forbids a student from creating users', function () {
    $student = User::factory()->student()->create();

    $this->actingAs($student)->post(route('users.store'), [
        'first_name' => 'No',
        'last_name' => 'Way',
        'email' => 'no@example.com',
        'role' => UserRole::Student->value,
    ])->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CreateUserTest`
Expected: FAIL (route `users.store` not defined).

- [ ] **Step 3: Create the FormRequest**

`app/Http/Requests/User/StoreUserRequest.php`:

```php
<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Only users who may provision accounts can create.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in($this->allowedRoles())],
        ];
    }

    /**
     * Roles the current actor may assign (admins: any; others: student only).
     *
     * @return list<string>
     */
    private function allowedRoles(): array
    {
        if ($this->user()->hasRole(UserRole::Admin->value)) {
            return array_map(fn (UserRole $role): string => $role->value, UserRole::cases());
        }

        return [UserRole::Student->value];
    }
}
```

- [ ] **Step 4: Add `create` + `store` to the controller**

Add these imports to `app/Http/Controllers/UserManagementController.php`:

```php
use App\Actions\InviteUser;
use App\Http\Requests\User\StoreUserRequest;
use Illuminate\Http\RedirectResponse;
```

Add these methods (after `index`):

```php
    /**
     * Show the new-user form.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'roleOptions' => $this->roleOptions($request->user()),
        ]);
    }

    /**
     * Provision a user and send them an invitation.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        InviteUser::run(
            $request->safe()->only('first_name', 'last_name', 'email'),
            $request->enum('role', UserRole::class),
            $request->user(),
        );

        return redirect()->route('users.index')->with('status', 'Invitation sent.');
    }
```

- [ ] **Step 5: Register routes + constrain profile binding**

In `routes/web.php`, add below the `users.index` route (and above the profile `users/{user}` line):

```php
        Route::get('users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
```

Then add `->whereNumber('user')` to the two bare profile routes so `users/create` resolves correctly:

```php
        Route::get('users/{user}', [UserProfileController::class, 'show'])->name('users.show')->whereNumber('user');
        Route::patch('users/{user}', [UserProfileController::class, 'update'])->name('users.update')->whereNumber('user');
```

- [ ] **Step 6: Create the Create page**

`resources/js/Pages/Users/Create.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    roleOptions: {
        type: Array,
        required: true,
    },
});

const canChooseRole = computed(() => props.roleOptions.length > 1);

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    role: props.roleOptions[0]?.value ?? '',
});

const submit = () => {
    form.post(route('users.store'));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="New user" />

        <h1 class="mb-6 text-2xl font-semibold">New user</h1>

        <form class="max-w-2xl" @submit.prevent="submit">
            <Card>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="first_name" class="block text-sm font-medium">First name</label>
                            <Input id="first_name" v-model="form.first_name" class="mt-1" />
                            <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-600">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium">Last name</label>
                            <Input id="last_name" v-model="form.last_name" class="mt-1" />
                            <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-600">{{ form.errors.last_name }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium">Email</label>
                        <Input id="email" v-model="form.email" type="email" class="mt-1" />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div v-if="canChooseRole">
                        <label for="role" class="block text-sm font-medium">Role</label>
                        <select
                            id="role"
                            v-model="form.role"
                            class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm"
                        >
                            <option v-for="option in roleOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.role" class="mt-1 text-sm text-red-600">{{ form.errors.role }}</p>
                    </div>

                    <p class="text-sm text-muted-foreground">
                        The user will receive an email invitation to set their own password.
                    </p>
                </CardContent>
            </Card>

            <div class="mt-6 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Send invitation</Button>
                <Button as-child variant="ghost">
                    <Link :href="route('users.index')">Cancel</Link>
                </Button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 7: Run test + build to verify**

Run: `php artisan test --compact --filter=CreateUserTest`
Expected: PASS (4 passing).
Run: `npm run build`
Expected: builds without errors.

- [ ] **Step 8: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/User/StoreUserRequest.php app/Http/Controllers/UserManagementController.php resources/js/Pages/Users/Create.vue routes/web.php tests/Feature/Users/CreateUserTest.php
git commit -m "feat: add create-user (invite) endpoint and form"
```

---

### Task 7: Edit + update user endpoint + form

**Files:**
- Create: `app/Http/Requests/User/UpdateUserRequest.php`
- Create: `resources/js/Pages/Users/Edit.vue`
- Modify: `app/Http/Controllers/UserManagementController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Users/UpdateUserTest.php`

**Interfaces:**
- Consumes: `UserPolicy::manage`; `roleOptions`.
- Produces:
  - `GET users/{user}/edit` → `users.edit`; `PUT users/{user}` → `users.management.update`.
  - `UpdateUserRequest` (role validated + applied only for admins).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Users/UpdateUserTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets an admin update a user name, email, and role', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->student()->create();

    $this->actingAs($admin)->put(route('users.management.update', $user), [
        'first_name' => 'Renamed',
        'last_name' => 'Person',
        'email' => 'renamed@example.com',
        'role' => UserRole::Instructor->value,
    ])->assertRedirect(route('users.index'));

    $user->refresh();
    expect($user->first_name)->toBe('Renamed');
    expect($user->email)->toBe('renamed@example.com');
    expect($user->hasRole(UserRole::Instructor->value))->toBeTrue();
});

it('lets an instructor edit their own student but not change the role', function () {
    $instructor = User::factory()->instructor()->create();
    $student = User::factory()->student()->create(['created_by' => $instructor->id]);

    $this->actingAs($instructor)->put(route('users.management.update', $student), [
        'first_name' => 'Edited',
        'last_name' => 'Student',
        'email' => $student->email,
        'role' => UserRole::Instructor->value,
    ])->assertRedirect(route('users.index'));

    $student->refresh();
    expect($student->first_name)->toBe('Edited');
    expect($student->hasRole(UserRole::Student->value))->toBeTrue();
});

it('forbids an instructor from editing another instructors student', function () {
    $instructor = User::factory()->instructor()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($instructor)->put(route('users.management.update', $student), [
        'first_name' => 'Nope',
        'last_name' => 'Student',
        'email' => $student->email,
    ])->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=UpdateUserTest`
Expected: FAIL (route `users.management.update` not defined).

- [ ] **Step 3: Create the FormRequest**

`app/Http/Requests/User/UpdateUserRequest.php`:

```php
<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Only a manager of the target account may update it.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('user'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ];

        if ($this->user()->hasRole(UserRole::Admin->value)) {
            $rules['role'] = ['required', Rule::in(array_map(
                fn (UserRole $role): string => $role->value,
                UserRole::cases(),
            ))];
        }

        return $rules;
    }
}
```

- [ ] **Step 4: Add `edit` + `update` to the controller**

Add imports to `app/Http/Controllers/UserManagementController.php`:

```php
use App\Http\Requests\User\UpdateUserRequest;
```

Add these methods (after `store`):

```php
    /**
     * Show the edit form for a managed user.
     */
    public function edit(Request $request, User $user): Response
    {
        $this->authorize('manage', $user);

        return Inertia::render('Users/Edit', [
            'user' => UserManagementResource::make($user->load('roles', 'media'))->resolve($request),
            'roleOptions' => $this->roleOptions($request->user()),
            'canEditRole' => $request->user()->hasRole(UserRole::Admin->value),
        ]);
    }

    /**
     * Update a managed user. Only admins may change roles.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->safe()->only('first_name', 'last_name', 'email'));

        if ($request->user()->hasRole(UserRole::Admin->value)) {
            $user->syncRoles([$request->enum('role', UserRole::class)->value]);
        }

        return redirect()->route('users.index')->with('status', 'User updated.');
    }
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, below the `users.store` route, add:

```php
        Route::get('users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit')->whereNumber('user');
        Route::put('users/{user}', [UserManagementController::class, 'update'])->name('users.management.update')->whereNumber('user');
```

- [ ] **Step 6: Create the Edit page**

`resources/js/Pages/Users/Edit.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    roleOptions: {
        type: Array,
        required: true,
    },
    canEditRole: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    email: props.user.email,
    role: props.user.role,
});

const submit = () => {
    form.put(route('users.management.update', props.user.id));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Edit ${user.name}`" />

        <h1 class="mb-6 text-2xl font-semibold">Edit user</h1>

        <form class="max-w-2xl" @submit.prevent="submit">
            <Card>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="first_name" class="block text-sm font-medium">First name</label>
                            <Input id="first_name" v-model="form.first_name" class="mt-1" />
                            <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-600">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium">Last name</label>
                            <Input id="last_name" v-model="form.last_name" class="mt-1" />
                            <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-600">{{ form.errors.last_name }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium">Email</label>
                        <Input id="email" v-model="form.email" type="email" class="mt-1" />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div v-if="canEditRole">
                        <label for="role" class="block text-sm font-medium">Role</label>
                        <select
                            id="role"
                            v-model="form.role"
                            class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm"
                        >
                            <option v-for="option in roleOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.role" class="mt-1 text-sm text-red-600">{{ form.errors.role }}</p>
                    </div>
                    <div v-else>
                        <p class="text-sm text-muted-foreground">Role: {{ user.role }}</p>
                    </div>
                </CardContent>
            </Card>

            <div class="mt-6 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Save changes</Button>
                <Button as-child variant="ghost">
                    <Link :href="route('users.index')">Cancel</Link>
                </Button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 7: Run test + build to verify**

Run: `php artisan test --compact --filter=UpdateUserTest`
Expected: PASS (3 passing).
Run: `npm run build`
Expected: builds without errors.

- [ ] **Step 8: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/User/UpdateUserRequest.php app/Http/Controllers/UserManagementController.php resources/js/Pages/Users/Edit.vue routes/web.php tests/Feature/Users/UpdateUserTest.php
git commit -m "feat: add edit/update user endpoint and form"
```

---

### Task 8: Delete (soft) + resend invite

**Files:**
- Modify: `app/Http/Controllers/UserManagementController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Users/Index.vue`
- Test: `tests/Feature/Users/DeleteUserTest.php`

**Interfaces:**
- Consumes: `UserPolicy::delete`/`manage`; `UserInvitation`; password broker.
- Produces:
  - `DELETE users/{user}` → `users.destroy` (soft delete; self-delete blocked for everyone).
  - `POST users/{user}/resend-invite` → `users.invite.resend` (only while pending).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Users/DeleteUserTest.php`:

```php
<?php

use App\Models\User;
use App\Notifications\UserInvitation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('soft deletes a user and blocks their login', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($admin)->delete(route('users.destroy', $student))
        ->assertRedirect(route('users.index'));

    expect(User::find($student->id))->toBeNull();
    expect(User::withTrashed()->find($student->id)->trashed())->toBeTrue();
});

it('blocks an admin from deleting their own account', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->delete(route('users.destroy', $admin))->assertForbidden();

    expect(User::find($admin->id))->not->toBeNull();
});

it('forbids an instructor from deleting another instructors student', function () {
    $instructor = User::factory()->instructor()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($instructor)->delete(route('users.destroy', $student))->assertForbidden();
});

it('resends an invitation to a pending user', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $pending = User::factory()->student()->unverified()->create();

    $this->actingAs($admin)->post(route('users.invite.resend', $pending))
        ->assertRedirect();

    Notification::assertSentTo($pending, UserInvitation::class);
});

it('does not resend an invitation to an already-active user', function () {
    $admin = User::factory()->admin()->create();
    $active = User::factory()->student()->create();

    $this->actingAs($admin)->post(route('users.invite.resend', $active))
        ->assertStatus(422);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DeleteUserTest`
Expected: FAIL (route `users.destroy` not defined).

- [ ] **Step 3: Add `destroy` + `resendInvite` to the controller**

Add imports to `app/Http/Controllers/UserManagementController.php`:

```php
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Password;
```

Add these methods (after `update`):

```php
    /**
     * Soft-delete a managed user. Nobody may delete their own account.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403, 'You cannot delete your own account.');

        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User removed.');
    }

    /**
     * Re-send the invitation to a user who has not yet accepted it.
     */
    public function resendInvite(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manage', $user);

        abort_if($user->email_verified_at !== null, 422, 'This user has already accepted their invitation.');

        $user->notify(new UserInvitation(Password::createToken($user)));

        return back()->with('status', 'Invitation resent.');
    }
```

- [ ] **Step 4: Register the routes**

In `routes/web.php`, below the `users.management.update` route, add:

```php
        Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy')->whereNumber('user');
        Route::post('users/{user}/resend-invite', [UserManagementController::class, 'resendInvite'])->name('users.invite.resend')->whereNumber('user');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=DeleteUserTest`
Expected: PASS (5 passing).

- [ ] **Step 6: Wire delete + resend into the Index actions**

In `resources/js/Pages/Users/Index.vue`, replace the imports/script block header to add `router`, a dropdown, and icons. Change the top imports to:

```js
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, UsersRound, MoreHorizontal, Pencil, Send, Trash2 } from 'lucide-vue-next';
```

Add these handlers inside `<script setup>` after `defineProps({...})`:

```js
const destroy = (row) => {
    if (confirm(`Remove ${row.name}? Their account will be disabled.`)) {
        router.delete(route('users.destroy', row.id));
    }
};

const resendInvite = (row) => {
    router.post(route('users.invite.resend', row.id));
};
```

Replace the `Actions` `<TableCell>` in the row (the one containing the single Edit `Button`) with:

```vue
                        <TableCell class="text-right">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="size-8">
                                        <MoreHorizontal class="size-4" />
                                        <span class="sr-only">User actions</span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-44">
                                    <DropdownMenuItem as-child>
                                        <Link :href="route('users.edit', row.id)" class="cursor-pointer">
                                            <Pencil class="size-4" />
                                            Edit
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="row.status === 'Invited'"
                                        class="cursor-pointer"
                                        @select="resendInvite(row)"
                                    >
                                        <Send class="size-4" />
                                        Resend invite
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem variant="destructive" class="cursor-pointer" @select="destroy(row)">
                                        <Trash2 class="size-4" />
                                        Remove
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
```

- [ ] **Step 7: Build to verify the frontend compiles**

Run: `npm run build`
Expected: builds without errors.

- [ ] **Step 8: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/UserManagementController.php routes/web.php resources/js/Pages/Users/Index.vue tests/Feature/Users/DeleteUserTest.php
git commit -m "feat: add soft-delete and resend-invite for users"
```

---

### Task 9: Full-suite regression + roster interplay check

**Files:**
- Test: run the whole suite (no new files unless a regression surfaces).

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test --compact`
Expected: PASS (all green, including pre-existing Profile/Roster/Auth tests — confirms the `whereNumber('user')` profile-route constraint and the new `users` routes did not break existing `users.show`/`users.update`/roster flows).

- [ ] **Step 2: If any pre-existing test regressed, fix inline**

Most likely suspects if red:
- `users.show` / profile tests → verify `->whereNumber('user')` was added to BOTH the profile `show` and `update` routes and that management `users/create` is registered before the profile bare route.
- Nav render tests → verify the `users` key exists in `THEMES`.

Re-run the specific failing file with `--filter` until green.

- [ ] **Step 3: Final commit (only if fixes were needed)**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "test: fix regressions from user management routes"
```

---

## Self-Review

**1. Spec coverage:**
- Roles/permissions matrix → Tasks 2, 5, 6, 7, 8 (policy + per-endpoint enforcement). ✓
- Data model (`deleted_at`, `created_by`, SoftDeletes, Searchable, derived status) → Task 1 + `UserManagementResource` status in Task 5. ✓
- Invitation flow (InviteUser, UserInvitation, accept route/page, resend) → Tasks 3, 4, 8. ✓
- Routes & controller + collision handling (`whereNumber`, PUT vs PATCH) → Tasks 5–8 + Task 9 regression. ✓
- Authorization (`viewAny`/`create`/`manage`/`delete`, admin self-delete guard) → Task 2 + Task 8 controller guard. ✓
- Frontend pages (Index/Create/Edit/Accept) + nav + `manage_users` share → Tasks 4, 5, 6, 7, 8. ✓
- Testing matrix (all bullets in spec) → covered across Tasks 1–8. ✓
- Out of scope (bulk/CSV/hard-delete/last-admin/avatar-on-create/self-registration) → not implemented. ✓

**2. Placeholder scan:** No TBD/TODO; every code step contains complete code and exact commands. ✓

**3. Type consistency:**
- `InviteUser::run(array, UserRole, User): User` — same signature used in Tasks 3 and 6. ✓
- `UserManagementResource` shape (incl. `first_name`, `role`, `status`) consumed by Index (Task 5) and Edit (Task 7) forms. ✓
- Route name `users.management.update` used consistently in controller, route, Edit.vue, and tests (Task 7). ✓
- Policy `manage`/`delete`/`viewAny`/`create` referenced consistently by controllers, requests, and shared prop. ✓
- `roleOptions` + `PER_PAGE` defined in Task 5, reused in Tasks 6–7. ✓
