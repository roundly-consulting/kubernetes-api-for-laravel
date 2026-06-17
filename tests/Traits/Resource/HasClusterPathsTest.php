<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasClusterPaths;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasKind;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasName;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasNamespace;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasVersion;

it('uses required traits', function () {
    expect(HasClusterPaths::class)->toUse([
        HasKind::class,
        HasNamespace::class,
        HasName::class,
        HasVersion::class,
    ]);
});

it('returns path to resource listing', function () {
    $instance = new class
    {
        use HasClusterPaths;
    };

    $instance->setKind('deployment')
        ->ignoreNamespace(false);

    expect($instance)
        ->protected('getResourceListingPath')
        ->toBe('/api/v1/namespaces/default/deployments');
});

it('returns path to single resource item', function () {
    $instance = new class
    {
        use HasClusterPaths;
    };

    $instance->setKind('deployment')
        ->setName('test')
        ->ignoreNamespace(false);

    expect($instance)
        ->protected('getResourcePath')
        ->toBe('/api/v1/namespaces/default/deployments/test');
});

it('returns path to resource listing without namespace', function () {
    $instance = new class
    {
        use HasClusterPaths;
    };

    $instance->setKind('deployment')
        ->ignoreNamespace();

    expect($instance)
        ->protected('getResourceListingPath')
        ->toBe('/api/v1/deployments');
});

it('returns path to custom resource listing version', function () {
    $instance = new class
    {
        use HasClusterPaths;
    };

    $instance->setKind('deployment')
        ->ignoreNamespace(false)
        ->setNamespace('custom')
        ->setVersion('v2beta');

    expect($instance)
        ->protected('getResourceListingPath')
        ->toBe('/apis/v2beta/namespaces/custom/deployments');
});
