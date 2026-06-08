<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
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
            'encrypted_password' => base64_encode('password123'),
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

    public function test_generated_urls_keep_configured_subdirectory(): void
    {
        $baseUrl = 'https://clb-biahoi.net/notion_scan_business_card';
        config(['app.url' => $baseUrl]);
        URL::forceRootUrl($baseUrl);
        URL::forceScheme('https');

        $this->assertSame('login', app('router')->getRoutes()->getByName('login.form')->uri());
        $this->assertSame($baseUrl.'/dashboard', route('dashboard'));
    }
}
