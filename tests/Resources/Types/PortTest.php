<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Types\Port;

it('creates instance and sets attributes for http port', function () {
    $instance = Port::http(containerPort: 8000);

    expect($instance->toArray())->toBe([
        'port' => 80,
        'protocol' => 'TCP',
        'targetPort' => 8000,
    ]);
});
