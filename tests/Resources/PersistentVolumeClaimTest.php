<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\PersistentVolumeClaim;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAccessModes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatusPhase;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStorageClass;

it('extends resource class', function () {
    expect(PersistentVolumeClaim::class)->toUse([
        Resource::class,
        HasSpec::class,
        HasStatus::class,
        HasStatusPhase::class,
        HasSelectors::class,
        HasStorageClass::class,
        HasAccessModes::class,
    ]);
});

it('has correct kind', function () {
    expect(PersistentVolumeClaim::make()->getKind())->toBe('PersistentVolumeClaim');
});

it('uses namespaces', function () {
    expect(PersistentVolumeClaim::make()->usesNamespaces())->toBeTrue();
});

it('stores capacity to spec', function () {
    $pvc = PersistentVolumeClaim::make();

    $pvc->setCapacity(128);
    expect($pvc->getAttribute('spec.resources.requests.storage'))->toBe('128Gi');

    $pvc->setCapacity(128, 'Ki');
    expect($pvc->getAttribute('spec.resources.requests.storage'))->toBe('128Ki');
});

it('returns capacity from spec', function () {
    $pvc = PersistentVolumeClaim::make([
        'spec' => [
            'resources' => [
                'requests' => [
                    'storage' => '128Gi',
                ],
            ],
        ],
    ]);

    expect($pvc->getCapacity())->toBe('128Gi');
});

it('checks whether pvc is available from status phase', function () {
    $pvc = PersistentVolumeClaim::make([
        'status' => [
            'phase' => 'Available',
        ],
    ]);

    expect($pvc->isAvailable())->toBeTrue();

    $pvc = PersistentVolumeClaim::make([
        'status' => [
            'phase' => 'Bound',
        ],
    ]);

    expect($pvc->isAvailable())->toBeFalse();
});

it('checks whether pvc is bound from status phase', function () {
    $pvc = PersistentVolumeClaim::make([
        'status' => [
            'phase' => 'Available',
        ],
    ]);

    expect($pvc->isBound())->toBeFalse();

    $pvc = PersistentVolumeClaim::make([
        'status' => [
            'phase' => 'Bound',
        ],
    ]);

    expect($pvc->isBound())->toBeTrue();
});
