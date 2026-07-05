<?php

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('filters notifications by type', function () {
    $user = User::factory()->create();
    $question = Notification::factory()->for($user, 'notifiable')
        ->ofType(NotificationType::NewQuestion)->create();
    Notification::factory()->for($user, 'notifiable')
        ->ofType(NotificationType::NewMessage)->create();

    actingAs($user)->get(route('notifications.index', ['filters' => ['type' => ['new_question']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.total', 1)
            ->where('notifications.data.0.id', $question->id));
});

it('filters notifications by unread state', function () {
    $user = User::factory()->create();
    $unread = Notification::factory()->for($user, 'notifiable')->unread()->create();
    Notification::factory()->for($user, 'notifiable')->read()->create();

    actingAs($user)->get(route('notifications.index', ['filters' => ['read' => 'unread']]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.total', 1)
            ->where('notifications.data.0.id', $unread->id));
});

it('paginates notifications and exposes type, read, and date filter options', function () {
    $user = User::factory()->create();

    actingAs($user)->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications.data')
            ->has('notifications.total')
            ->has('filterOptions', 3)
            ->where('filterOptions.0.key', 'type')
            ->where('filterOptions.1.key', 'read')
            ->where('filterOptions.2.key', 'created_at'));
});
