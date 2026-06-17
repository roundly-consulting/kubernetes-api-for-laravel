<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAccessModes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

it('uses required traits', function () {
    expect(HasAccessModes::class)->toUse([
        HasSpec::class,
    ]);
});

it('sets and gets access modes', function () {
    $instance = new class
    {
        use HasAccessModes;
    };

    expect($instance->getAccessModes())->toBe([]);

    $instance->setAccessModes(['ReadWriteOnce']);

    expect($instance->getAccessModes())->toBe(['ReadWriteOnce']);
});

it('stores access modes in spec', function () {
    $instance = new class
    {
        use HasAccessModes;
    };

    $instance->setAccessModes(['ReadWriteOnce']);

    expect($instance->getAttribute('spec'))->toBe([
        'accessModes' => ['ReadWriteOnce'],
    ]);
});
