<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Profile\StoreAvatarRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserSummaryResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserProfileController extends Controller
{
    /**
     * Show a user's profile. Read-only for everyone; the owner also receives
     * the editable form data.
     */
    public function show(Request $request, User $user): Response
    {
        $is_own_profile = $request->user()->is($user);

        $viewer = $request->user();
        $can_message = ! $is_own_profile && (
            ($viewer->hasRole(UserRole::Student->value) && $user->hasRole(UserRole::Instructor->value))
            || ($viewer->hasRole(UserRole::Instructor->value) && $user->hasRole(UserRole::Student->value))
        );

        return Inertia::render('Profile/Show', [
            'profile' => UserSummaryResource::make($user)->resolve(),
            'can_edit' => $is_own_profile,
            'can_message' => $can_message,
            'form' => $is_own_profile
                ? [
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                ]
                : null,
        ]);
    }

    /**
     * Update the owner's name and email.
     */
    public function update(UpdateProfileRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return back()->with('status', 'Profile updated.');
    }

    /**
     * Change the owner's password. The 'hashed' cast hashes the value on save.
     */
    public function updatePassword(UpdatePasswordRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return back()->with('status', 'password-updated');
    }

    /**
     * Replace the owner's avatar (the collection is single-file).
     */
    public function storeAvatar(StoreAvatarRequest $request, User $user): RedirectResponse
    {
        $user->addMedia($request->file('avatar'))->toMediaCollection('avatars');

        return back()->with('status', 'Avatar updated.');
    }

    /**
     * Remove the owner's avatar.
     */
    public function destroyAvatar(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $user->clearMediaCollection('avatars');

        return back()->with('status', 'Avatar removed.');
    }
}
