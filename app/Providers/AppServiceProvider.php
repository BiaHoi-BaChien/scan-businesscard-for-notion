<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $username = Str::lower(trim((string) $request->input('username')));

            return [
                Limit::perMinute(5)->by($username.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('passkeyLogin', function (Request $request) {
            $username = Str::lower(trim((string) $request->input('username')));

            return [
                Limit::perMinute(5)->by($username.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('passkeyRegistration', function (Request $request) {
            $userId = (string) $request->user()?->getAuthIdentifier();

            return [
                Limit::perMinute(5)->by($userId.'|'.$request->ip()),
                Limit::perMinute(10)->by($userId),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        $appUrl = config('app.url');

        if (is_string($appUrl) && $appUrl !== '') {
            $forcedRootUrl = rtrim($appUrl, '/');
            $appPath = parse_url($appUrl, PHP_URL_PATH);

            if (is_string($appPath) && trim($appPath, '/') !== '') {
                URL::forceRootUrl($forcedRootUrl);
            } elseif ($this->app->bound('request')) {
                $requestBaseUrl = request()->getBaseUrl();

                if (is_string($requestBaseUrl) && trim($requestBaseUrl, '/') !== '') {
                    $forcedRootUrl .= '/'.trim($requestBaseUrl, '/');
                }

                URL::forceRootUrl($forcedRootUrl);
            } else {
                URL::forceRootUrl($forcedRootUrl);
            }

            if ($scheme = parse_url($appUrl, PHP_URL_SCHEME)) {
                URL::forceScheme($scheme);
            }
        }
    }
}
