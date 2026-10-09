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
                'port' => 8000,
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
            'port' => 8000,
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

it('refuses a host that would break out of the host rule', function (string $host) {
    // `a.test`) || Host(`victim.test` used to inject a second matcher into the rule.
    TraefikRoute::hostRule($host);
})->with([
    'backtick' => ['a.test`) || Host(`victim.test'],
    'space' => ['a.test b.test'],
    'parenthesis' => ['a.test)'],
    'empty' => [''],
    'leading hyphen' => ['-a.test'],
    'label over 63 characters' => [str_repeat('a', 64).'.test'],
    'trailing newline' => ["a.test\n"],
])->throws(InvalidArgumentException::class);

it('refuses a path that would break out of the path rule', function (string $path) {
    TraefikRoute::pathRule($path);
})->with([
    'backtick' => ['/x`) || PathPrefix(`/'],
    'whitespace' => ["/x\n"],
    'no leading slash' => ['api'],
])->throws(InvalidArgumentException::class);

it('accepts ordinary hosts and paths', function () {
    expect(TraefikRoute::hostRule('Shop-1.example.com')->getAttribute('match'))->toBe('Host(`Shop-1.example.com`)')
        ->and(TraefikRoute::hostRule('10.0.0.1')->getAttribute('match'))->toBe('Host(`10.0.0.1`)')
        ->and(TraefikRoute::pathRule('/')->getAttribute('match'))->toBe('PathPrefix(`/`)')
        ->and(TraefikRoute::pathRule('/api/v1/users-list_%20.json')->getAttribute('match'))->toBe('PathPrefix(`/api/v1/users-list_%20.json`)');
});
