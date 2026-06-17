<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAttributes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasName;

it('uses required traits', function () {
    expect(HasName::class)->toUse([
        HasAttributes::class,
    ]);
});

it('sets and gets name', function () {
    $instance = new class
    {
        use HasName;
    };

    $instance->setName('beta');

    expect($instance->getName())->toBe('beta');
});

it('uses attribute metadata to store and retrieve name', function () {
    $instance = new class
    {
        use HasName;
    };

    $instance->setAttribute('metadata.name', 'beta');
    expect($instance->getName())->toBe('beta');

    $instance->setName('staging');
    expect($instance->getAttribute('metadata.name'))->toBe('staging');
});
