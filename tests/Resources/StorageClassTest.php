<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\StorageClass;

it('extends resource class', function () {
    expect(StorageClass::class)->toUse(Resource::class);
});

it('has correct kind', function () {
    expect(StorageClass::make()->getKind())->toBe('StorageClass');
});

it('does not use namespaces', function () {
    expect(StorageClass::make()->usesNamespaces())->toBeFalse();
});

it('has different version', function () {
    expect(StorageClass::make()->getVersion())->toBe('storage.k8s.io/v1');
});

it('stores mount options in attribute', function () {
    $sc = StorageClass::make();

    $sc->setMountOptions(['debug']);

    expect($sc->getAttribute('mountOptions'))->toBe(['debug']);
});

it('gets mount options from attribute', function () {
    $sc = StorageClass::make(['mountOptions' => ['debug']]);

    expect($sc->getMountOptions())->toBe(['debug']);
});

it('stores parameters in attribute', function () {
    $sc = StorageClass::make();

    $sc->setParameters(['type' => 'test']);

    expect($sc->getAttribute('parameters'))->toBe(['type' => 'test']);
});

it('gets parameters from attribute', function () {
    $sc = StorageClass::make(['parameters' => ['type' => 'test']]);

    expect($sc->getParameters())->toBe(['type' => 'test']);
});
