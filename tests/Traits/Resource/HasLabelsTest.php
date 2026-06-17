<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAttributes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasLabels;

it('uses required traits', function () {
    expect(HasLabels::class)->toUse([
        HasAttributes::class,
    ]);
});

it('gets and sets labels', function () {
    $instance = new class
    {
        use HasLabels;
    };

    expect($instance->getLabels())->toBe([]);

    $instance->setLabels(['something' => 'important']);

    expect($instance->getLabels())->toBe(['something' => 'important']);
});

it('gets and sets single label', function () {
    $instance = new class
    {
        use HasLabels;
    };

    expect($instance->getLabel('something'))->toBeNull();

    $instance->setLabel('something', 'important');

    expect($instance->getLabel('something'))->toBe('important');
});

it('returns default value when label is not found', function () {
    $instance = new class
    {
        use HasLabels;
    };

    expect($instance->getLabel('something', 'default'))->toBe('default');
});

it('removes label', function () {
    $instance = new class
    {
        use HasLabels;
    };

    $instance->setLabel('something', 'important');
    $instance->setLabel('else', 'ok');

    expect($instance->getLabel('something'))
        ->toBe('important')
        ->and($instance->getLabel('else'))
        ->toBe('ok');

    $instance->removeLabel('something');

    expect($instance->getLabel('something'))
        ->toBeNull()
        ->and($instance->getLabel('else'))
        ->toBe('ok');
});

it('stores labels in metadata', function () {
    $instance = new class
    {
        use HasLabels;
    };

    $instance->setLabel('something', 'important');

    expect($instance->getAttribute('metadata'))->toBe([
        'labels' => [
            'something' => 'important',
        ],
    ]);
});
