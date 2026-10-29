<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL sends visitors to the login page.
     */
    public function test_the_home_page_redirects_to_the_login_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
