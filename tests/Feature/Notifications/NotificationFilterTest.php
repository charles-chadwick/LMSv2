<?php

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
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

it('filters notifications by multiple selected types', function () {
    $user = User::factory()->create();
    Notification::factory()->for($user, 'notifiable')
        ->ofType(NotificationType::NewQuestion)->create();
    Notification::factory()->for($user, 'notifiable')
        ->ofType(NotificationType::NewReply)->create();
    Notification::factory()->for($user, 'notifiable')
        ->ofType(NotificationType::NewMessage)->create();

    actingAs($user)->get(route('notifications.index', ['filters' => ['type' => ['new_question', 'new_reply']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.total', 2));
});

it('filters notifications by read state', function () {
    $user = User::factory()->create();
    $read = Notification::factory()->for($user, 'notifiable')->read()->create();
    Notification::factory()->for($user, 'notifiable')->unread()->create();

    actingAs($user)->get(route('notifications.index', ['filters' => ['read' => 'read']]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.total', 1)
            ->where('notifications.data.0.id', $read->id));
});

it('shows all notifications when both read and unread are selected', function () {
    $user = User::factory()->create();
    Notification::factory()->for($user, 'notifiable')->read()->create();
    Notification::factory()->for($user, 'notifiable')->unread()->create();

    actingAs($user)->get(route('notifications.index', ['filters' => ['read' => ['read', 'unread']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.total', 2));
});

it('filters notifications by created_at range', function () {
    $user = User::factory()->create();
    Notification::factory()->for($user, 'notifiable')->create(['created_at' => Carbon::parse('2026-06-01')]);
    $middle = Notification::factory()->for($user, 'notifiable')->create(['created_at' => Carbon::parse('2026-06-15')]);
    Notification::factory()->for($user, 'notifiable')->create(['created_at' => Carbon::parse('2026-06-30')]);

    actingAs($user)->get(route('notifications.index', [
        'filters' => ['created_at' => ['from' => '2026-06-10', 'to' => '2026-06-20']],
    ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.total', 1)
            ->where('notifications.data.0.id', $middle->id));
});

it('paginates notifications while an active filter is applied', function () {
    $user = User::factory()->create();
    Notification::factory()->for($user, 'notifiable')
        ->ofType(NotificationType::NewMessage)->count(25)->create();

    actingAs($user)->get(route('notifications.index', ['filters' => ['type' => ['new_message']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.total', 25)
            ->where('notifications.per_page', 20)
            ->has('notifications.data', 20));
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
