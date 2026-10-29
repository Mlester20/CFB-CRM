<?php

namespace Tests\Feature\Rbac;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class ActiveUserTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_a_deactivated_user_cannot_sign_in(): void
    {
        $user = $this->userWithRole('Sales Rep', ['is_active' => false]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    public function test_deactivated_and_wrong_password_errors_are_identical(): void
    {
        $inactive = $this->userWithRole('Sales Rep', ['is_active' => false]);
        $active = $this->userWithRole('Sales Rep');

        $this->post('/login', ['email' => $inactive->email, 'password' => 'password']);
        $first = session('errors')->get('email');

        $this->post('/login', ['email' => $active->email, 'password' => 'wrong-password']);
        $second = session('errors')->get('email');

        $this->assertSame($first, $second);
    }

    public function test_an_active_user_can_sign_in_and_the_session_is_regenerated(): void
    {
        $user = $this->userWithRole('Sales Rep');

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
    }

    public function test_a_signed_in_user_who_is_deactivated_is_signed_out_on_the_next_request(): void
    {
        $user = $this->userWithRole('Sales Rep');

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $user = $this->userWithRole('Sales Rep');

        foreach (range(1, 5) as $ignored) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
