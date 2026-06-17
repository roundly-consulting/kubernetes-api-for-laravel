<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\TraefikIngressRoute;
use RoundlyConsulting\KubernetesApi\Resources\Types\TraefikRoute;
use RoundlyConsulting\KubernetesApi\Tests\Integration\ClusterFactory;

uses()->group('integration');

beforeEach(function () {
    if (! ClusterFactory::shouldRun()) {
        $this->markTestSkipped('Set K8S_INTEGRATION=1 to run the live OrbStack integration suite.');
    }

    $this->cluster = ClusterFactory::make();

    if (! ClusterFactory::hasApiGroup($this->cluster, 'traefik.io')) {
        $this->markTestSkipped('Traefik CRDs (traefik.io) are not installed on this cluster.');
    }

    $this->ns = ClusterFactory::namespace();

    ClusterFactory::createNamespace($this->cluster, $this->ns);
});

afterEach(function () {
    if (isset($this->cluster, $this->ns)) {
        ClusterFactory::deleteNamespace($this->cluster, $this->ns);
    }

    ClusterFactory::cleanup();
});

it('creates and reads a traefik ingress route when the CRDs exist', function () {
    $route = TraefikIngressRoute::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('site')
        ->setEntryPoints(['web'])
        ->addRoute(
            TraefikRoute::make()
                ->setAttribute('match', 'Host(`example.test`)')
                ->setAttribute('kind', 'Rule')
        );

    expect($route->create()->wasRecentlyCreated())->toBeTrue();

    $found = TraefikIngressRoute::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('site')->find();
    expect($found->getEntryPoints())->toBe(['web']);

    $found->delete();
});
