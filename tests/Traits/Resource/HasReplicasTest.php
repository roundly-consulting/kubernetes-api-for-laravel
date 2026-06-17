<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasReplicas;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

it('uses required traits', function () {
    expect(HasReplicas::class)->toUse([
        HasSpec::class,
    ]);
});

it('sets and gets replicas count having defualt 1', function () {
    $instance = new class
    {
        use HasReplicas;
    };

    expect($instance->getReplicas())->toBe(1);

    $instance->setReplicas(5);

    expect($instance->getReplicas())->toBe(5);
});

it('stores replicas information in spec', function () {
    $instance = new class
    {
        use HasReplicas;
    };

    $instance->setSpec('replicas', 5);
    expect($instance->getReplicas())->toBe(5);

    $instance->setReplicas(3);
    expect($instance->getSpec('replicas'))->toBe(3);
});
