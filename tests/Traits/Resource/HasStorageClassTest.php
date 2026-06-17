<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStorageClass;

it('uses required traits', function () {
    expect(HasStorageClass::class)->toUse([
        HasSpec::class,
    ]);
});

it('gets storage class name from spec', function () {
    $instance = new class
    {
        use HasStorageClass;
    };

    expect($instance->getStorageClassName())->toBeNull();

    $instance->setSpec('storageClassName', 'standard');

    expect($instance->getStorageClassName())->toBe('standard');
});

it('sets storage class name to spec', function () {
    $instance = new class
    {
        use HasStorageClass;
    };

    $instance->setStorageClassName('standard');

    expect($instance->getSpec('storageClassName'))->toBe('standard');
});
