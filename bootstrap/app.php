<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\AuthenticateSession;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\PreventSearchIndexing;
use App\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(PreventSearchIndexing::class);

        $middleware->group('web', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            SubstituteBindings::class,
        ]);

        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'admin' => AdminOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (
            AuthenticationException $exception,
            Request $request
        ) {
            return $request->expectsJson()
                ? response()->json(['message' => '認証が必要です。'], 401)
                : redirect()->guest(route('login.form'));
        });

        $exceptions->render(function (
            ThrottleRequestsException $exception,
            Request $request
        ) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => '試行回数が多すぎます。しばらく待ってから再試行してください。',
                ], 429, $exception->getHeaders());
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'サーバー内部でエラーが発生しました。',
                ], 500);
            }
        });
    })->create();
