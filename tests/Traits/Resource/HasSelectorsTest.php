<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Types\EmptyObject;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

it('uses required traits', function () {
    expect(HasSelectors::class)->toUse([
        HasSpec::class,
    ]);
});

it('sets and gets selectors', function () {
    $instance = new class
    {
        use HasSelectors;
    };

    expect($instance->getSelectors())->toBe([]);

    $instance->setSelectors(['run' => 'app']);

    expect($instance->getSelectors())->toBe(['run' => 'app']);
});

it('adds to selectors', function () {
    $instance = new class
    {
        use HasSelectors;
    };

    $instance->setSelectors(['run' => 'app']);

    expect($instance->getSelectors())->toBe(['run' => 'app']);

    $instance->addSelector('tier', 'prod');

    expect($instance->getSelectors())->toBe(['run' => 'app', 'tier' => 'prod']);
});

it('stores selectors in spec', function () {
    $instance = new class
    {
        use HasSelectors;
    };

    $instance->setSpec('selector', ['run' => 'app']);
    expect($instance->getSelectors())->toBe(['run' => 'app']);

    $instance->setSelectors(['run' => 'web']);
    expect($instance->getSpec('selector'))->toBe(['run' => 'web']);
});

it('serialises an empty selector as the empty object so it is not rejected as a list', function () {
    $instance = new class
    {
        use HasSelectors;
    };

    $instance->setSelectors([]);

    expect($instance->getSpec('selector'))->toBeInstanceOf(EmptyObject::class)
        ->and(json_decode(json_encode($instance->getSpec('selector')), true))->toBe([])
        ->and($instance->getSelectors())->toBe([]);
});

it('replaces a previously-set selector map when set to empty', function () {
    $instance = new class
    {
        use HasSelectors;
    };

    $instance->setSelectors(['run' => 'app']);
    $instance->setSelectors([]);

    expect($instance->getSpec('selector'))->toBeInstanceOf(EmptyObject::class);
});
