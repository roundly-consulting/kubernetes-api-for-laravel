<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Types\TraefikRoute;
use RoundlyConsulting\KubernetesApi\Resources\Types\TraefikService;

it('creates instance and sets attributes for host rule', function () {
    $instance = TraefikRoute::hostRule('something.tld');

    expect($instance->toArray())->toBe([
        'kind' => 'Rule',
        'match' => 'Host(`something.tld`)',
    ]);
});

it('creates instance and sets attributes for path rule', function () {
    $instance = TraefikRoute::pathRule('/api/v1');

    expect($instance->toArray())->toBe([
        'kind' => 'Rule',
        'match' => 'PathPrefix(`/api/v1`)',
    ]);
});

it('creates instance and sets attributes for match rule', function () {
    $instance = TraefikRoute::matchRule('Host(`something.tld`) && PathPrefix(`/api/v1`)');

    expect($instance->toArray())->toBe([
        'kind' => 'Rule',
        'match' => 'Host(`something.tld`) && PathPrefix(`/api/v1`)',
    ]);
});

it('adds service', function () {
    $instance = TraefikRoute::hostRule('something.tld')
        ->addService(TraefikService::to('my-api', '8000'));

    expect($instance->toArray())->toBe([
        'kind' => 'Rule',
        'match' => 'Host(`something.tld`)',
        'services' => [
            [
                'kind' => 'Service',
                'name' => 'my-api',
                'port' => '8000',
            ],
        ],
    ]);
});

it('returns services', function () {
    $instance = TraefikRoute::hostRule('something.tld')
        ->addService(TraefikService::to('my-api', '8000'));

    $services = $instance->getServices();

    expect($services)
        ->toBeArray()
        ->toHaveLength(1)
        ->and($services[0])
        ->toBeInstanceOf(TraefikService::class)
        ->toArray()
        ->toBe([
            'kind' => 'Service',
            'name' => 'my-api',
            'port' => '8000',
        ]);
});

it('adds middleware', function () {
    $instance = TraefikRoute::hostRule('something.tld')
        ->addMiddleware('redirect-to-https', 'traefik');

    expect($instance->toArray())->toBe([
        'kind' => 'Rule',
        'match' => 'Host(`something.tld`)',
        'middlewares' => [
            [
                'name' => 'redirect-to-https',
                'namespace' => 'traefik',
            ],
        ],
    ]);
});

it('returns middlewares', function () {
    $instance = TraefikRoute::hostRule('something.tld')
        ->addMiddleware('redirect-to-https', 'traefik');

    expect($instance->getMiddlewares())
        ->toBeArray()
        ->toHaveLength(1)
        ->toBe([
            [
                'name' => 'redirect-to-https',
                'namespace' => 'traefik',
            ],
        ]);
});
