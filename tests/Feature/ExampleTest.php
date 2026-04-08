<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example - unauthenticated / redirects to login.
     */
    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    /**
     * Test login page is accessible.
     */
    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }
}
