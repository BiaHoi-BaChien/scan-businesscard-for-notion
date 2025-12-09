<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PasskeyManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PasskeyLoginController extends Controller
{
    public function options(Request $request, PasskeyManager $passkeyManager): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string',
            'has_navigator_credentials' => 'nullable|boolean',
            'origin_hint' => 'nullable|string',
        ]);

        $user = User::where('username', $data['username'])->first();

        $logContext = [
            'action' => 'passkey.options.start',
            'username' => $data['username'],
            'user_id' => $user?->id,
            'session_id' => $request->session()->getId(),
            'user_agent' => $request->userAgent(),
            'has_navigator_credentials' => $request->boolean('has_navigator_credentials'),
            'referer' => $request->headers->get('referer'),
            'origin_hint' => $data['origin_hint'] ?? null,
        ];

        Log::info('Passkey authentication options request started', $logContext);

        if (! $user) {
            return response()->json([
                'message' => 'ユーザーが見つかりませんでした。',
            ], 404);
        }

        try {
            return response()->json($passkeyManager->authenticationOptions($user));
        } catch (RuntimeException $exception) {
            Log::warning('Passkey authentication options error', array_merge($logContext, [
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]));

            return response()->json([
                'message' => 'パスキーの認証情報を生成できませんでした。',
            ], 422);
        }
    }

    public function login(Request $request, PasskeyManager $passkeyManager): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string',
            'data' => 'required|array',
            'state' => 'required|string',
            'has_navigator_credentials' => 'nullable|boolean',
            'origin_hint' => 'nullable|string',
        ]);

        $user = User::where('username', $data['username'])->first();

        $logContext = [
            'action' => 'passkey.login.start',
            'username' => $data['username'],
            'user_id' => $user?->id,
            'session_id' => $request->session()->getId(),
            'user_agent' => $request->userAgent(),
            'has_navigator_credentials' => $request->boolean('has_navigator_credentials'),
            'referer' => $request->headers->get('referer'),
            'origin_hint' => $data['origin_hint'] ?? null,
        ];

        Log::info('Passkey authentication login request started', $logContext);

        if (! $user) {
            return response()->json([
                'message' => 'ユーザーが見つかりませんでした。',
            ], 404);
        }

        try {
            $authenticated = $passkeyManager->authenticate($user, $data['data'], $data['state']);
        } catch (RuntimeException $exception) {
            Log::warning('Passkey authentication failed', array_merge($logContext, [
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]));

            return response()->json([
                'message' => 'パスキー認証に失敗しました。管理者に確認してください。',
            ], 422);
        }

        if (! $authenticated) {
            Log::info('Passkey authentication result', array_merge($logContext, [
                'authenticated' => false,
            ]));

            return response()->json([
                'message' => '認証に失敗しました。',
            ], 422);
        }

        Log::info('Passkey authentication result', array_merge($logContext, [
            'authenticated' => true,
        ]));

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'redirect' => route('dashboard'),
        ]);
    }
}
