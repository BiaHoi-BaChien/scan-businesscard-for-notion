<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\VirtualPasskey;
use Tests\TestCase;

class PasswordResetSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost', 'passkeys.relying_party.id' => 'localhost']);
        $this->user = User::create(['username' => 'admin', 'password' => Hash::make('old-password')]);
    }

    #[DataProvider('loginMethods')]
    public function test_password_change_invalidates_sessions_immediately_after_login(string $method): void
    {
        $this->login($method);
        $snapshot = Session::all();
        $this->resetPassword();
        Auth::forgetGuards();

        $this->get(route('dashboard'))->assertRedirect(route('login.form'));
        $this->assertGuest();

        // A second device carrying the same old authentication snapshot must also fail.
        Session::replace($snapshot);
        Auth::forgetGuards();
        $this->getJson(route('dashboard'))->assertUnauthorized();
        $this->assertGuest();
    }

    #[DataProvider('loginMethods')]
    public function test_password_change_invalidates_remember_cookie(string $method): void
    {
        $response = $this->login($method);
        $name = Auth::guard()->getRecallerName();
        $cookie = $response->getCookie($name, false);
        $this->assertNotNull($cookie);
        $oldToken = $this->user->fresh()->getRememberToken();
        $this->resetPassword();
        Session::invalidate();
        Auth::forgetGuards();

        $this->withUnencryptedCookie($name, $cookie->getValue())
            ->get(route('dashboard'))->assertRedirect(route('login.form'));
        $this->assertGuest();
        $this->assertNotSame($oldToken, $this->user->fresh()->getRememberToken());
    }

    #[DataProvider('loginMethods')]
    public function test_current_sessions_and_remember_cookies_remain_usable(string $method): void
    {
        $response = $this->login($method);
        $name = Auth::guard()->getRecallerName();
        $cookie = $response->getCookie($name, false);
        $this->assertNotNull($cookie);
        Auth::forgetGuards();
        $this->get(route('dashboard'))->assertOk();
        Session::invalidate();
        Auth::forgetGuards();

        $this->withUnencryptedCookie($name, $cookie->getValue())
            ->get(route('dashboard'))->assertOk();
        $this->assertTrue(Auth::viaRemember());
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_legacy_session_without_password_snapshot_requires_login(): void
    {
        $this->login('password');
        Session::forget('password_hash_web');
        Auth::forgetGuards();

        $this->getJson(route('dashboard'))->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_new_password_works_and_old_password_does_not(): void
    {
        $this->resetPassword();
        $this->post(route('login'), ['username' => $this->user->username, 'password' => 'old-password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post(route('login'), ['username' => $this->user->username, 'password' => 'new-password'])
            ->assertRedirect(route('dashboard'));
        Auth::forgetGuards();
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_registered_passkey_can_start_a_new_login_after_password_change(): void
    {
        $authenticator = $this->registerPasskey();
        $this->resetPassword();
        $this->passkeyLogin($authenticator)->assertOk();
        Auth::forgetGuards();
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_changing_another_user_does_not_invalidate_the_current_session(): void
    {
        $this->login('password');
        $this->assertSame(0, Artisan::call('user:create-admin', ['--username' => 'other', '--password' => 'other-password']));
        Auth::forgetGuards();
        $this->get(route('dashboard'))->assertOk();
        $this->assertAuthenticatedAs($this->user);
        $other = User::where('username', 'other')->firstOrFail();
        $this->assertTrue($other->is_admin);
        $this->assertTrue(Hash::check('other-password', $other->password));
    }

    public static function loginMethods(): array
    {
        return [['password'], ['passkey']];
    }

    private function login(string $method): TestResponse
    {
        if ($method === 'passkey') {
            return $this->passkeyLogin($this->registerPasskey())->assertOk();
        }

        return $this->post(route('login'), [
            'username' => $this->user->username,
            'password' => 'old-password',
        ])->assertRedirect(route('dashboard'));
    }

    private function registerPasskey(): VirtualPasskey
    {
        $authenticator = new VirtualPasskey;
        $this->actingAs($this->user);
        $issued = $this->postJson(route('passkeys.register.options'))->assertOk()->json();
        $this->postJson(route('passkeys.register'), [
            'data' => $authenticator->registration($issued['options']),
            'state' => $issued['state'],
            'name' => 'Test device',
        ])->assertOk();
        $this->post(route('logout'))->assertRedirect(route('login.form'));
        Auth::forgetGuards();

        return $authenticator;
    }

    private function passkeyLogin(VirtualPasskey $authenticator): TestResponse
    {
        $issued = $this->postJson(route('passkeys.options'), ['username' => $this->user->username])->assertOk()->json();

        return $this->postJson(route('passkeys.login'), [
            'username' => $this->user->username,
            'data' => $authenticator->assertion($issued['options'], $this->user),
            'state' => $issued['state'],
        ]);
    }

    private function resetPassword(): void
    {
        $this->assertSame(0, Artisan::call('user:create-admin', [
            '--username' => $this->user->username,
            '--password' => 'new-password',
        ]));
    }
}
