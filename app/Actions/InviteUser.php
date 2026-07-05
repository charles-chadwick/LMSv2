<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class InviteUser
{
    use AsAction;

    /**
     * Provision a new, unverified user and email them an invitation to set
     * their password. The 'hashed' cast hashes the placeholder password.
     *
     * @param  array{first_name: string, last_name: string, email: string}  $attributes
     */
    public function handle(array $attributes, UserRole $role, User $creator): User
    {
        $user = new User;
        $user->first_name = $attributes['first_name'];
        $user->last_name = $attributes['last_name'];
        $user->email = $attributes['email'];
        $user->password = Str::password(32);
        $user->created_by = $creator->id;
        $user->save();

        $user->assignRole($role->value);

        $user->notify(new UserInvitation(Password::createToken($user)));

        return $user;
    }
}
