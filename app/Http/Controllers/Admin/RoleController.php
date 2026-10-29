<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Support\Rbac;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->get()
            ->sortBy(fn (Role $role) => array_search($role->name, Rbac::roles(), true))
            ->values()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'locked' => $role->name === Rbac::ADMIN,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions->count(),
                'granted' => $this->grantedByModule($role->permissions->pluck('name')->all()),
            ]);

        return Inertia::render('Admin/Roles/Index', ['roles' => $roles]);
    }

    public function edit(Role $role): Response
    {
        $groups = [];

        foreach (Rbac::modules() as $module => $actions) {
            $groups[] = [
                'module' => $module,
                'permissions' => array_map(
                    fn (string $action) => ['name' => "{$module}.{$action}", 'action' => $action],
                    $actions,
                ),
            ];
        }

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'locked' => $role->name === Rbac::ADMIN,
            ],
            'groups' => $groups,
            'granted' => $role->permissions()->pluck('name')->all(),
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->name === Rbac::ADMIN, 403, 'The Admin role cannot be edited.');

        $role->syncPermissions($request->validated('permissions'));

        return to_route('admin.roles.index')->with('success', "Permissions for {$role->name} were updated.");
    }

    /**
     * Group permission names by module for display: ['leads' => ['view', 'create']].
     *
     * @param  list<string>  $names
     * @return array<string, list<string>>
     */
    private function grantedByModule(array $names): array
    {
        $grouped = [];

        foreach (Rbac::modules() as $module => $actions) {
            $granted = array_values(array_filter(
                $actions,
                fn (string $action) => in_array("{$module}.{$action}", $names, true),
            ));

            if ($granted !== []) {
                $grouped[$module] = $granted;
            }
        }

        return $grouped;
    }
}
