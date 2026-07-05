<?php

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Carbon;

it('filters a user\'s notifications by type, read state, and created_at', function () {
    $user = User::factory()->create();

    $question = Notification::factory()->for($user, 'notifiable')
        ->ofType(NotificationType::NewQuestion)->unread()
        ->create(['created_at' => Carbon::parse('2026-06-01')]);
    Notification::factory()->for($user, 'notifiable')
        ->ofType(NotificationType::NewMessage)->read()
        ->create(['created_at' => Carbon::parse('2026-06-20')]);

    expect($user->notifications()->withFilters(['type' => ['new_question']])->pluck('id')->all())
        ->toBe([$question->id]);
    expect($user->notifications()->withFilters(['read' => 'unread'])->pluck('id')->all())
        ->toBe([$question->id]);
    expect($user->notifications()->withFilters(['created_at' => ['to' => '2026-06-10']])->pluck('id')->all())
        ->toBe([$question->id]);
    expect($user->notifications()->withFilters([])->count())->toBe(2);
});
