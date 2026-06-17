<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\TraefikIngressRoute;
use RoundlyConsulting\KubernetesApi\Resources\Types\TraefikRoute;

it('extends resource class', function () {
    expect(TraefikIngressRoute::class)->toUse(Resource::class);
});

it('has correct kind', function () {
    expect(TraefikIngressRoute::make()->getKind())->toBe('IngressRoute');
});

it('has correct version', function () {
    expect(TraefikIngressRoute::make()->getVersion())->toBe('traefik.containo.us/v1alpha1');
});

it('uses namespaces', function () {
    expect(TraefikIngressRoute::make()->usesNamespaces())->toBeTrue();
});

it('gets and sets entry points', function () {
    $ingressRoute = TraefikIngressRoute::make();

    expect($ingressRoute->setEntryPoints(['web', 'websecure']))
        ->toBeInstanceOf(TraefikIngressRoute::class)
        ->and($ingressRoute->getEntryPoints())
        ->toBe([
            'web',
            'websecure',
        ]);
});

it('gets and sets routes', function () {
    $ingressRoute = TraefikIngressRoute::make()
        ->addRoute(
            TraefikRoute::hostRule('something.tld')
        );

    $routes = $ingressRoute->getRoutes();

    expect($ingressRoute->toArray())
        ->toBe([
            'apiVersion' => 'traefik.containo.us/v1alpha1',
            'kind' => 'IngressRoute',
            'spec' => [
                'routes' => [
                    [
                        'kind' => 'Rule',
                        'match' => 'Host(`something.tld`)',
                    ],
                ],
            ],
        ])
        ->and($routes)
        ->toBeArray()
        ->toHaveLength(1)
        ->and($routes[0])
        ->toBeInstanceOf(TraefikRoute::class)
        ->toArray()
        ->toBe([
            'kind' => 'Rule',
            'match' => 'Host(`something.tld`)',
        ]);
});
