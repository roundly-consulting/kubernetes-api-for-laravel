<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\TraefikIngressRoute;
use RoundlyConsulting\KubernetesApi\Resources\TraefikMiddleware;
use RoundlyConsulting\KubernetesApi\Resources\Types\TraefikRoute;
use RoundlyConsulting\KubernetesApi\Resources\Types\TraefikService;
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

it('creates, reads and deletes a traefik ingress route', function () {
    // The real Traefik v3 IngressRoute CRD requires every route to carry a
    // `match`, `kind: Rule`, and a non-empty `services` list (name + port);
    // the apiserver rejects a route without a service reference.
    $route = TraefikIngressRoute::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('site')
        ->setEntryPoints(['web'])
        ->addRoute(
            TraefikRoute::hostRule('example.test')
                ->addService(TraefikService::to('whoami', '80'))
        );

    expect($route->create()->wasRecentlyCreated())->toBeTrue();

    $found = TraefikIngressRoute::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('site')->find();

    expect($found->getEntryPoints())->toBe(['web'])
        ->and($found->getRoutes())->toHaveCount(1);

    $persisted = $found->getRoutes()[0];

    expect($persisted->getAttribute('match'))->toBe('Host(`example.test`)')
        ->and($persisted->getAttribute('kind'))->toBe('Rule')
        ->and($persisted->getServices())->toHaveCount(1)
        ->and($persisted->getServices()[0]->getAttribute('name'))->toBe('whoami')
        ->and((string) $persisted->getServices()[0]->getAttribute('port'))->toBe('80');

    $found->delete();
});

it('creates, reads and deletes a traefik middleware', function () {
    $middleware = TraefikMiddleware::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('https-redirect')
        ->redirectToScheme('https', true);

    expect($middleware->create()->wasRecentlyCreated())->toBeTrue();

    $found = TraefikMiddleware::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('https-redirect')->find();

    $redirect = $found->getSpec('redirectScheme');

    expect($redirect)->toBeArray()
        ->and($redirect['scheme'])->toBe('https')
        ->and($redirect['permanent'])->toBeTrue();

    $found->delete();
});

it('creates and reads a traefik service load-balancing across kubernetes services', function () {
    // The package ships no first-class `TraefikService` Resource (kind
    // `TraefikService`, traefikservices.traefik.io) — only the route-level
    // `Types\TraefikService` value object. There is no typed CRUD entry point
    // for the standalone CRD, so this case is intentionally skipped rather than
    // hand-rolling an untyped manifest.
    $this->markTestSkipped('No first-class TraefikService resource in the package; covered via IngressRoute service refs instead.');
});
