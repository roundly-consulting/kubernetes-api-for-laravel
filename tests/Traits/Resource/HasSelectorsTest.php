<?php

declare(strict_types=1);

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
