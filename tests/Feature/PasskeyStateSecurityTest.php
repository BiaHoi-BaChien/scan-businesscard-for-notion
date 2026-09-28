<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PasskeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\VirtualPasskey;
use Tests\TestCase;

class PasskeyStateSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PasskeyManager $manager;

    private VirtualPasskey $authenticator;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost', 'passkeys.relying_party.id' => 'localhost']);
        $this->startSession();
        $this->travelTo(now()->startOfSecond());
        $this->user = User::create(['username' => 'passkey-user', 'password' => Hash::make('old-password')]);
        $this->manager = app(PasskeyManager::class);
        $this->authenticator = new VirtualPasskey;
    }

    public function test_real_registration_and_zero_counter_authentication_succeed(): void
    {
        $this->enroll();
        $issued = $this->manager->authenticationOptions();

        $this->assertTrue($this->manager->authenticate($this->user, $this->authenticator->assertion($issued['options'], $this->user), $issued['state']));
        $this->assertSame(0, $this->user->passkeys()->firstOrFail()->data->counter);
        $this->assertSame('Test device', $this->user->passkeys()->firstOrFail()->name);
    }

    #[DataProvider('ceremonies')]
    public function test_equivalent_encoded_state_cannot_replay_a_valid_response(string $ceremony): void
    {
        $issued = $this->issue($ceremony);
        $snapshot = Session::all();
        $response = $ceremony === 'registration'
            ? $this->authenticator->registration($issued['options'])
            : $this->authenticator->assertion($issued['options'], $this->user);
        $this->assertNotFalse($this->complete($ceremony, $issued, $response));

        // Model a second request that read the session before the first consumed it.
        Session::replace($snapshot);
        $envelope = json_decode(base64_decode($issued['state']), true);
        $reencoded = is_array($envelope)
            ? base64_encode(json_encode([...$envelope, 'ignored' => true], JSON_PRETTY_PRINT))
            : $issued['state'].'=';
        $issued['state'] = $reencoded;

        if ($ceremony === 'registration') {
            // Avoid a duplicate credential constraint concealing state reuse.
            $response = (new VirtualPasskey)->registration($issued['options']);
        }

        $this->expectException(RuntimeException::class);
        $this->complete($ceremony, $issued, $response);
    }

    public function test_only_one_request_can_consume_the_same_session_snapshot(): void
    {
        $this->enroll();
        $issued = $this->manager->authenticationOptions();
        $snapshot = Session::all();
        $assertion = $this->authenticator->assertion($issued['options'], $this->user);
        $this->assertTrue($this->manager->authenticate($this->user, $assertion, $issued['state']));
        Session::replace($snapshot);

        $this->expectException(RuntimeException::class);
        $this->manager->authenticate($this->user, $assertion, $issued['state']);
    }

    #[DataProvider('ceremonies')]
    public function test_state_is_bound_to_the_issuing_session(string $ceremony): void
    {
        $issued = $this->issue($ceremony);
        Session::flush();
        Session::regenerate();

        $this->expectException(RuntimeException::class);
        $this->complete($ceremony, $issued);
    }

    #[DataProvider('lifetimes')]
    public function test_server_enforces_the_absolute_lifetime(string $ceremony, int $seconds, bool $expired): void
    {
        $issued = $this->issue($ceremony);
        $this->travel($seconds)->seconds();

        if ($expired) {
            $this->expectException(RuntimeException::class);
        }

        $this->assertNotFalse($this->complete($ceremony, $issued));
    }

    public function test_expired_used_state_stays_invalid_after_cache_eviction(): void
    {
        $this->enroll();
        $issued = $this->manager->authenticationOptions();
        $snapshot = Session::all();
        $assertion = $this->authenticator->assertion($issued['options'], $this->user);
        $this->assertTrue($this->manager->authenticate($this->user, $assertion, $issued['state']));
        $this->travel(11)->minutes();
        Cache::flush();
        Session::replace($snapshot);

        $this->expectException(RuntimeException::class);
        $this->manager->authenticate($this->user, $assertion, $issued['state']);
    }

    public function test_registration_is_bound_to_the_original_user(): void
    {
        $issued = $this->manager->registrationOptions($this->user);
        $other = User::create(['username' => 'other', 'password' => Hash::make('password')]);

        $this->expectException(RuntimeException::class);
        $this->manager->register($other, $this->authenticator->registration($issued['options']), $issued['state']);
    }

    public function test_an_older_ceremony_is_invalid_after_options_are_reissued(): void
    {
        $issued = $this->issue('authentication');
        $this->manager->authenticationOptions();

        $this->expectException(RuntimeException::class);
        $this->complete('authentication', $issued);
    }

    public function test_registration_state_cannot_be_used_for_authentication(): void
    {
        $this->enroll();
        $authentication = $this->manager->authenticationOptions();
        $registration = $this->manager->registrationOptions($this->user);

        $this->expectException(RuntimeException::class);
        $this->manager->authenticate($this->user, $this->authenticator->assertion($authentication['options'], $this->user), $registration['state']);
    }

    #[DataProvider('ceremonies')]
    public function test_direct_callers_cannot_omit_state(string $ceremony): void
    {
        $issued = $this->issue($ceremony);
        $issued['state'] = null;

        $this->expectException(RuntimeException::class);
        $this->complete($ceremony, $issued);
    }

    public function test_failed_signature_consumes_state_and_fresh_options_allow_recovery(): void
    {
        $issued = $this->issue('authentication');
        $assertion = $this->authenticator->assertion($issued['options'], $this->user);
        $invalid = $assertion;
        $invalid['response']['signature'] = 'aW52YWxpZA';
        $this->assertFalse($this->manager->authenticate($this->user, $invalid, $issued['state']));

        try {
            $this->manager->authenticate($this->user, $assertion, $issued['state']);
            $this->fail('A failed signature must consume the ceremony.');
        } catch (RuntimeException) {
            $fresh = $this->manager->authenticationOptions();
            $this->assertTrue($this->manager->authenticate($this->user, $this->authenticator->assertion($fresh['options'], $this->user), $fresh['state']));
        }
    }

    public static function ceremonies(): array
    {
        return [['authentication'], ['registration']];
    }

    public static function lifetimes(): array
    {
        $cases = [];
        foreach (['authentication', 'registration'] as $ceremony) {
            foreach ([599, 600, 601, 1201] as $seconds) {
                $cases[$ceremony.'-'.$seconds] = [$ceremony, $seconds, $seconds >= 600];
            }
        }

        return $cases;
    }

    private function enroll(): void
    {
        $issued = $this->manager->registrationOptions($this->user);
        $this->manager->register($this->user, $this->authenticator->registration($issued['options']), $issued['state'], 'Test device');
    }

    private function issue(string $ceremony): array
    {
        if ($ceremony === 'registration') {
            return $this->manager->registrationOptions($this->user);
        }

        $this->enroll();

        return $this->manager->authenticationOptions();
    }

    private function complete(string $ceremony, array $issued, ?array $response = null): mixed
    {
        return $ceremony === 'registration'
            ? $this->manager->register($this->user, $response ?? $this->authenticator->registration($issued['options']), $issued['state'])
            : $this->manager->authenticate($this->user, $response ?? $this->authenticator->assertion($issued['options'], $this->user), $issued['state']);
    }
}
