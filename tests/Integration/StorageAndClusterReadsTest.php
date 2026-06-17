<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Event;
use RoundlyConsulting\KubernetesApi\Resources\Node;
use RoundlyConsulting\KubernetesApi\Resources\PersistentVolumeClaim;
use RoundlyConsulting\KubernetesApi\Resources\Pod;
use RoundlyConsulting\KubernetesApi\Resources\StorageClass;
use RoundlyConsulting\KubernetesApi\Resources\Types\Container;
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

it('creates a persistent volume claim bound by first consumer on local-path', function () {
    $pvc = PersistentVolumeClaim::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('data')
        ->setStorageClassName('local-path')
        ->setAccessModes(['ReadWriteOnce'])
        ->setCapacity(1);

    expect($pvc->create()->wasRecentlyCreated())->toBeTrue();

    $found = PersistentVolumeClaim::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('data')->find();

    // local-path uses WaitForFirstConsumer, so an unmounted claim stays Pending.
    expect($found->getStorageClassName())->toBe('local-path')
        ->and($found->getStatusPhase())->toBe('Pending');

    $found->delete();
});

it('lists the cluster nodes', function () {
    $nodes = Node::make()->setCluster($this->cluster)->get();

    expect($nodes)->not->toBeEmpty();
});

it('lists the storage classes including local-path', function () {
    $classes = StorageClass::make()->setCluster($this->cluster)->get();

    $names = collect($classes)->map(fn (StorageClass $class): string => $class->getName())->all();

    expect($names)->toContain('local-path');
});

it('reads the cluster version through the ping path', function () {
    $response = ClusterFactory::rawGet($this->cluster, '/version');

    expect($response->successful())->toBeTrue()
        ->and($response->json('gitVersion'))->toBeString()
        ->and($response->json('gitVersion'))->toStartWith('v1.');
});

it('lists system-generated events in a namespace', function () {
    // Create a pod so the kubelet/scheduler emit events to read back.
    Pod::make()
        ->setCluster($this->cluster)->setNamespace($this->ns)->setName('noisy')
        ->setContainers([
            Container::make()->setName('noisy')->setImage('busybox')->setCommand(['sleep', '60']),
        ])
        ->create();

    retry(40, function (): void {
        $events = Event::make()->setCluster($this->cluster)->setNamespace($this->ns)->get();
        throw_unless(count($events) > 0, new RuntimeException('no events yet'));
    }, 500);

    $events = Event::make()->setCluster($this->cluster)->setNamespace($this->ns)->get();

    expect($events)->not->toBeEmpty();
});
