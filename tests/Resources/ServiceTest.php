<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\Service;
use RoundlyConsulting\KubernetesApi\Resources\Types\Port;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

it('extends resource class', function () {
    expect(Service::class)->toUse([
        Resource::class,
        HasSpec::class,
        HasSelectors::class,
    ]);
});

it('has correct kind', function () {
    expect(Service::make()->getKind())->toBe('Service');
});

it('uses namespaces', function () {
    expect(Service::make()->usesNamespaces())->toBeTrue();
});

it('generates cluster dns form name and namespace', function () {
    $service = Service::make();
    $service->setName('loadbalancer');
    $service->setNamespace('production');

    expect($service->getClusterDns())->toBe('loadbalancer.production.svc.cluster.local');
});

it('stores type in spec', function () {
    $service = Service::make();

    $service->setType('LoadBalancer');

    expect($service->getSpec('type'))->toBe('LoadBalancer');
});

it('returns type from spec', function () {
    $service = Service::make([
        'spec' => [
            'type' => 'NodeIP',
        ],
    ]);

    expect($service->getType())->toBe('NodeIP');
});

it('sets cluster traffic policy to local', function () {
    $service = Service::make();

    $service->useLocalTrafficPolicy();

    expect($service)
        ->getSpec('externalTrafficPolicy')
        ->toBe('Local')
        ->getTrafficPolicy()
        ->toBe('Local');
});

it('sets cluster traffic policy to cluster', function () {
    $service = Service::make();

    $service->useClusterTrafficPolicy();

    expect($service->getSpec('externalTrafficPolicy'))->toBe('Cluster');
});

it('has default cluster traffic policy set to cluster', function () {
    $service = Service::make();

    expect($service->getTrafficPolicy())->toBe('Cluster');
});

it('returns ports from spec', function () {
    $service = Service::make([
        'spec' => [
            'ports' => [
                [
                    'protocol' => 'TCP',
                    'port' => 80,
                    'targetPort' => 80,
                ],
            ],
        ],
    ]);

    $ports = $service->getPorts();

    expect($ports)
        ->toBeArray()
        ->toHaveLength(1)
        ->and($ports[0])
        ->toBeInstanceOf(Port::class)
        ->toArray()
        ->toBe([
            'port' => 80,
            'protocol' => 'TCP',
            'targetPort' => 80,
        ]);
});

it('sets ports', function () {
    $service = Service::make();

    $service->setPorts([
        Port::http(8080),
    ]);

    expect($service->getSpec('ports'))->toBe([
        [
            'port' => 80,
            'protocol' => 'TCP',
            'targetPort' => 8080,
        ],
    ]);
});

it('adds port', function () {
    $service = Service::make([
        'spec' => [
            'ports' => [
                [
                    'port' => 443,
                    'protocol' => 'TCP',
                    'targetPort' => 443,
                ],
            ],
        ],
    ]);

    $service->addPort(Port::http(3000));

    expect($service->getSpec('ports'))->toBe([
        [
            'port' => 443,
            'protocol' => 'TCP',
            'targetPort' => 443,
        ],
        [
            'port' => 80,
            'protocol' => 'TCP',
            'targetPort' => 3000,
        ],
    ]);
});

it('adds multiple ports', function () {
    $service = $this->partialMock(Service::class);
    $service->expects('addPort')->twice();

    $service->addPorts([
        Port::http(),
        Port::http(443),
    ]);
});
