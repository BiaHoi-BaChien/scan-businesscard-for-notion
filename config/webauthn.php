<?php

// Resolve a sensible default relying party ID from the application URL or the current request
// host when available. This prevents WebAuthn from failing with a `NotAllowedError` when the
// configured host (or a missing `WEBAUTHN_ID`) doesn't match the actual domain being used.
$defaultRelyingPartyId = parse_url(config('app.url'), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? null);

// Build a default origin (scheme + host) for WebAuthn ceremonies. If the application URL is
// unavailable or misconfigured, fall back to the current request host to avoid 422 errors caused
// by origin mismatches when serving the app from a different domain or subdirectory.
$appUrl = config('app.url');
$appHost = parse_url($appUrl, PHP_URL_HOST);
$appScheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';

$defaultOrigin = $appHost ? sprintf('%s://%s', $appScheme, $appHost) : null;

if (! $defaultOrigin) {
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
    $forwardedHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? null;

    if ($forwardedProto || $forwardedHost) {
        $scheme = $forwardedProto ? trim(explode(',', $forwardedProto)[0]) : null;
        $host = $forwardedHost ? trim(explode(',', $forwardedHost)[0]) : null;

        $scheme ??= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host ??= $_SERVER['HTTP_HOST'] ?? null;

        if ($host) {
            $defaultOrigin = sprintf('%s://%s', $scheme, $host);
        }
    }
}

if (! $defaultOrigin && function_exists('request')) {
    $request = request();

    if ($request) {
        $defaultOrigin = $request->getSchemeAndHttpHost();
    }
}

if (! $defaultOrigin && isset($_SERVER['HTTP_HOST'])) {
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $defaultOrigin = sprintf('%s://%s', $isSecure ? 'https' : 'http', $_SERVER['HTTP_HOST']);
}

return [

    /*
    |--------------------------------------------------------------------------
    | Relying Party
    |--------------------------------------------------------------------------
    |
    | We will use your application information to inform the device who is the
    | relying party. While only the name is enough, you can further set the
    | a custom domain as ID and even an icon image data encoded as BASE64.
    |
    */

    'relying_party' => [
        'name' => env('WEBAUTHN_NAME', config('app.name')),
        'id' => env('WEBAUTHN_ID', $defaultRelyingPartyId),
    ],

    /*
    |--------------------------------------------------------------------------
    | Origins
    |--------------------------------------------------------------------------
    |
    | By default, only your application domain is used as a valid origin for
    | all ceremonies. If you are using your app as a backend for an app or
    | UI you may set additional origins to check against the ceremonies.
    |
    | For multiple origins, separate them using comma, like `foo,bar`.
    */

    'origins' => env('WEBAUTHN_ORIGINS', $defaultOrigin),

    /*
    |--------------------------------------------------------------------------
    | Challenge configuration
    |--------------------------------------------------------------------------
    |
    | When making challenges your application needs to push at least 16 bytes
    | of randomness. Since we need to later check them, we'll also store the
    | bytes for a small amount of time inside this current request session.
    |
    | @see https://www.w3.org/TR/webauthn-2/#sctn-cryptographic-challenges
    |
    */

    'challenge' => [
        'bytes' => 16,
        'timeout' => 60,
        'key' => '_webauthn',
    ],
];
