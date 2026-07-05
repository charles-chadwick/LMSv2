<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /**
     * Show the set-password form for an invited user.
     */
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Invitations/Accept', [
            'email' => $request->query('email'),
            'token' => $token,
        ]);
    }

    /**
     * Set the invited user's password, mark them verified, and log them in.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $accepted = null;

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request, &$accepted): void {
                $user->forceFill([
                    'password' => Hash::make($request->string('password')),
                    'email_verified_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                $accepted = $user;
            }
        );

        if ($status === Password::PASSWORD_RESET && $accepted !== null) {
            Auth::login($accepted);

            return redirect()->route('dashboard')->with('status', 'Welcome! Your account is ready.');
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
