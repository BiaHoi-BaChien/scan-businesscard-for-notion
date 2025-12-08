<?php

namespace App\Actions;

use Illuminate\Support\Arr;
use Spatie\LaravelPasskeys\Actions\ConfigureCeremonyStepManagerFactoryAction as BaseAction;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;

class ConfigureCeremonyStepManagerFactoryAction extends BaseAction
{
    public function execute(): CeremonyStepManagerFactory
    {
        $factory = parent::execute();

        $appUrl = config('app.url', 'http://localhost');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'http';
        $host = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';
        $port = parse_url($appUrl, PHP_URL_PORT);

        $baseOrigin = rtrim(sprintf('%s://%s%s', $scheme, $host, $port ? ':' . $port : ''), '/');

        $allowedOrigins = [$baseOrigin];

        if ($host === 'localhost' || $host === '127.0.0.1') {
            // Allow HTTP on localhost/127.0.0.1 for local development (otherwise the validator requires HTTPS).
            $factory->setSecuredRelyingPartyId(['localhost', '127.0.0.1']);

            $fallbackPorts = $port ? [$port] : [8000, 5173];
            foreach (['localhost', '127.0.0.1'] as $localHost) {
                foreach ($fallbackPorts as $p) {
                    $allowedOrigins[] = "http://{$localHost}" . ($p ? ':' . $p : '');
                    $allowedOrigins[] = "https://{$localHost}" . ($p ? ':' . $p : '');
                }
                $allowedOrigins[] = "http://{$localHost}";
                $allowedOrigins[] = "https://{$localHost}";
            }
        }

        $factory->setAllowedOrigins(array_values(array_unique(Arr::whereNotNull($allowedOrigins))));

        return $factory;
    }
}
