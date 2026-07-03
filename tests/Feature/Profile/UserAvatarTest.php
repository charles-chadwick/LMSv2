<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('avatar accessors are null when no avatar is uploaded', function (): void {
    $user = User::factory()->create();

    expect($user->avatar_thumb_url)->toBeNull();
    expect($user->avatar_preview_url)->toBeNull();
});

test('uploading an avatar generates thumb and preview conversions', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();

    $user->addMedia(UploadedFile::fake()->image('me.jpg', 400, 400))
        ->toMediaCollection('avatars');

    $user->refresh();

    expect($user->avatar_thumb_url)->toContain('thumb');
    expect($user->avatar_preview_url)->toContain('preview');
});

test('the avatar collection holds only a single file', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();

    $user->addMedia(UploadedFile::fake()->image('first.jpg', 400, 400))->toMediaCollection('avatars');
    $user->addMedia(UploadedFile::fake()->image('second.jpg', 400, 400))->toMediaCollection('avatars');

    expect($user->getMedia('avatars'))->toHaveCount(1);
    expect($user->getFirstMedia('avatars')->file_name)->toBe('second.jpg');
});
