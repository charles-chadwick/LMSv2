<?php

use App\Models\Discussion;
use App\Models\User;
use App\Notifications\NewDiscussionQuestion;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

// Event::fake() neutralizes the broadcast channel of the (queued, sync) notification so it
// never hits the non-running Reverb server, while the database channel still writes the row.
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Event::fake();
});

it('shares the unread notification count and lists notifications', function () {
    $user = User::factory()->create();
    $discussion = Discussion::factory()->create();
    $user->notify(new NewDiscussionQuestion($discussion));

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->where('auth.user.unread_notifications_count', 1)
            ->has('notifications', 1));
});

it('marks a single notification and all notifications as read', function () {
    $user = User::factory()->create();
    $discussion = Discussion::factory()->create();
    $user->notify(new NewDiscussionQuestion($discussion));
    $user->notify(new NewDiscussionQuestion($discussion));
    $first = $user->unreadNotifications()->first();

    $this->actingAs($user)->post(route('notifications.read', $first->id))->assertRedirect();
    expect($user->fresh()->unreadNotifications()->count())->toBe(1);

    $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();
    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});
