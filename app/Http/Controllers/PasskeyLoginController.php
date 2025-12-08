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
        ]);

        $user = User::where('username', $data['username'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'ユーザーが見つかりませんでした。',
            ], 404);
        }

        try {
            return response()->json($passkeyManager->authenticationOptions($user));
        } catch (RuntimeException $exception) {
            Log::warning('Passkey authentication options error', ['message' => $exception->getMessage()]);

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
        ]);

        $user = User::where('username', $data['username'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'ユーザーが見つかりませんでした。',
            ], 404);
        }

        try {
            $authenticated = $passkeyManager->authenticate($user, $data['data'], $data['state']);
        } catch (RuntimeException $exception) {
            Log::warning('Passkey authentication failed', ['message' => $exception->getMessage()]);

            return response()->json([
                'message' => 'パスキー認証に失敗しました。管理者に確認してください。',
            ], 422);
        }

        if (! $authenticated) {
            return response()->json([
                'message' => 'パスキー認証に失敗しました。',
            ], 422);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'redirect' => route('dashboard'),
        ]);
    }
}
