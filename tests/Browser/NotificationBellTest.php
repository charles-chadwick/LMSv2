<?php

use App\Models\Discussion;
use App\Models\User;
use App\Notifications\NewDiscussionQuestion;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('shows the unread count in the nav bell', function () {
    Event::fake(); // keep the notification's broadcast off the (non-running) Reverb server; DB row still writes
    $user = User::factory()->create();
    $discussion = Discussion::factory()->create();
    $user->notify(new NewDiscussionQuestion($discussion));
    $this->actingAs($user);

    $page = visit(route('dashboard'));

    $page->assertSeeIn('[aria-label="Notifications"]', '1')->assertNoJavaScriptErrors();
});
