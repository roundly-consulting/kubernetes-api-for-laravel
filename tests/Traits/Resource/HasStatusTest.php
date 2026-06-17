<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAttributes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;

it('uses required traits', function () {
    expect(HasStatus::class)->toUse([
        HasAttributes::class,
    ]);
});

it('retrieves status information from status attribute', function () {
    $instance = new class
    {
        use HasStatus;
    };

    expect($instance->getStatus('availableReplicas', 1))->toBe(1);

    $instance->setAttribute('status.availableReplicas', 8);

    expect($instance->getStatus('availableReplicas', 1))->toBe(8);
});
