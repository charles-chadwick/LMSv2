# Reverb Broadcasting Foundation — Design

**Date:** 2026-07-03
**Branch:** feature/reverb-foundation
**Status:** Approved

## Summary

Install and configure Laravel Reverb as the app's real-time broadcasting layer, wire
Laravel Echo into the Inertia/Vue frontend, and prove the full server→WebSocket→browser
path end-to-end. This is shared infrastructure for two later slices — live course/lesson
**discussions** and live **private messaging** — and delivers no user-facing feature of
its own beyond a proof-of-life event on the reusable per-user channel.

This is sub-project 1 of 3. Sequence: **foundation → discussions → messaging**. Both
later features broadcast live via Reverb.

## Goals

- Reverb server installed, configured, and runnable in local development.
- `config/broadcasting.php` + `config/reverb.php` published; `BROADCAST_CONNECTION=reverb`.
- Laravel Echo + `pusher-js` installed and wired so `window.Echo` is available app-wide,
  authenticated through the existing session/`web` middleware (no API tokens).
- `routes/channels.php` with the standard per-user private channel `App.Models.User.{id}`,
  reusable by notifications, unread badges, and messaging.
- An automated test pattern for broadcast events (the app currently has none).

## Non-Goals

- Any discussion or messaging feature (later slices).
- Notifications table / `app/Notifications/*` classes (introduced when a feature needs them).
- Presence channels / online indicators (YAGNI until a feature needs them).
- Production deployment of the Reverb process (documented, not automated here).

## Current State (from codebase exploration)

- Broadcasting is fully greenfield: no `laravel/reverb`, no `config/broadcasting.php`,
  no `routes/channels.php`, no Echo, no `laravel-echo`/`pusher-js`, no `app/Events/`.
- `QUEUE_CONNECTION=database` is set and the `composer dev` script already runs a queue
  worker (`php artisan queue:listen --tries=1 --timeout=0`) concurrently with server,
  pail, and vite. So queued `ShouldBroadcast` events work in dev without new plumbing.
- `User` already uses `Illuminate\Notifications\Notifiable`.
- Tests run on MariaDB (`lms_v2_testing`) with `DatabaseTruncation` (see
  `test-db-mariadb-truncation` note). Broadcast tests use fakes — no live Reverb needed.

## Architecture

### 1. Install & configuration

Run `php artisan install:broadcasting` (non-interactive). It:

- Adds `laravel/reverb` (composer) and installs it.
- Publishes `config/broadcasting.php` and `config/reverb.php`.
- Sets `BROADCAST_CONNECTION=reverb` and generates `REVERB_APP_ID/KEY/SECRET` in `.env`.
- Adds `laravel-echo` + `pusher-js` to `package.json` and generates the Echo bootstrap
  (`resources/js/echo.js`).
- Creates `routes/channels.php` with:

```php
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});
```

If the generated closure uses a loose signature, tighten it to the typed form above to
match project PHP conventions (explicit param + return types).

### 2. Frontend wiring

- Ensure `resources/js/echo.js` configures Echo with `broadcaster: 'reverb'` and the
  `VITE_REVERB_*` env vars (host, port, scheme, key).
- Import it from `resources/js/app.js` so `window.Echo` is initialized app-wide, before
  the Inertia app mounts. Follow the existing `app.js` structure (Inertia + Ziggy setup).

### 3. Environment

Add to `.env.example` (documented placeholders, no real secrets):

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

### 4. Dev orchestration

Add a 5th process to the `composer dev` concurrently command so the Reverb server runs
alongside the rest:

```
"php artisan reverb:start"
```

Extend the `--names` list and color list accordingly (e.g. add `reverb`). The queue
worker is already present, so no change there.

### 5. Proof-of-life event (permanent, reusable)

Create `app/Events/BroadcastPing.php` — a minimal `ShouldBroadcast` event on the
per-user private channel. It is kept (not throwaway): it doubles as the smoke test for
the channel that notifications/messaging will reuse.

```php
namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
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

    /** @return array{message: string} */
    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
```

## Data Flow

```
broadcast(new BroadcastPing($user))
  → ShouldBroadcast queued onto the database queue
  → queue worker processes it
  → Reverb server pushes over WebSocket
  → browser: Echo.private(`App.Models.User.${id}`).listen('BroadcastPing', cb)
```

Channel authorization: the browser hits `POST /broadcasting/auth` (registered by
`install:broadcasting`) which runs under `web` middleware, so the logged-in Inertia
session authorizes the private channel via the `routes/channels.php` closure.

## Error Handling

- Reverb server not running in dev → broadcasts queue/no-op; the app still functions,
  just without live updates. The spec/README note: `composer dev` must be running.
- Broadcasting is queued, so a broadcasting failure never blocks or errors the
  originating HTTP request.
- Channel auth denies non-matching users (`$user->id === $id`), preventing a user from
  subscribing to another user's private channel.

## Testing

Automated (Pest, MariaDB + `DatabaseTruncation`, no live Reverb server):

1. **Event broadcasts on the correct channel** — `Event::fake()`, dispatch
   `BroadcastPing`, assert dispatched on `App.Models.User.{id}` with payload
   `['message' => ...]`. Establishes the broadcast-event test pattern.
2. **Channel authorization allows the owner** — a user authorizes their own
   `App.Models.User.{id}` channel (assert the closure returns true / the
   `/broadcasting/auth` request succeeds for the owner).
3. **Channel authorization denies others** — a different authenticated user is denied
   authorization for someone else's channel.

Manual verification (documented, run once):

- Start `composer dev` (server, queue, reverb, vite all up).
- In `php artisan tinker`: `broadcast(new App\Events\BroadcastPing(User::first()));`
- In the browser console as that user:
  `Echo.private('App.Models.User.' + userId).listen('BroadcastPing', e => console.log(e))`
  — confirm `{ message: 'pong' }` arrives.
- `npm run build` compiles with Echo wired.

## Files

- **New:** `config/broadcasting.php`, `config/reverb.php` (published)
- **New:** `routes/channels.php`
- **New:** `resources/js/echo.js` (generated)
- **New:** `app/Events/BroadcastPing.php`
- **New:** `tests/Feature/Broadcasting/BroadcastFoundationTest.php`
- **Edit:** `resources/js/app.js` (import Echo bootstrap)
- **Edit:** `.env.example` (Reverb + Vite Reverb keys, `BROADCAST_CONNECTION=reverb`)
- **Edit:** `composer.json` (`dev` script: add `reverb:start` process)
- **Edit:** `package.json` (`laravel-echo`, `pusher-js` — added by installer)
- **Edit:** `composer.json` / `composer.lock` (`laravel/reverb` — added by installer)

## Follow-on Slices (context, not built here)

- **Discussions:** reuse `Discussion`/`DiscussionReply` models; extend to attach to
  lessons ("pages") as well as courses; broadcast new replies live on a course/lesson
  channel authorized via `CoursePolicy::learn()`.
- **Messaging:** greenfield conversations/messages between students and instructors;
  live chat on a private conversation channel; unread badges via `App.Models.User.{id}`.
