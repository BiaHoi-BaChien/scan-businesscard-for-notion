<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_management_endpoints_are_not_available(): void
    {
        $this->get('/users')->assertNotFound();
        $this->post('/users')->assertNotFound();
        $this->patch('/users/1')->assertNotFound();
        $this->delete('/users/1')->assertNotFound();
    }
}
