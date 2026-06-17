<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\TraefikMiddleware;

it('extends resource class', function () {
    expect(TraefikMiddleware::class)->toUse(Resource::class);
});

it('has correct kind', function () {
    expect(TraefikMiddleware::make()->getKind())->toBe('Middleware');
});

it('has correct version', function () {
    expect(TraefikMiddleware::make()->getVersion())->toBe('traefik.io/v1alpha1');
});

it('uses namespaces', function () {
    expect(TraefikMiddleware::make()->usesNamespaces())->toBeTrue();
});

it('honours the configured traefik api group for back-compat', function () {
    config()->set('kubernetes.traefik.group', 'traefik.containo.us/v1alpha1');

    expect(TraefikMiddleware::make()->getVersion())->toBe('traefik.containo.us/v1alpha1');
});

it('sets redirect to scheme', function () {
    $ingressRoute = TraefikMiddleware::make()->setName('redirects')->redirectToScheme();

    expect($ingressRoute)
        ->toBeInstanceOf(TraefikMiddleware::class)
        ->toArray()
        ->toBe([
            'apiVersion' => 'traefik.io/v1alpha1',
            'kind' => 'Middleware',
            'metadata' => [
                'name' => 'redirects',
            ],
            'spec' => [
                'redirectScheme' => [
                    'scheme' => 'https',
                    'permanent' => true,
                ],
            ],
        ]);
});
