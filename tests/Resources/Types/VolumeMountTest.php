<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Types\VolumeMount;

it('creates instance and sets correct attributes', function () {
    $instance = new VolumeMount;

    $instance->mountTo('/etc/volume');

    expect($instance->toArray())->toBe([
        'mountPath' => '/etc/volume',
    ]);

    $instance->mountTo('/etc/volume', 'cert');

    expect($instance->toArray())->toBe([
        'mountPath' => '/etc/volume',
        'subPath' => 'cert',
    ]);
});
