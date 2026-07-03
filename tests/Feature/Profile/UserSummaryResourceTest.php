<?php

use App\Enums\UserRole;
use App\Http\Resources\UserSummaryResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('it exposes the user summary shape', function (): void {
    $user = User::factory()->student()->create();

    $data = UserSummaryResource::make($user)->resolve();

    expect($data)->toHaveKeys(['id', 'name', 'role', 'avatar_thumb', 'avatar_preview']);
    expect($data['id'])->toBe($user->id);
    expect($data['name'])->toBe($user->name);
    expect($data['role'])->toBe('Student');
    expect($data['avatar_thumb'])->toBeNull();
    expect($data['avatar_preview'])->toBeNull();
});

test('it falls back to Member when the user has no role', function (): void {
    $user = User::factory()->create();

    $data = UserSummaryResource::make($user)->resolve();

    expect($data['role'])->toBe('Member');
});

test('it capitalizes the first assigned role', function (): void {
    $user = User::factory()->instructor()->create();

    $data = UserSummaryResource::make($user)->resolve();

    expect($data['role'])->toBe(UserRole::Instructor->value);
});
