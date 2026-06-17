<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAnnotations;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAttributes;

it('uses required traits', function () {
    expect(HasAnnotations::class)->toUse([
        HasAttributes::class,
    ]);
});

it('gets and sets annotations', function () {
    $instance = new class
    {
        use HasAnnotations;
    };

    expect($instance->getAnnotations())->toBe([]);

    $instance->setAnnotations(['something' => 'important']);

    expect($instance->getAnnotations())->toBe(['something' => 'important']);
});

it('gets and sets single annotation', function () {
    $instance = new class
    {
        use HasAnnotations;
    };

    expect($instance->getAnnotation('something'))->toBeNull();

    $instance->setAnnotation('something', 'important');

    expect($instance->getAnnotation('something'))->toBe('important');
});

it('returns default value when annotation is not found', function () {
    $instance = new class
    {
        use HasAnnotations;
    };

    expect($instance->getAnnotation('something', 'default'))->toBe('default');
});

it('removes annotation', function () {
    $instance = new class
    {
        use HasAnnotations;
    };

    $instance->setAnnotation('something', 'important');
    $instance->setAnnotation('else', 'ok');

    expect($instance->getAnnotation('something'))
        ->toBe('important')
        ->and($instance->getAnnotation('else'))
        ->toBe('ok');

    $instance->removeAnnotation('something');

    expect($instance->getAnnotation('something'))
        ->toBeNull()
        ->and($instance->getAnnotation('else'))
        ->toBe('ok');
});

it('stores annotations in metadata', function () {
    $instance = new class
    {
        use HasAnnotations;
    };

    $instance->setAnnotation('something', 'important');

    expect($instance->getAttribute('metadata'))->toBe([
        'annotations' => [
            'something' => 'important',
        ],
    ]);
});
