<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Pod;
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

it('drops empty label and annotation maps instead of sending a json list', function () {
    // `"labels": []` is a JSON list where ObjectMeta expects a map: the apiserver answers 400.
    $pod = Pod::make()->setName('p')->setLabels(['app' => 'web'])->setAnnotations(['a' => 'b']);

    $pod->setLabels([])->setAnnotations([]);

    // The empty object `{}` — never `[]`, and never an emptied `metadata` either.
    expect($pod->toJson())->not->toContain('[]')
        ->and($pod->toJson())->toContain('"metadata":{"name":"p","labels":{},"annotations":{}}')
        ->and($pod->getLabels())->toBe([])
        ->and($pod->getAnnotations())->toBe([])
        ->and(Pod::make()->setLabels([])->setAnnotations([])->toJson())->toContain('"metadata":{"labels":{},"annotations":{}}');

    expect(Pod::make()->setName('p')->setLabel('app', 'web')->removeLabel('app')->toJson())->toContain('"labels":{}')
        ->and(Pod::make()->setName('p')->setAnnotation('a', 'b')->removeAnnotation('a')->toJson())->toContain('"annotations":{}')
        ->and(Pod::make()->setLabels([])->setLabel('app', 'web')->getLabels())->toBe(['app' => 'web']);
});
