<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

    public function test_login_is_rate_limited_by_username_and_ip(): void
    {
        $user = User::create([
            'username' => 'user_'.Str::random(8),
            'password' => Hash::make('password123'),
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->post(route('login'), [
                    'username' => $user->username,
                    'password' => 'wrong-password',
                ])
                ->assertRedirect();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post(route('login'), [
                'username' => $user->username,
                'password' => 'wrong-password',
            ])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_login_is_rate_limited_across_usernames_from_same_ip(): void
    {
        $ipAddress = '203.0.113.20';

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ipAddress])
                ->post(route('login'), [
                    'username' => 'unknown-user-'.$attempt,
                    'password' => 'wrong-password',
                ])
                ->assertRedirect();
        }

        $this->withServerVariables(['REMOTE_ADDR' => $ipAddress])
            ->post(route('login'), [
                'username' => 'another-unknown-user',
                'password' => 'wrong-password',
            ])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
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

    public function test_generated_urls_keep_request_subdirectory_when_app_url_has_no_path(): void
    {
        config(['app.url' => 'https://clb-biahoi.net']);

        $request = Request::create(
            'https://attacker.example/notion_scan_business_card/dashboard',
            'GET',
            server: [
                'SCRIPT_NAME' => '/notion_scan_business_card/index.php',
                'SCRIPT_FILENAME' => '/home/u685478147/public_html/public_html/notion_scan_business_card/index.php',
            ],
        );

        $this->app->instance('request', $request);
        URL::setRequest($request);
        (new AppServiceProvider($this->app))->boot();

        $this->assertSame(
            'https://clb-biahoi.net/notion_scan_business_card/login',
            route('login.form'),
        );
    }
}
