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
        return $request->toVerify(array_filter($request->only('username')));
    }

    /**
     * Log the user in.
     */
    public function login(AssertedRequest $request): Response|JsonResponse
    {
        try {
            if ($request->login()) {
                return response()->noContent();
            }

            Log::warning('WebAuthn login rejected', [
                'username' => $request->input('username'),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
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
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => 'パスキー認証に失敗しました。管理者にお問い合わせください。',
            ], 500);
        }
    }
}
