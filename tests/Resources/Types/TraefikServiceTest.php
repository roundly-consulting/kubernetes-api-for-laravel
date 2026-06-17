<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Types\TraefikService;

it('creates traefik services with correct attributes', function () {
    $instance = TraefikService::to('my-api', '8000');

    expect($instance->toArray())->toBe([
        'kind' => 'Service',
        'name' => 'my-api',
        'port' => '8000',
    ]);
});
