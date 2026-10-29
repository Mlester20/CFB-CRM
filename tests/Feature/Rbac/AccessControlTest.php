<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public static function protectedPages(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'users list' => ['/admin/users'],
            'users create' => ['/admin/users/create'],
            'roles list' => ['/admin/roles'],
        ];
    }

    #[DataProvider('protectedPages')]
    public function test_guests_are_redirected_to_login(string $url): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    public static function adminOnlyPages(): array
    {
        return [
            'users list' => ['get', '/admin/users'],
            'users create form' => ['get', '/admin/users/create'],
            'users store' => ['post', '/admin/users'],
            'roles list' => ['get', '/admin/roles'],
        ];
    }

    #[DataProvider('adminOnlyPages')]
    public function test_non_admin_roles_get_403_on_management_routes(string $method, string $url): void
    {
        foreach (['Sales Manager', 'Sales Rep', 'Viewer'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->{$method}($url)
                ->assertForbidden();
        }
    }

    public function test_users_without_a_role_are_denied_by_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/roles')->assertForbidden();
    }

    public function test_admin_can_reach_management_screens(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/users/create')->assertOk();
        $this->actingAs($admin)->get('/admin/roles')->assertOk();
        $this->actingAs($admin)->get('/dashboard')->assertOk();
    }

    public function test_every_seeded_role_can_open_the_dashboard(): void
    {
        foreach (['Admin', 'Sales Manager', 'Sales Rep', 'Viewer'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/dashboard')->assertOk();
        }
    }

    public function test_forbidden_requests_render_the_inertia_403_page_without_redirecting(): void
    {
        $this->actingAs($this->userWithRole('Viewer'))
            ->get('/admin/users')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Errors/Forbidden'));
    }
}
