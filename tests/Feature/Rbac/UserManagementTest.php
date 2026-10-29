<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
        $this->admin = $this->userWithRole('Admin', ['name' => 'Alice Admin', 'email' => 'alice@example.com']);
    }

    public function test_users_list_can_be_searched_by_name_or_email(): void
    {
        $this->userWithRole('Sales Rep', ['name' => 'Rhea Rep', 'email' => 'rhea@example.com']);
        $this->userWithRole('Viewer', ['name' => 'Victor View', 'email' => 'victor@sample.org']);

        $this->actingAs($this->admin)->get('/admin/users?search=sample.org')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data', 1)
                ->where('users.data.0.email', 'victor@sample.org')
                ->where('filters.search', 'sample.org'));

        $this->actingAs($this->admin)->get('/admin/users?search=Rhea')
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.role', 'Sales Rep'));

        $this->actingAs($this->admin)->get('/admin/users')
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 3));
    }

    public function test_admin_can_create_a_user_with_one_role_who_is_verified_and_active(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'New Rep',
            'email' => 'new.rep@example.com',
            'role' => 'Sales Rep',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])->assertRedirect('/admin/users');

        $user = User::where('email', 'new.rep@example.com')->firstOrFail();

        $this->assertSame(['Sales Rep'], $user->getRoleNames()->all());
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Str0ng-Passw0rd!', $user->password));
    }

    public function test_creating_a_user_is_validated(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => '',
            'email' => 'alice@example.com',
            'role' => 'Super Duper',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['name', 'email', 'role', 'password']);

        $this->assertSame(1, User::count());
    }

    public function test_admin_can_edit_a_user_and_change_their_single_role(): void
    {
        $rep = $this->userWithRole('Sales Rep');

        $this->actingAs($this->admin)->put("/admin/users/{$rep->id}", [
            'name' => 'Renamed',
            'email' => $rep->email,
            'role' => 'Viewer',
        ])->assertRedirect('/admin/users');

        $this->assertSame('Renamed', $rep->fresh()->name);
        $this->assertSame(['Viewer'], $rep->fresh()->getRoleNames()->all());
    }

    public function test_admin_can_deactivate_and_reactivate_another_user(): void
    {
        $rep = $this->userWithRole('Sales Rep');

        $this->actingAs($this->admin)->patch("/admin/users/{$rep->id}/active")->assertSessionHas('success');
        $this->assertFalse($rep->fresh()->is_active);

        $this->actingAs($this->admin)->patch("/admin/users/{$rep->id}/active")->assertSessionHas('success');
        $this->assertTrue($rep->fresh()->is_active);
    }

    public function test_admin_can_reset_a_users_password(): void
    {
        $rep = $this->userWithRole('Sales Rep');

        $this->actingAs($this->admin)->put("/admin/users/{$rep->id}/password", [
            'password' => 'Brand-New-Pass1!',
            'password_confirmation' => 'Brand-New-Pass1!',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Brand-New-Pass1!', $rep->fresh()->password));

        $this->actingAs($this->admin)->put("/admin/users/{$rep->id}/password", [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_a_user_cannot_deactivate_themselves(): void
    {
        $second = $this->userWithRole('Admin');

        $this->actingAs($second)->patch("/admin/users/{$second->id}/active")->assertSessionHas('error');

        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_the_last_active_admin_cannot_be_deactivated(): void
    {
        // A non-admin who was granted users.manage tries to deactivate the only Admin.
        $manager = $this->userWithRole('Sales Manager');
        $manager->givePermissionTo('users.manage');

        $this->actingAs($manager)->patch("/admin/users/{$this->admin->id}/active")
            ->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_the_last_active_admin_cannot_be_demoted(): void
    {
        $this->actingAs($this->admin)->put("/admin/users/{$this->admin->id}", [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => 'Viewer',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($this->admin->fresh()->hasRole('Admin'));
    }

    public function test_an_admin_can_be_demoted_or_deactivated_when_another_active_admin_exists(): void
    {
        $other = $this->userWithRole('Admin');

        $this->actingAs($this->admin)->put("/admin/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
            'role' => 'Viewer',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($other->fresh()->hasRole('Viewer'));
    }

    public function test_an_inactive_admin_does_not_count_as_an_active_admin(): void
    {
        $this->userWithRole('Admin', ['is_active' => false]);

        $this->assertTrue($this->admin->fresh()->isLastActiveAdmin());
    }

    public function test_users_cannot_be_deleted_through_the_app(): void
    {
        $this->actingAs($this->admin)->delete('/profile', ['password' => 'password'])
            ->assertStatus(405);
        $this->actingAs($this->admin)->delete("/admin/users/{$this->admin->id}")
            ->assertStatus(405);

        $this->assertNotNull($this->admin->fresh());
    }

    public function test_user_management_writes_are_forbidden_for_non_admins(): void
    {
        $rep = $this->userWithRole('Sales Rep');
        $target = $this->userWithRole('Viewer');

        $this->actingAs($rep)->patch("/admin/users/{$target->id}/active")->assertForbidden();
        $this->actingAs($rep)->put("/admin/users/{$target->id}", [
            'name' => 'x', 'email' => 'x@example.com', 'role' => 'Admin',
        ])->assertForbidden();
        $this->actingAs($rep)->put("/admin/users/{$target->id}/password", [
            'password' => 'Brand-New-Pass1!', 'password_confirmation' => 'Brand-New-Pass1!',
        ])->assertForbidden();

        $this->assertTrue($target->fresh()->is_active);
        $this->assertTrue($target->fresh()->hasRole('Viewer'));
    }
}
