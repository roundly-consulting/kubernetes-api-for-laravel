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
        HasSelectors::class,
        HasMountOptions::class,
        HasAccessModes::class,
    ]);
});

it('has correct kind', function () {
    expect(PersistentVolume::make()->getKind())->toBe('PersistentVolume');
});

it('does not use namespaces', function () {
    expect(PersistentVolume::make()->usesNamespaces())->toBeFalse();
});

it('sets source to spec', function () {
    $pv = PersistentVolume::make();

    $pv->setSource('Type', 'HostPath');

    expect($pv->getAttribute('spec.source.Type'))->toBe('HostPath');

    $pv->setSource('VolumeAttributes', [
        'type=vSphere CNS Block Volume',
    ]);

    expect($pv->getAttribute('spec.source.VolumeAttributes'))->toBe([
        'type=vSphere CNS Block Volume',
    ]);
});

it('returns source from spec', function () {
    $pv = PersistentVolume::make([
        'spec' => [
            'source' => [
                'Type' => 'CSI',
                'VolumeAttributes' => [
                    'type=vSphere CNS Block Volume',
                ],
            ],
        ],
    ]);

    expect($pv)
        ->getSource()
        ->toBe([
            'Type' => 'CSI',
            'VolumeAttributes' => [
                'type=vSphere CNS Block Volume',
            ],
        ])
        ->getSource('Type')
        ->toBe('CSI')
        ->getSource('VolumeAttributes')
        ->toBe([
            'type=vSphere CNS Block Volume',
        ]);
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
