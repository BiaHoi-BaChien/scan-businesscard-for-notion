<?php

namespace App\Http\Controllers;

use App\Services\PasskeyManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PasskeyRegistrationController extends Controller
{
    public function options(Request $request, PasskeyManager $passkeyManager): JsonResponse
    {
        try {
            return response()->json($passkeyManager->registrationOptions($request->user()));
        } catch (RuntimeException $exception) {
            Log::warning('Passkey registration options error', ['message' => $exception->getMessage()]);

            return response()->json([
                'message' => 'パスキーの登録情報を取得できませんでした。環境を確認してください。',
            ], 422);
        }
    }

    public function store(Request $request, PasskeyManager $passkeyManager): JsonResponse
    {
        $data = $request->validate([
            'data' => 'required|array',
            'name' => 'nullable|string|max:255',
        ]);

        try {
            $passkey = $passkeyManager->register(
                $request->user(),
                $data['data'],
                $data['name'] ?? null,
            );

            return response()->json([
                'success' => true,
                'passkey' => $passkey,
            ]);
        } catch (RuntimeException $exception) {
            Log::warning('Passkey registration failed', ['message' => $exception->getMessage()]);

            return response()->json([
                'message' => 'パスキーの登録に失敗しました。管理者に確認してください。',
            ], 422);
        }
    }
}
