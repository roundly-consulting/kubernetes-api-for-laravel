<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAttributes;

it('stores attributes', function () {
    $instance = new class
    {
        use HasAttributes;
    };

    $instance->setAttribute('name', 'ahoy');

    expect($instance->getAttribute('name'))->toBe('ahoy');
});

it('returns default value when no attribute is found by name', function () {
    $instance = new class
    {
        use HasAttributes;
    };

    expect($instance->getAttribute('name', 'default'))->toBe('default');
});

it('adds value to attribute', function () {
    $instance = new class(['spec' => ['one']])
    {
        use HasAttributes;
    };

    $instance->addToAttribute('spec', 'two');

    expect($instance->getAttribute('spec'))->toBe(['one', 'two']);
});

it('removes attribute', function () {
    $instance = new class(['spec' => ['one']])
    {
        use HasAttributes;
    };

    expect($instance->getAttribute('spec'))->toBe(['one']);

    $instance->removeAttribute('spec');

    expect($instance->getAttribute('spec'))->toBeNull();
});

it('sets attributes', function () {
    $instance = new class(['spec' => ['one']])
    {
        use HasAttributes;
    };

    $instance->setAttributes(['metadata' => ['name' => 'test']]);

    expect($instance->getAttribute('metadata'))
        ->toBe(['name' => 'test'])
        ->and($instance->getAttribute('spec'))
        ->toBeNull();
});

it('removes all attributes', function () {
    $instance = new class(['spec' => ['one']])
    {
        use HasAttributes;
    };

    $instance->removeAttributes();

    expect($instance->getAttribute('spec'))
        ->toBeNull();
});

it('it checks whether instance has dirty attributes', function () {
    $instance = new class(['name' => 'test'])
    {
        use HasAttributes;
    };

    expect($instance)->isDirty()->toBeFalse();

    $instance->setAttribute('name', 'okay');

    expect($instance)->isDirty()->toBeTrue();
});

it('sync dirty attributes to original values', function () {
    $instance = new class(['name' => 'test'])
    {
        use HasAttributes;
    };

    $instance->setAttribute('name', 'okay');

    expect($instance)->isDirty()->toBeTrue();

    $instance->sync();

    expect($instance)->isDirty()->toBeFalse();
});

it('discards changes', function () {
    $instance = new class(['name' => 'test'])
    {
        use HasAttributes;
    };

    $instance->setAttribute('name', 'okay');

    expect($instance)
        ->isDirty()
        ->toBeTrue()
        ->getAttribute('name')
        ->toBe('okay');

    $instance->discardChanges();

    expect($instance)
        ->isDirty()
        ->toBeFalse()
        ->getAttribute('name')
        ->toBe('test');
});

it('returns original values', function () {
    $instance = new class(['name' => 'test'])
    {
        use HasAttributes;
    };

    $instance->setAttribute('name', 'okay');

    expect($instance)
        ->getAttribute('name')
        ->toBe('okay')
        ->and($instance)
        ->getOriginal('name')
        ->toBe('test')
        ->getOriginal()
        ->toBe(['name' => 'test'])
        ->getOriginal('not-existing')
        ->toBeNull();
});

it('has macros to manipulate attributes', function () {
    $instance = new class(['name' => 'Bob'])
    {
        use HasAttributes;
    };

    $instance->setName('John')
        ->setAddresses(['one'])
        ->addToAddresses('two')
        ->withReplicas(2);

    expect($instance)
        ->getName()
        ->toBe('John')
        ->getAddresses()
        ->toBe(['one', 'two'])
        ->getOriginalName()
        ->toBe('Bob')
        ->getOriginalAge(20)
        ->toBe(20)
        ->getReplicas()
        ->toBe(2);

    $instance->removeAddresses();

    expect($instance)->getAddresses()->toBeNull();
});

it('can use custom macros', function () {
    $instance = new class(['name' => 'Bob'])
    {
        use HasAttributes;
    };

    $instance::macro('something', fn () => 'Hi');

    expect($instance->something())->toBe('Hi');
});
