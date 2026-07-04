<?php

use App\Models\Conversation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('allows only participants to view and send', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);
    $outsider = User::factory()->student()->create();

    expect($student->can('view', $conversation))->toBeTrue()
        ->and($instructor->can('send', $conversation))->toBeTrue()
        ->and($outsider->can('view', $conversation))->toBeFalse()
        ->and($outsider->can('send', $conversation))->toBeFalse();
});
