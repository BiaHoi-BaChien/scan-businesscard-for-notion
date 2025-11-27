<?php

namespace App\Http\Controllers\WebAuthn;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;
use Throwable;

use function response;

class WebAuthnRegisterController
{
    /**
     * Returns a challenge to be verified by the user device.
     */
    public function options(AttestationRequest $request): Responsable
    {
        return $request
            ->fastRegistration()
            ->allowDuplicates()
            ->toCreate();
    }

    /**
     * Registers a device for further WebAuthn authentication.
     */
    public function register(AttestedRequest $request): Response
    {
        try {
            $request->save();
        } catch (ValidationException $exception) {
            Log::warning('WebAuthn registration validation failed', [
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
                'user_id' => optional($request->user())->id,
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'パスキーの登録に失敗しました。再度お試しください。',
                'errors' => $exception->errors(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('WebAuthn registration failed', [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'user_id' => optional($request->user())->id,
                'user_agent' => $request->userAgent(),
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => 'パスキーの登録に失敗しました。',
            ], 500);
        }

        return response()->noContent();
    }
}
