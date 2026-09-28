<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\Exceptions\NamespaceScopeException;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasCluster;

it('sets and gets cluster', function () {
    $instance = new class
    {
        use HasCluster;
    };

    expect($instance->getCluster())->toBeNull();

    $cluster = Cluster::make();

    $instance->setCluster($cluster);

    expect($instance->getCluster())->toBe($cluster);
});

it('pins the resource to the namespace of a scoped cluster', function () {
    $instance = new class
    {
        use HasCluster;
    };

    $instance->setCluster(Cluster::make()->namespace('prod'));

    expect($instance->namespaceScope())->toBe('prod')
        ->and($instance->getNamespace())->toBe('prod');
});

it('refuses a cluster scoped to another namespace', function () {
    $instance = new class
    {
        use HasCluster;
    };

    $instance->setCluster(Cluster::make()->namespace('prod'));
    $instance->setCluster(Cluster::make()->namespace('staging'));
})->throws(NamespaceScopeException::class, "scoped to namespace 'prod' and refuses namespace 'staging'");
