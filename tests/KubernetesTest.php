<?php

declare(strict_types=1);

use Illuminate\Support\Traits\Macroable;
use RoundlyConsulting\KubernetesApi\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Traits\HasAuthentication;
use RoundlyConsulting\KubernetesApi\Traits\HasManagerName;
use RoundlyConsulting\KubernetesApi\Traits\HasUrl;

it('uses required traits', function () {
    expect(Kubernetes::class)->toUse([
        Macroable::class,
        HasUrl::class,
        HasAuthentication::class,
        HasManagerName::class,
    ]);
});

it('registers cluster instances as macros', function () {
    $kubernetes = new Kubernetes;
    $kubernetes->registerCluster('main', function (Kubernetes $cluster) {
        $cluster->setManagerName('My Cluster Definition');
    });

    expect(Kubernetes::hasMacro('getMainCluster'))
        ->toBeTrue()
        ->and(Kubernetes::getMainCluster())
        ->toBeInstanceOf(Kubernetes::class)
        ->getManagerName()
        ->toBe('My Cluster Definition');
});

it('resolves cluster definition from cluster method', function () {
    $kubernetes = new Kubernetes;
    $kubernetes->registerCluster('main', function (Kubernetes $cluster) {
        $cluster->setManagerName('My Cluster Definition');
    });

    expect($kubernetes->cluster('main'))
        ->toBeInstanceOf(Kubernetes::class)
        ->getManagerName()
        ->toBe('My Cluster Definition');
});

it('throws exception when no cluster with name has been registered', function () {
    $kubernetes = new Kubernetes;

    $kubernetes->cluster('alfa');
})->throws(BadMethodCallException::class, "No cluster 'alfa' definition found.");

it('registers custom resources using macros', function () {
    class CustomResource extends Resource
    {
        //
    }

    $kubernetes = new Kubernetes;
    $kubernetes->setManagerName('My Manager');
    $kubernetes->registerResource('customResource', CustomResource::class);

    expect($kubernetes->customResource())
        ->toBeInstanceOf(CustomResource::class)
        ->getCluster()->toBe($kubernetes);
});
