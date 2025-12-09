<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugController extends Controller
{
    public function store(Request $request)
    {
        $payload = $request->all();

        Log::debug('Passkey debug event', [
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'visibility' => $payload['visibilityState'] ?? null,
            'event' => $payload['event'] ?? 'unknown',
            'context' => $payload,
        ]);

        return response()->json(['status' => 'ok']);
    }
}
