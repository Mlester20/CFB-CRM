<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * List users with an optional name/email search.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->with('roles:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user) => $this->present($user, $request));

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => Rbac::roles(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        // Created by an admin, so the email counts as verified.
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles($data['role']);

        return to_route('admin.users.index')->with('success', "User {$user->name} was created.");
    }

    public function edit(Request $request, User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'roles' => Rbac::roles(),
            'managedUser' => $this->present($user->load('roles:id,name'), $request),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->update(['name' => $data['name'], 'email' => $data['email']]);
        $user->syncRoles($data['role']);

        return to_route('admin.users.index')->with('success', "User {$user->name} was updated.");
    }

    /**
     * Deactivate or reactivate a user.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($user->is_active && $user->isLastActiveAdmin()) {
            return back()->with('error', 'The last active Admin cannot be deactivated.');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return back()->with('success', $user->is_active
            ? "{$user->name} was reactivated."
            : "{$user->name} was deactivated.");
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->forceFill(['password' => Hash::make($request->validated('password'))])->save();

        return back()->with('success', "Password for {$user->name} was reset.");
    }

    /**
     * Only the fields the admin screens need.
     *
     * @return array<string, mixed>
     */
    private function present(User $user, Request $request): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'role' => $user->roles->first()?->name,
            'created_at' => $user->created_at?->toDateString(),
            'is_self' => $user->is($request->user()),
        ];
    }
}
