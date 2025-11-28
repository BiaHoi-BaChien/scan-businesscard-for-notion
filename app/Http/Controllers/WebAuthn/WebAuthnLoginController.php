<?php

namespace App\Http\Controllers\WebAuthn;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;
use Throwable;

use function response;

class WebAuthnLoginController
{
    /**
     * Returns the challenge to assertion.
     */
    public function options(AssertionRequest $request): Responsable
    {
        return $request
            ->toVerify(array_filter($request->only('username')));
    }

    /**
     * Log the user in.
     */
    public function login(AssertedRequest $request): Response|JsonResponse
    {
        $validationDebug = $this->buildValidationDebug($request);

        if (config('app.debug')) {
            Log::debug('WebAuthn assertion received', $validationDebug);
        }

        try {
            if ($request->login()) {
                return response()->noContent();
            }

            Log::warning('WebAuthn login rejected', [
                'username' => $request->input('username'),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
                'assertion_summary' => $this->buildAssertionSummary($request),
                'validation_debug' => $validationDebug,
            ]);

            return response()->json([
                'message' => 'パスキー認証に失敗しました。再度お試しください。',
            ], 422);
        } catch (Throwable $exception) {
            Log::error('WebAuthn login failed', [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'username' => $request->input('username'),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
                'assertion_summary' => $this->buildAssertionSummary($request),
                'validation_debug' => $validationDebug,
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => 'パスキー認証に失敗しました。管理者にお問い合わせください。',
            ], 500);
        }
    }

    /**
     * Build a sanitized summary of the incoming assertion payload for debugging.
     */
    private function buildAssertionSummary(AssertedRequest $request): array
    {
        $assertion = $request->input('assertion', []);

        return [
            'id' => $assertion['id'] ?? null,
            'raw_id_length' => isset($assertion['rawId']) ? strlen($assertion['rawId']) : null,
            'response_fields' => array_keys($assertion['response'] ?? []),
            'client_data_length' => isset($assertion['response']['clientDataJSON'])
                ? strlen($assertion['response']['clientDataJSON'])
                : null,
            'authenticator_data_length' => isset($assertion['response']['authenticatorData'])
                ? strlen($assertion['response']['authenticatorData'])
                : null,
            'signature_length' => isset($assertion['response']['signature'])
                ? strlen($assertion['response']['signature'])
                : null,
            'user_handle_present' => isset($assertion['response']['userHandle'])
                ? $assertion['response']['userHandle'] !== null
                : null,
        ];
    }

    /**
     * Build detailed validation data for debugging server-side verification failures.
     */
    private function buildValidationDebug(AssertedRequest $request): array
    {
        $assertion = $request->input('assertion', []);
        $clientDataJson = $assertion['response']['clientDataJSON'] ?? null;
        $authenticatorData = $assertion['response']['authenticatorData'] ?? null;

        return [
            'expected' => [
                'rp_id' => config('webauthn.relying_party.id'),
                'origin' => config('webauthn.origins'),
                'session_challenge' => $this->extractSessionChallenge($request),
            ],
            'client_data' => $this->parseClientDataJson($clientDataJson),
            'authenticator_data' => $this->parseAuthenticatorData($authenticatorData),
        ];
    }

    private function parseClientDataJson(?string $encodedClientData): array
    {
        if (! $encodedClientData) {
            return [];
        }

        $decoded = $this->decodeBase64Url($encodedClientData);

        if ($decoded === null) {
            return ['decode_error' => 'clientDataJSON could not be base64url-decoded'];
        }

        $data = json_decode($decoded, true);

        if (! is_array($data)) {
            return ['decode_error' => 'clientDataJSON is not valid JSON'];
        }

        return [
            'type' => $data['type'] ?? null,
            'origin' => $data['origin'] ?? null,
            'challenge' => $data['challenge'] ?? null,
            'cross_origin' => $data['crossOrigin'] ?? null,
            'raw_length' => strlen($decoded),
        ];
    }

    private function parseAuthenticatorData(?string $encodedAuthenticatorData): array
    {
        if (! $encodedAuthenticatorData) {
            return [];
        }

        $binary = $this->decodeBase64Url($encodedAuthenticatorData);

        if ($binary === null) {
            return ['decode_error' => 'authenticatorData could not be base64url-decoded'];
        }

        $rpIdHash = substr($binary, 0, 32);
        $flagsByte = ord($binary[32] ?? "\0");
        $signCountBytes = substr($binary, 33, 4);
        $signCount = $signCountBytes !== false && strlen($signCountBytes) === 4
            ? unpack('N', $signCountBytes)[1]
            : null;

        return [
            'rp_id_hash_hex' => $rpIdHash ? bin2hex($rpIdHash) : null,
            'flags' => [
                'user_present' => (bool) ($flagsByte & 0b00000001),
                'user_verified' => (bool) ($flagsByte & 0b00000100),
                'backup_eligible' => (bool) ($flagsByte & 0b00010000),
                'backup_state' => (bool) ($flagsByte & 0b00100000),
                'attested_credential_data' => (bool) ($flagsByte & 0b01000000),
                'extension_data_included' => (bool) ($flagsByte & 0b10000000),
            ],
            'sign_count' => $signCount,
            'raw_length' => strlen($binary),
        ];
    }

    private function extractSessionChallenge(AssertedRequest $request): ?string
    {
        $sessionKey = config('webauthn.challenge.key');
        $challenge = $sessionKey ? $request->session()->get($sessionKey) : null;

        if (is_array($challenge)) {
            if (isset($challenge['challenge'])) {
                return $challenge['challenge'];
            }

            if (isset($challenge['value'])) {
                return $challenge['value'];
            }
        }

        return is_string($challenge) ? $challenge : null;
    }

    private function decodeBase64Url(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $remainder = strlen($value) % 4;

        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/')) ?: null;
    }
}
