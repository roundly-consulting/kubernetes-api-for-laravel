<?php

declare(strict_types=1);

use Illuminate\Support\Traits\Conditionable;
use RoundlyConsulting\KubernetesApi\Resources\Types\Type;
use RoundlyConsulting\KubernetesApi\Traits\Makeable;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAttributes;

it('uses required traits', function () {
    expect(Type::class)->toUse([
        Makeable::class,
        HasAttributes::class,
        Conditionable::class,
    ]);
});

it('returns attributes as sorted array', function () {
    $instance = new Type([
        'something' => 'yay',
        'aron' => 'hi',
    ]);

    expect($instance->toArray())->toBe([
        'aron' => 'hi',
        'something' => 'yay',
    ]);
});

it('returns json of sorted attributes', function () {
    $instance = new Type([
        'something' => 'yay',
        'aron' => 'hi',
    ]);

    expect($instance->toJson())
        ->toBe('{"aron":"hi","something":"yay"}');
});

it('returns unescaped unicode json of sorted attributes allowing to use custom json options', function () {
    $instance = new Type([
        'something' => '€',
        'aron' => 'hi',
    ]);

    expect($instance->toJson(JSON_UNESCAPED_UNICODE))
        ->toBe('{"aron":"hi","something":"€"}');
});
