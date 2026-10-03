<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\TraefikIngressRoute;
use RoundlyConsulting\KubernetesApi\Resources\TraefikMiddleware;
use RoundlyConsulting\KubernetesApi\Resources\TraefikServersTransport;
use RoundlyConsulting\KubernetesApi\Resources\TraefikTlsOption;
use RoundlyConsulting\KubernetesApi\Resources\TraefikTlsStore;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

dataset('traefik resources', [
    'IngressRoute' => [TraefikIngressRoute::class],
    'Middleware' => [TraefikMiddleware::class],
    'ServersTransport' => [TraefikServersTransport::class],
    'TLSOption' => [TraefikTlsOption::class],
    'TLSStore' => [TraefikTlsStore::class],
]);

it('keeps the bundled traefik group when none is configured (strict config)', function (string $resource, ?string $group) {
    // Blank means not set: null and a blank value both keep the bundled group.
    config()->set('kubernetes.traefik.group', $group);

    expect($resource::make()->getVersion())->toBe('traefik.io/v1alpha1');
})->with('traefik resources')->with(['null' => [null], 'empty' => [''], 'whitespace' => ['  ']]);

it('refuses a non-string traefik group instead of ignoring it (strict config)', function (string $resource, mixed $group) {
    config()->set('kubernetes.traefik.group', $group);

    expect(fn () => $resource::make())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [kubernetes.traefik.group] must be a non-empty string',
    );
})->with('traefik resources')->with(['int' => [3], 'list' => [['traefik.io/v1alpha1']]]);
