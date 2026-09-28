<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\KubernetesNamespace;
use RoundlyConsulting\KubernetesApi\Resources\Resource;

it('extends resource class', function () {
    expect(KubernetesNamespace::class)->toUse(Resource::class);
});

it('has correct kind', function () {
    expect(KubernetesNamespace::make()->getKind())->toBe('Namespace');
});

it('does not use namespaces', function () {
    expect(KubernetesNamespace::make()->usesNamespaces())->toBeFalse();
});

it('has status and status phase', function () {
    $namespace = KubernetesNamespace::make(['status' => ['phase' => 'Active']]);

    expect($namespace->getStatus('phase'))
        ->toBe('Active')
        ->and($namespace->getStatusPhase())
        ->toBe('Active')
        ->and($namespace->statusPhaseIs('Active'))
        ->toBeTrue()
        ->and($namespace->isActive())
        ->toBeTrue()
        ->and($namespace->isTerminating())
        ->toBeFalse();

    $namespace->setAttribute('status', ['phase' => 'Terminating']);

    expect($namespace->isTerminating())->toBeTrue();
});
