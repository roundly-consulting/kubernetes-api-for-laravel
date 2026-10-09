<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\PersistentVolume;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAccessModes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasMountOptions;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatusPhase;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStorageClass;

it('extends resource class', function () {
    expect(PersistentVolume::class)->toUse([
        Resource::class,
        HasSpec::class,
        HasStatus::class,
        HasStatusPhase::class,
        HasStorageClass::class,
        HasMountOptions::class,
        HasAccessModes::class,
    ]);
});

it('has no selector api, since a persistent volume has no spec.selector', function () {
    // PersistentVolumeSpec has no `selector` (the claim does): the field was dropped.
    expect(class_uses(PersistentVolume::class))->not->toContain(HasSelectors::class)
        ->and(method_exists(PersistentVolume::class, 'setSelectors'))->toBeFalse()
        ->and(method_exists(PersistentVolume::class, 'addSelector'))->toBeFalse()
        ->and(method_exists(PersistentVolume::class, 'getSelectors'))->toBeFalse();
});

it('has correct kind', function () {
    expect(PersistentVolume::make()->getKind())->toBe('PersistentVolume');
});

it('does not use namespaces', function () {
    expect(PersistentVolume::make()->usesNamespaces())->toBeFalse();
});

it('sets the volume source inline in spec', function () {
    // PersistentVolumeSource is embedded inline: the keys are `spec.nfs`, `spec.csi`, …
    // and there is no `spec.source` field.
    $pv = PersistentVolume::make()->setSource('nfs', ['server' => 's', 'path' => '/x']);

    expect($pv->toArray()['spec'])->toBe(['nfs' => ['server' => 's', 'path' => '/x']])
        ->and($pv->getAttribute('spec.source'))->toBeNull();
});

it('replaces the previous volume source when setting another', function () {
    $pv = PersistentVolume::make()
        ->setCapacity(10)
        ->setSource('nfs', ['server' => 's', 'path' => '/x'])
        ->setSource('csi', ['driver' => 'csi.example.com', 'volumeHandle' => 'h1']);

    expect($pv->toArray()['spec'])->toBe([
        'capacity' => ['storage' => '10Gi'],
        'csi' => ['driver' => 'csi.example.com', 'volumeHandle' => 'h1'],
    ]);
});

it('returns the volume source from a server-shaped spec', function () {
    $pv = PersistentVolume::make([
        'spec' => [
            'capacity' => ['storage' => '5Gi'],
            'csi' => [
                'driver' => 'csi.vsphere.vmware.com',
                'volumeAttributes' => ['type' => 'vSphere CNS Block Volume'],
            ],
            'accessModes' => ['ReadWriteOnce'],
        ],
    ]);

    expect($pv)
        ->getSource()
        ->toBe([
            'csi' => [
                'driver' => 'csi.vsphere.vmware.com',
                'volumeAttributes' => ['type' => 'vSphere CNS Block Volume'],
            ],
        ])
        ->getSource('csi')
        ->toBe([
            'driver' => 'csi.vsphere.vmware.com',
            'volumeAttributes' => ['type' => 'vSphere CNS Block Volume'],
        ])
        ->getSource('nfs')
        ->toBeNull();
});

it('returns no source when the spec has none', function () {
    expect(PersistentVolume::make(['spec' => ['capacity' => ['storage' => '5Gi']]])->getSource())->toBeNull();
});

it('sets capacity to spec', function () {
    $pv = PersistentVolume::make();

    $pv->setCapacity(128);
    expect($pv->getAttribute('spec.capacity.storage'))->toBe('128Gi');

    $pv->setCapacity(128, 'Ki');
    expect($pv->getAttribute('spec.capacity.storage'))->toBe('128Ki');
});

it('returns capacity from spec', function () {
    $pv = PersistentVolume::make([
        'spec' => [
            'capacity' => [
                'storage' => '128Gi',
            ],
        ],
    ]);

    expect($pv->getCapacity())->toBe('128Gi');
});

it('checks whether pv is available from status phase', function () {
    $pv = PersistentVolume::make([
        'status' => [
            'phase' => 'Available',
        ],
    ]);

    expect($pv->isAvailable())->toBeTrue();

    $pv = PersistentVolume::make([
        'status' => [
            'phase' => 'Bound',
        ],
    ]);

    expect($pv->isAvailable())->toBeFalse();
});

it('checks whether pv is bound from status phase', function () {
    $pv = PersistentVolume::make([
        'status' => [
            'phase' => 'Available',
        ],
    ]);

    expect($pv->isBound())->toBeFalse();

    $pv = PersistentVolume::make([
        'status' => [
            'phase' => 'Bound',
        ],
    ]);

    expect($pv->isBound())->toBeTrue();
});
