<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Types\TraefikService;

it('creates traefik services with correct attributes', function () {
    $instance = TraefikService::to('my-api', 8000);

    expect($instance->toArray())->toBe([
        'kind' => 'Service',
        'name' => 'my-api',
        'port' => 8000,
    ]);
});

it('sends a numeric port as a number', function () {
    // Traefik reads a quoted port as a port NAME, and no service port is named "80":
    // a numeric port must go out as an integer, whichever way it was given.
    expect(TraefikService::to('web', 80)->toJson())->toBe('{"kind":"Service","name":"web","port":80}')
        ->and(TraefikService::to('web', '80')->toJson())->toBe('{"kind":"Service","name":"web","port":80}');
});

it('keeps a named port as a string', function () {
    expect(TraefikService::to('web', 'http')->toJson())->toBe('{"kind":"Service","name":"web","port":"http"}');
});
