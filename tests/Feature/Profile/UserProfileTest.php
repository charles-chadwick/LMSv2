<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('an authenticated user can view another users profile read-only', function (): void {
    $viewer = User::factory()->student()->create();
    $subject = User::factory()->instructor()->create()->refresh();

    $this->actingAs($viewer)
        ->get(route('users.show', $subject))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Profile/Show')
            ->where('profile.id', $subject->id)
            ->where('profile.name', $subject->name)
            ->where('profile.role', 'Instructor')
            ->where('can_edit', false)
            ->where('form', null)
        );
});

test('viewing your own profile exposes the edit form', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->get(route('users.show', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Profile/Show')
            ->where('can_edit', true)
            ->where('form.first_name', $user->first_name)
            ->where('form.last_name', $user->last_name)
            ->where('form.email', $user->email)
        );
});

test('a user can update their own profile', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->patch(route('users.update', $user), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
        ])
        ->assertRedirect();

    $user->refresh();
    expect($user->name)->toBe('Ada Lovelace');
    expect($user->email)->toBe('ada@example.com');
});

test('a user cannot update another users profile', function (): void {
    $user = User::factory()->student()->create();
    $other = User::factory()->student()->create();

    $this->actingAs($user)
        ->patch(route('users.update', $other), [
            'first_name' => 'Hacked',
            'last_name' => 'Name',
            'email' => 'hacked@example.com',
        ])
        ->assertForbidden();
});

test('a user can upload their own avatar', function (): void {
    Storage::fake('public');
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->post(route('users.avatar.store', $user), [
            'avatar' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ])
        ->assertRedirect();

    $user->refresh();
    expect($user->avatar_thumb_url)->not->toBeNull();
    expect($user->avatar_preview_url)->not->toBeNull();
});

test('a user cannot upload an avatar for another user', function (): void {
    Storage::fake('public');
    $user = User::factory()->student()->create();
    $other = User::factory()->student()->create();

    $this->actingAs($user)
        ->post(route('users.avatar.store', $other), [
            'avatar' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ])
        ->assertForbidden();
});

test('avatar upload rejects non-image files', function (): void {
    Storage::fake('public');
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->post(route('users.avatar.store', $user), [
            'avatar' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('avatar');
});

test('a user can remove their own avatar', function (): void {
    Storage::fake('public');
    $user = User::factory()->student()->create();
    $user->addMedia(UploadedFile::fake()->image('me.jpg', 300, 300))->toMediaCollection('avatars');

    $this->actingAs($user)
        ->delete(route('users.avatar.destroy', $user))
        ->assertRedirect();

    expect($user->refresh()->avatar_thumb_url)->toBeNull();
});

test('a user can change their own password', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->put(route('users.password.update', $user), [
            'password' => 'Str0ng-Pass!',
            'password_confirmation' => 'Str0ng-Pass!',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'password-updated');

    expect(Hash::check('Str0ng-Pass!', $user->refresh()->password))->toBeTrue();
});

test('changing password requires a matching confirmation', function (): void {
    $user = User::factory()->student()->create();
    $original = $user->password;

    $this->actingAs($user)
        ->put(route('users.password.update', $user), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'does-not-match',
        ])
        ->assertSessionHasErrors('password');

    expect($user->refresh()->password)->toBe($original);
});

test('changing password rejects a weak password', function (): void {
    $user = User::factory()->student()->create();
    $original = $user->password;

    $this->actingAs($user)
        ->put(route('users.password.update', $user), [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertSessionHasErrors('password');

    expect($user->refresh()->password)->toBe($original);
});

test('changing password enforces upper, lower, number, and symbol', function (string $password): void {
    $user = User::factory()->student()->create();
    $original = $user->password;

    $this->actingAs($user)
        ->put(route('users.password.update', $user), [
            'password' => $password,
            'password_confirmation' => $password,
        ])
        ->assertSessionHasErrors('password');

    expect($user->refresh()->password)->toBe($original);
})->with([
    'no uppercase' => ['str0ng-pass!'],
    'no lowercase' => ['STR0NG-PASS!'],
    'no number' => ['Strong-Pass!'],
    'no symbol' => ['Str0ngPass1'],
]);

test('a user cannot change another users password', function (): void {
    $user = User::factory()->student()->create();
    $other = User::factory()->student()->create();
    $original = $other->password;

    $this->actingAs($user)
        ->put(route('users.password.update', $other), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
        ->assertForbidden();

    expect($other->refresh()->password)->toBe($original);
});
