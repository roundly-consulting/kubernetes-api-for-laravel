<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Deployment;
use RoundlyConsulting\KubernetesApi\Resources\Endpoints;
use RoundlyConsulting\KubernetesApi\Resources\HorizontalPodAutoscaler;
use RoundlyConsulting\KubernetesApi\Resources\Ingress;
use RoundlyConsulting\KubernetesApi\Resources\LimitRange;
use RoundlyConsulting\KubernetesApi\Resources\NetworkPolicy;
use RoundlyConsulting\KubernetesApi\Resources\ResourceQuota;
use RoundlyConsulting\KubernetesApi\Resources\Service;
use RoundlyConsulting\KubernetesApi\Resources\ServiceAccount;
use RoundlyConsulting\KubernetesApi\Resources\Types\Port;
use RoundlyConsulting\KubernetesApi\Tests\Integration\ClusterFactory;

uses()->group('integration');

beforeEach(function () {
    if (! ClusterFactory::shouldRun()) {
        $this->markTestSkipped('Set K8S_INTEGRATION=1 to run the live OrbStack integration suite.');
    }

    $this->cluster = ClusterFactory::make();
    $this->ns = ClusterFactory::namespace();

    ClusterFactory::createNamespace($this->cluster, $this->ns);
});

afterEach(function () {
    if (isset($this->cluster, $this->ns)) {
        ClusterFactory::deleteNamespace($this->cluster, $this->ns);
    }

    ClusterFactory::cleanup();
});

it('creates, reads and deletes a service', function () {
    $service = Service::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('web')
        ->setType('ClusterIP')
        ->setSelectors(['app' => 'web'])
        ->addPort(Port::http(80));

    expect($service->create()->wasRecentlyCreated())->toBeTrue();

    $found = Service::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('web')->find();
    expect($found->getType())->toBe('ClusterIP')
        ->and($found->getClusterDns())->toContain('web.'.$this->ns);

    $found->delete();
});

it('creates, reads and deletes a service account', function () {
    $sa = ServiceAccount::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('robot')
        ->setAutomountServiceAccountToken(false);

    expect($sa->create()->wasRecentlyCreated())->toBeTrue();

    $found = ServiceAccount::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('robot')->find();
    expect($found->getAutomountServiceAccountToken())->toBeFalse();

    $found->delete();
});

it('reads system-generated endpoints in the default namespace', function () {
    $endpoints = Endpoints::make()->setCluster($this->cluster)->setNamespace('default')->setName('kubernetes')->find();

    expect($endpoints->getName())->toBe('kubernetes')
        ->and($endpoints->getReadyAddresses())->not->toBeEmpty();
});

it('creates, reads and deletes a resource quota', function () {
    $quota = ResourceQuota::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('compute')
        ->setHard(['pods' => '10', 'requests.cpu' => '1']);

    expect($quota->create()->wasRecentlyCreated())->toBeTrue();

    $found = ResourceQuota::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('compute')->find();
    expect($found->getHard()['pods'])->toBe('10');

    $found->delete();
});

it('creates, reads and deletes a limit range', function () {
    $range = LimitRange::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('limits')
        ->addLimit([
            'type' => 'Container',
            'default' => ['cpu' => '500m'],
            'defaultRequest' => ['cpu' => '250m'],
        ]);

    expect($range->create()->wasRecentlyCreated())->toBeTrue();

    $found = LimitRange::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('limits')->find();
    expect($found->getLimits())->toHaveCount(1);

    $found->delete();
});

it('creates and reads an ingress without a controller', function () {
    $ingress = Ingress::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('site')
        ->addRule('example.test', '/', 'web', 80);

    expect($ingress->create()->wasRecentlyCreated())->toBeTrue();

    $found = Ingress::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('site')->find();
    expect($found->getRules())->toHaveCount(1);

    $found->delete();
});

it('creates and reads a network policy without enforcement', function () {
    $policy = NetworkPolicy::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('deny-web')
        ->setPodSelector(['app' => 'web'])
        ->setPolicyTypes(['Ingress']);

    expect($policy->create()->wasRecentlyCreated())->toBeTrue();

    $found = NetworkPolicy::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('deny-web')->find();
    expect($found->getPolicyTypes())->toBe(['Ingress'])
        ->and($found->getPodSelector())->toBe(['app' => 'web']);

    $found->delete();
});

it('creates a select-all network policy with an empty pod selector', function () {
    // An empty podSelector must serialise to `{}` (select all pods); the
    // apiserver rejects `[]`. This proves the EmptyObject fix end-to-end.
    $policy = NetworkPolicy::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('default-deny')
        ->setPodSelector([])
        ->setPolicyTypes(['Ingress']);

    expect($policy->create()->wasRecentlyCreated())->toBeTrue();

    $found = NetworkPolicy::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('default-deny')->find();

    // A select-all policy reads back with an empty matchLabels map.
    expect($found->getPolicyTypes())->toBe(['Ingress'])
        ->and($found->getPodSelector())->toBe([])
        ->and($found->getSpec('podSelector'))->toBe([]);

    $found->delete();
});

it('creates and reads a horizontal pod autoscaler against a deployment', function () {
    Deployment::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('scaler')
        ->setReplicas(1)
        ->setPodsSelectors(['app' => 'scaler'])
        ->setSpec('template', [
            'metadata' => ['labels' => ['app' => 'scaler']],
            'spec' => ['containers' => [['name' => 'scaler', 'image' => 'nginx:alpine']]],
        ])
        ->create();

    $hpa = HorizontalPodAutoscaler::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('scaler')
        ->setScaleTargetRef('Deployment', 'scaler')
        ->setMinReplicas(1)
        ->setMaxReplicas(3);

    expect($hpa->create()->wasRecentlyCreated())->toBeTrue();

    $found = HorizontalPodAutoscaler::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('scaler')->find();
    expect($found->getMaxReplicas())->toBe(3)
        ->and($found->getScaleTargetRef()['name'])->toBe('scaler');

    $found->delete();
});
