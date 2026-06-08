<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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
