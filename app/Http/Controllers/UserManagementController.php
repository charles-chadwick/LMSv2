<?php

namespace App\Http\Controllers;

use App\Actions\InviteUser;
use App\Enums\UserRole;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserManagementResource;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
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
            ->withFilters($request->input('filters'))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user): array => UserManagementResource::make($user)->resolve($request));

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $request->query('search'),
                ...$request->input('filters', []),
            ],
            'filterOptions' => $this->filterOptions(),
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
     * Show the edit form for a managed user.
     */
    public function edit(Request $request, User $user): Response
    {
        $this->authorize('manage', $user);

        return Inertia::render('Users/Edit', [
            'user' => UserManagementResource::make($user->load('roles', 'media'))->resolve($request),
            'roleOptions' => $this->roleOptions($request->user()),
            'canEditRole' => $request->user()->hasRole(UserRole::Admin->value),
        ]);
    }

    /**
     * Update a managed user. Only admins may change roles.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->safe()->only('first_name', 'last_name', 'email'));

        if ($request->user()->hasRole(UserRole::Admin->value)) {
            $user->syncRoles([$request->enum('role', UserRole::class)->value]);
        }

        return redirect()->route('users.index')->with('status', 'User updated.');
    }

    /**
     * Soft-delete a managed user. Nobody may delete their own account.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403, 'You cannot delete your own account.');

        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User removed.');
    }

    /**
     * Re-send the invitation to a user who has not yet accepted it.
     */
    public function resendInvite(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manage', $user);

        abort_if($user->email_verified_at !== null, 422, 'This user has already accepted their invitation.');

        $user->notify(new UserInvitation(Password::createToken($user)));

        return back()->with('status', 'Invitation resent.');
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

    /**
     * Declarative filter controls for the user list.
     *
     * @return list<array<string, mixed>>
     */
    private function filterOptions(): array
    {
        return [
            [
                'key' => 'role',
                'label' => 'Role',
                'type' => 'select',
                'multiple' => true,
                'options' => array_map(
                    fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->value],
                    UserRole::cases(),
                ),
            ],
            [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'multiple' => true,
                'options' => [
                    ['value' => 'Active', 'label' => 'Active'],
                    ['value' => 'Invited', 'label' => 'Invited'],
                ],
            ],
            [
                'key' => 'created_at',
                'label' => 'Created',
                'type' => 'daterange',
            ],
        ];
    }
}
