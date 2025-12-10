<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_regenerates_session_id(): void
    {
        $user = User::create([
            'username' => 'user_'.Str::random(8),
            'password' => Hash::make('password123'),
        ]);

        Session::start();
        $oldSessionId = Session::getId();

        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertNotSame($oldSessionId, Session::getId());
    }
}
