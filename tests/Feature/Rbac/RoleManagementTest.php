<?php

namespace Tests\Feature\Rbac;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_roles_list_shows_the_four_roles_with_grouped_permissions(): void
    {
        $this->actingAs($this->userWithRole('Admin'))->get('/admin/roles')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Roles/Index')
                ->has('roles', 4)
                ->where('roles.0.name', 'Admin')
                ->where('roles.0.locked', true)
                ->where('roles.3.name', 'Viewer')
                ->where('roles.3.granted.leads', ['view', 'view-all'])
                ->where('roles.3.permissions_count', 11));
    }

    public function test_the_admin_role_cannot_be_edited(): void
    {
        $admin = $this->userWithRole('Admin');
        $adminRole = Role::findByName('Admin');

        $this->actingAs($admin)->put("/admin/roles/{$adminRole->id}", ['permissions' => ['dashboard.view']])
            ->assertForbidden();

        $this->assertSame(38, $adminRole->fresh()->permissions()->count());

        $this->actingAs($admin)->get("/admin/roles/{$adminRole->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('role.locked', true));
    }

    public function test_another_roles_permissions_can_be_edited_and_apply_immediately(): void
    {
        $admin = $this->userWithRole('Admin');
        $viewer = $this->userWithRole('Viewer');
        $viewerRole = Role::findByName('Viewer');

        $this->actingAs($viewer)->get('/admin/users')->assertForbidden();
        $this->assertFalse($viewer->fresh()->can('leads.export'));

        $this->actingAs($admin)->put("/admin/roles/{$viewerRole->id}", [
            'permissions' => ['dashboard.view', 'leads.view', 'leads.export', 'users.manage'],
        ])->assertRedirect('/admin/roles');

        $this->assertEqualsCanonicalizing(
            ['dashboard.view', 'leads.view', 'leads.export', 'users.manage'],
            $viewerRole->fresh()->permissions->pluck('name')->all(),
        );

        // The very next request already sees the new permissions.
        $this->actingAs($viewer->fresh())->get('/admin/users')->assertOk();
        $this->assertTrue($viewer->fresh()->can('leads.export'));
        $this->assertFalse($viewer->fresh()->can('deals.view'));
    }

    public function test_revoking_dashboard_view_denies_the_dashboard_immediately(): void
    {
        $admin = $this->userWithRole('Admin');
        $rep = $this->userWithRole('Sales Rep');
        $repRole = Role::findByName('Sales Rep');

        $this->actingAs($rep)->get('/dashboard')->assertOk();

        $this->actingAs($admin)->put("/admin/roles/{$repRole->id}", [
            'permissions' => $repRole->permissions->pluck('name')->reject(fn ($n) => $n === 'dashboard.view')->values()->all(),
        ]);

        $this->actingAs($rep->fresh())->get('/dashboard')->assertForbidden();
    }

    public function test_unknown_permission_names_are_rejected(): void
    {
        $viewerRole = Role::findByName('Viewer');

        $this->actingAs($this->userWithRole('Admin'))
            ->put("/admin/roles/{$viewerRole->id}", ['permissions' => ['leads.view', 'made.up']])
            ->assertSessionHasErrors('permissions.1');

        $this->assertSame(11, $viewerRole->fresh()->permissions()->count());
    }

    public function test_a_role_can_be_emptied(): void
    {
        $viewerRole = Role::findByName('Viewer');

        $this->actingAs($this->userWithRole('Admin'))
            ->put("/admin/roles/{$viewerRole->id}", ['permissions' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $viewerRole->fresh()->permissions()->count());
    }

    public function test_non_admins_cannot_edit_roles(): void
    {
        $viewerRole = Role::findByName('Viewer');

        $this->actingAs($this->userWithRole('Sales Manager'))
            ->put("/admin/roles/{$viewerRole->id}", ['permissions' => ['users.manage']])
            ->assertForbidden();

        $this->assertSame(11, $viewerRole->fresh()->permissions()->count());
    }
}
