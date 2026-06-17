<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasMountOptions;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

it('uses required traits', function () {
    expect(HasMountOptions::class)->toUse([
        HasSpec::class,
    ]);
});

it('sets and gets mount options', function () {
    $instance = new class
    {
        use HasMountOptions;
    };

    expect($instance->getMountOptions())->toBe([]);

    $instance->setMountOptions(['hard']);

    expect($instance->getMountOptions())->toBe(['hard']);
});

it('stores mount options in spec', function () {
    $instance = new class
    {
        use HasMountOptions;
    };

    $instance->setMountOptions(['hard']);

    expect($instance->getAttribute('spec'))->toBe([
        'mountOptions' => ['hard'],
    ]);
});
