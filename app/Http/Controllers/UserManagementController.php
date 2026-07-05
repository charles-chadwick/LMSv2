<?php

namespace App\Http\Controllers;

use App\Actions\InviteUser;
use App\Enums\UserRole;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Resources\UserManagementResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    /**
     * Users shown per management list page.
     */
    private const PER_PAGE = 20;

    /**
     * List users. Admins see everyone; instructors see only their own students.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $viewer = $request->user();

        $users = User::query()
            ->when(
                ! $viewer->hasRole(UserRole::Admin->value),
                fn ($query) => $query->where('created_by', $viewer->id),
            )
            ->with('roles', 'media')
            ->withSearch($request->query('search'))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user): array => UserManagementResource::make($user)->resolve($request));

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['search' => $request->query('search')],
        ]);
    }

    /**
     * Show the new-user form.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'roleOptions' => $this->roleOptions($request->user()),
        ]);
    }

    /**
     * Provision a user and send them an invitation.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        InviteUser::run(
            $request->safe()->only('first_name', 'last_name', 'email'),
            $request->enum('role', UserRole::class),
            $request->user(),
        );

        return redirect()->route('users.index')->with('status', 'Invitation sent.');
    }

    /**
     * Role options the given actor is allowed to assign.
     *
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(User $actor): array
    {
        $roles = $actor->hasRole(UserRole::Admin->value) ? UserRole::cases() : [UserRole::Student];

        return array_map(
            fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->value],
            $roles,
        );
    }
}
