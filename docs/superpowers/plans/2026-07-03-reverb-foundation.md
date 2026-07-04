# Reverb Broadcasting Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Install and configure Laravel Reverb as the app's real-time broadcasting layer, wire Laravel Echo into the Inertia/Vue frontend, and prove the server→WebSocket→browser path with a permanent proof-of-life event on a reusable per-user private channel.

**Architecture:** Use `php artisan install:broadcasting --reverb` to scaffold Reverb + config + `channels.php` + Echo/`pusher-js`. Echo is initialized app-wide via a side-effect import in `app.js`. Broadcasting is queued on the existing database queue (already running in `composer dev`). A `BroadcastPing` `ShouldBroadcast` event on `App.Models.User.{id}` is the smoke test and the reusable channel for later notifications/messaging.

**Tech Stack:** Laravel 13, PHP 8.4, Laravel Reverb, Laravel Echo + pusher-js, Inertia v3 + Vue 3, Pest 4, MariaDB (`lms_v2_testing`) with DatabaseTruncation.

## Global Constraints

- Variables `snake_case`; methods/functions `camelCase`; classes `TitleCase`.
- PHP: explicit return types + param type hints; curly braces on all control structures; PHPDoc over inline comments; array-shape PHPDoc.
- Prefer OOP; follow existing sibling-file conventions.
- Do not add dependencies beyond `laravel/reverb`, `laravel-echo`, `pusher-js` (all part of the Reverb install) without approval.
- `App.Models.User.{id}` private channel authorizes iff `$user->id === $id`.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes.
- Tests run on MariaDB with DatabaseTruncation; broadcast tests use fakes / the `/broadcasting/auth` endpoint — no live Reverb server in CI.
- Run affected tests with `php artisan test --compact --filter=...`.

---

### Task 1: Install & configure Reverb

**Files:**
- Create (generated): `config/broadcasting.php`, `config/reverb.php`, `routes/channels.php`, `resources/js/echo.js`
- Modify: `.env.example`, `phpunit.xml`, `composer.json` (`dev` script), `composer.json`/`composer.lock` + `package.json` (deps added by installer), `bootstrap/app.php` (only if broadcasting route not auto-registered)

**Interfaces:**
- Produces: `BROADCAST_CONNECTION=reverb`; a registered `POST /broadcasting/auth` route; `config('broadcasting.default') === 'reverb'`; `laravel-echo` + `pusher-js` in `package.json`.

- [ ] **Step 1: Run the installer**

Run: `php artisan install:broadcasting --reverb --no-interaction`
Expected: installs `laravel/reverb`, publishes `config/broadcasting.php` + `config/reverb.php`, creates `routes/channels.php`, sets `BROADCAST_CONNECTION=reverb` in `.env`, adds `laravel-echo` + `pusher-js` to `package.json`, and generates `resources/js/echo.js`.

- [ ] **Step 2: Verify the install landed**

Run: `php artisan config:show broadcasting.default`
Expected: `reverb`.

Run: `php artisan route:list --path=broadcasting`
Expected: a `POST broadcasting/auth` route is listed. If it is NOT listed, the framework did not auto-register the channel auth route — add broadcasting to `bootstrap/app.php` inside the `->withRouting(...)` call:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    channels: __DIR__.'/../routes/channels.php',
    health: '/up',
)
```

Re-run `php artisan route:list --path=broadcasting` and confirm the route now appears.

Run: `node -e "const p=require('./package.json');console.log(!!(p.dependencies||{})['laravel-echo'], !!(p.dependencies||{})['pusher-js'])"`
Expected: `true true`. If either is `false`, run `npm install --save laravel-echo pusher-js`.

- [ ] **Step 3: Add Reverb keys to `.env.example`**

Append to `.env.example` (replace any installer-added duplicates so keys appear once). Ensure `BROADCAST_CONNECTION` reads `reverb`:

```
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

- [ ] **Step 4: Add deterministic broadcasting env to `phpunit.xml`**

So `/broadcasting/auth` can sign channel responses in tests regardless of `.env`, add these `<env>` entries inside the `<php>` block of `phpunit.xml` (next to the existing DB entries):

