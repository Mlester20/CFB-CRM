<?php

namespace Tests\Feature\Rbac;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class SharedPropsTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_shared_props_contain_the_signed_in_users_role_and_permissions_only(): void
    {
        $rep = $this->userWithRole('Sales Rep', ['email' => 'rep@example.com']);
        $admin = $this->userWithRole('Admin', ['email' => 'secret-admin@example.com']);

        $props = $this->actingAs($rep)->get('/dashboard')->viewData('page')['props'];

        $this->assertSame(['id' => $rep->id, 'name' => $rep->name, 'email' => 'rep@example.com'], $props['auth']['user']);
        $this->assertSame('Sales Rep', $props['auth']['role']);
        $this->assertCount(24, $props['auth']['permissions']);
        $this->assertContains('leads.create', $props['auth']['permissions']);
        $this->assertNotContains('leads.export', $props['auth']['permissions']);
        $this->assertNotContains('users.manage', $props['auth']['permissions']);

        $json = json_encode($props);
        $this->assertStringNotContainsString($admin->email, $json);
        $this->assertStringNotContainsString($rep->password, $json);
        $this->assertStringNotContainsString('remember_token', $json);
        $this->assertStringNotContainsString('"password"', $json);
    }

    public function test_admin_receives_every_permission(): void
    {
        $admin = $this->userWithRole('Admin');

        $props = $this->actingAs($admin)->get('/dashboard')->viewData('page')['props'];

        $this->assertSame('Admin', $props['auth']['role']);
        $this->assertCount(38, $props['auth']['permissions']);
    }

    public function test_guests_get_no_user_and_no_permissions(): void
    {
        $props = $this->get('/login')->viewData('page')['props'];

        $this->assertNull($props['auth']['user']);
        $this->assertNull($props['auth']['role']);
        $this->assertSame([], $props['auth']['permissions']);
    }

    public function test_flash_messages_are_shared(): void
    {
        $this->withSession(['success' => 'Saved.', 'error' => 'Nope.']);

        $props = $this->actingAs($this->userWithRole('Viewer'))->get('/dashboard')->viewData('page')['props'];

        $this->assertSame('Saved.', $props['flash']['success']);
        $this->assertSame('Nope.', $props['flash']['error']);
    }
}
