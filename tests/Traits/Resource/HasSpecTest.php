<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAttributes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

it('uses required traits', function () {
    expect(HasSpec::class)->toUse([
        HasAttributes::class,
    ]);
});

it('sets spec in attributes', function () {
    $instance = new class
    {
        use HasSpec;
    };

    $instance->setSpec('replicas', 4);

    expect($instance->getAttribute('spec'))->toBe([
        'replicas' => 4,
    ]);
});

it('adds to spec', function () {
    $instance = new class
    {
        use HasSpec;
    };

    $instance->addToSpec('test', 'something');

    expect($instance->getAttribute('spec'))->toBe([
        'test' => ['something'],
    ]);

    $instance->addToSpec('test', 'else');

    expect($instance->getAttribute('spec'))->toBe([
        'test' => ['something', 'else'],
    ]);
});

it('adds to spec without wrapping as array', function () {
    $instance = new class
    {
        use HasSpec;
    };

    $instance->addToSpec('test', ['something'], false);

    expect($instance->getAttribute('spec'))->toBe([
        'test' => ['something'],
    ]);

    $instance->addToSpec('test', ['else'], false);

    expect($instance->getAttribute('spec'))->toBe([
        'test' => ['something', 'else'],
    ]);
});

it('gets spec from attributes', function () {
    $instance = new class
    {
        use HasSpec;
    };

    $instance->setAttribute('spec', ['replicas' => 8]);

    expect($instance->getSpec('replicas'))->toBe(8);
});

it('removes spec from attributes', function () {
    $instance = new class
    {
        use HasSpec;
    };

    $instance->setAttribute('spec', ['replicas' => 8, 'readyReplicas' => 4]);

    $instance->removeSpec('replicas');

    expect($instance->getSpec('replicas'))->toBeNull();
});