```xml
        <env name="BROADCAST_CONNECTION" value="reverb"/>
        <env name="REVERB_APP_ID" value="test-app-id"/>
        <env name="REVERB_APP_KEY" value="test-app-key"/>
        <env name="REVERB_APP_SECRET" value="test-app-secret"/>
```

- [ ] **Step 5: Add the Reverb server to the `composer dev` script**

In `composer.json`, replace the `dev` script's concurrently line with one that adds a `reverb` process (5th color + name, `php artisan reverb:start` after the queue worker):

```json
        "Composer\\Config::disableProcessTimeout",
        "npx concurrently -c \"#93c5fd,#c4b5fd,#fbbf24,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan reverb:start\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,reverb,logs,vite --kill-others"
```

- [ ] **Step 6: Confirm config still boots and lint**

Run: `php artisan config:clear && php artisan config:show broadcasting.connections.reverb`
Expected: prints the reverb connection config (key/secret/app_id resolved) with no error.

Run: `vendor/bin/pint --dirty --format agent`
Expected: clean.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Install and configure Laravel Reverb broadcasting"
```

---

### Task 2: Wire Laravel Echo into the frontend

**Files:**
- Modify: `resources/js/echo.js` (ensure Reverb config), `resources/js/app.js` (side-effect import)
- Test: `tests/Browser/BroadcastEchoTest.php`

**Interfaces:**
- Consumes: `resources/js/echo.js` generated in Task 1; `VITE_REVERB_*` env.
- Produces: `window.Echo` initialized app-wide before the Inertia app mounts.

- [ ] **Step 1: Ensure `resources/js/echo.js` configures Echo for Reverb**

The file must construct Echo against Reverb using the Vite env vars. Its content should be (overwrite if the generated version differs):

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

- [ ] **Step 2: Import Echo bootstrap from `app.js`**

Add this as the FIRST line of `resources/js/app.js` (side-effect import initializes `window.Echo` before Inertia mounts):

```js
import './echo';
```

Leave the rest of `app.js` (the `createInertiaApp({ withApp })` block and Ziggy wiring) unchanged.

- [ ] **Step 3: Build the frontend**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 4: Write a browser smoke test**

Create `tests/Browser/BroadcastEchoTest.php`. It authenticates a user, loads an authed page, asserts `window.Echo` is defined, and asserts the page has no uncaught JS errors (i.e. Echo init did not break `app.js`). Mirror `tests/Browser/LoginTest.php` conventions (`assertNoJavaScriptErrors()` capital S; `actingAs` before `visit`).

```php
<?php

use App\Models\User;

it('initializes window.Echo without breaking the page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit(route('dashboard'));

    $page->assertNoJavaScriptErrors();

    expect($page->script('typeof window.Echo'))->not->toBe('undefined');
});
```

If `$page->script(...)` is not the correct accessor in this project's Pest browser plugin, check `tests/Browser/LoginTest.php` and other browser tests for the evaluate/script helper and use the matching one (the goal: read `typeof window.Echo` from the page and assert it is not `'undefined'`). If no JS-evaluate helper exists, assert only `assertNoJavaScriptErrors()` and additionally assert the built bundle references Echo: leave a comment noting the reduced check.

- [ ] **Step 5: Run the browser test**

Run: `php artisan test --compact --filter=BroadcastEchoTest`
Expected: PASS — page loads, `window.Echo` defined, no uncaught JS errors. (A failed WebSocket connection to a non-running Reverb server is handled internally by pusher-js and does not raise an uncaught error.)

- [ ] **Step 6: Commit**

```bash
git add resources/js/echo.js resources/js/app.js tests/Browser/BroadcastEchoTest.php
git commit -m "Wire Laravel Echo (Reverb) into the frontend"
```

---

### Task 3: BroadcastPing event + channel authorization + tests

**Files:**
- Create: `app/Events/BroadcastPing.php`
- Modify: `routes/channels.php` (typed closure)
- Test: `tests/Feature/Broadcasting/BroadcastFoundationTest.php`

**Interfaces:**
- Consumes: `POST /broadcasting/auth` route + reverb config from Task 1; the per-user channel string `App.Models.User.{id}`.
- Produces: `App\Events\BroadcastPing` (`implements ShouldBroadcast`, constructor `(User $user, string $message = 'pong')`, `broadcastOn(): PrivateChannel` on `App.Models.User.{id}`, `broadcastWith(): array{message: string}`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Broadcasting/BroadcastFoundationTest.php`:

