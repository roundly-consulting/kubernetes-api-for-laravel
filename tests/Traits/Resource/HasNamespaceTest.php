<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAttributes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasNamespace;

it('uses required traits', function () {
    expect(HasNamespace::class)->toUse([
        HasAttributes::class,
    ]);
});

it('sets default namespace and uses it when no namespace is defined', function () {
    $instance = new class
    {
        use HasNamespace;
    };

    $instance->setDefaultNamespace('awesomeland');

    expect($instance->getNamespace())->toBe('awesomeland');
});

it('does not set namespace when resource is not using namespaces by default', function () {
    $instance = new class
    {
        use HasNamespace;
    };

    $instance->setNamespace('cool');

    expect($instance->getNamespace())->toBe('default');
});

it('sets and gets namespace', function () {
    $instance = new class
    {
        use HasNamespace;
    };

    $instance->ignoreNamespace(false);
    $instance->setNamespace('cool');

    expect($instance->getNamespace())->toBe('cool');
});

it('checks whether resource uses namespaces', function () {
    $instance = new class
    {
        use HasNamespace;
    };

    expect($instance->usesNamespaces())->toBeFalse();

    $instance->usingNamespaces();

    expect($instance->usesNamespaces())->toBeTrue();
});

it('disables usage of namespaces', function () {
    $instance = new class
    {
        use HasNamespace;
    };

    $instance->usingNamespaces();

    expect($instance->usesNamespaces())->toBeTrue();

    $instance->ignoreNamespace();

    expect($instance->usesNamespaces())->toBeFalse();
});

it('uses attribute metadata to store and retrieve namespace', function () {
    $instance = new class
    {
        use HasNamespace;
    };

    $instance->usingNamespaces();

    $instance->setAttribute('metadata.namespace', 'beta');
    expect($instance->getNamespace())->toBe('beta');

    $instance->setNamespace('staging');
    expect($instance->getAttribute('metadata.namespace'))->toBe('staging');
});