```php
<?php

use App\Events\BroadcastPing;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

it('broadcasts on the owner private user channel with a message payload', function () {
    $user = User::factory()->create();

    $event = new BroadcastPing($user, 'pong');

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->broadcastOn()->name)->toBe('private-App.Models.User.'.$user->id)
        ->and($event->broadcastWith())->toBe(['message' => 'pong']);
});

it('authorizes the owner of a private user channel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$user->id,
        ])
        ->assertOk();
});

it('denies a user access to another users private channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$other->id,
        ])
        ->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=BroadcastFoundationTest`
Expected: FAIL — `Class "App\Events\BroadcastPing" not found` (and the auth tests may fail until the channel closure is confirmed).

- [ ] **Step 3: Create the event**

Create `app/Events/BroadcastPing.php`:

```php
<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastPing implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public User $user, public string $message = 'pong') {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->user->id);
    }

    /**
     * @return array{message: string}
     */
    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
```

- [ ] **Step 4: Ensure the per-user channel closure is typed**

Confirm `routes/channels.php` contains exactly (tighten the installer's version to typed params + return, and import `User`):

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=BroadcastFoundationTest`
Expected: PASS (3 passing). If the two `/broadcasting/auth` tests error with a signing/config issue, confirm Task 1 Step 4 added the `REVERB_APP_*` env to `phpunit.xml`.

- [ ] **Step 6: Lint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: clean.

- [ ] **Step 7: Commit**

```bash
git add app/Events/BroadcastPing.php routes/channels.php tests/Feature/Broadcasting/BroadcastFoundationTest.php
git commit -m "Add BroadcastPing proof-of-life event + per-user channel auth tests"
```

---

### Task 4: Regression, lint, and manual-verification note

**Files:**
- Create: `docs/superpowers/reverb-manual-verification.md` (one-time manual smoke steps)

**Interfaces:** none (verification + docs only).

- [ ] **Step 1: Full regression suite**

Run: `php artisan test --compact`
Expected: all tests pass (prior 172 + the new broadcasting/browser tests), no regressions.

- [ ] **Step 2: Lint sweep**

Run: `vendor/bin/pint --dirty --format agent`
Expected: clean.

- [ ] **Step 3: Write the manual-verification note**

Create `docs/superpowers/reverb-manual-verification.md`:

```markdown
# Reverb Foundation — Manual Verification

Live WebSocket delivery cannot run in CI (no Reverb server). Verify once locally:

1. Ensure `.env` has real `REVERB_APP_ID/KEY/SECRET` (generated by `install:broadcasting`)
   and the `VITE_REVERB_*` keys. Run `npm run build` (or `composer dev` runs vite).
2. Start everything: `composer dev` (server, queue, reverb, logs, vite).
3. Log in to the app in the browser. In the console, subscribe as the current user:

   Echo.private(`App.Models.User.${window.userId}`)
       .listen('BroadcastPing', (e) => console.log('ping', e));

   (Use the authenticated user's id for `${...}`.)
4. In another terminal: `php artisan tinker`

   broadcast(new App\Events\BroadcastPing(App\Models\User::find(<that-id>)));

5. Confirm the browser console logs `ping { message: 'pong' }`.

If nothing arrives: confirm the `reverb` process is running in `composer dev`, the queue
worker is processing (`BroadcastPing` is queued via `ShouldBroadcast`), and `/broadcasting/auth`
returns 200 for the channel (Network tab).
```

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/reverb-manual-verification.md
git commit -m "Add Reverb manual-verification note"
```

---

## Notes

- Broadcasting is queued (`ShouldBroadcast`); the `composer dev` queue worker delivers it. No synchronous broadcast is used.
- The `App.Models.User.{id}` channel is intentionally reusable: later slices (discussions, messaging) subscribe to it for notifications/unread badges, and add their own course/lesson/conversation channels.
- No `notifications` table or `app/Notifications/*` is created here — deferred to the first feature slice that needs it.
