<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Deployment;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\Types\EmptyObject;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasReplicas;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasTemplate;

it('extends resource class', function () {
    expect(Deployment::class)->toUse([
        Resource::class,
        HasSpec::class,
        HasStatus::class,
        HasReplicas::class,
        HasSelectors::class,
        HasTemplate::class,
    ]);
});

it('has correct kind', function () {
    expect(Deployment::make()->getKind())->toBe('Deployment');
});

it('uses namespaces', function () {
    expect(Deployment::make()->usesNamespaces())->toBeTrue();
});

it('belongs to the apps/v1 api group', function () {
    expect(Deployment::make()->getVersion())->toBe('apps/v1');
});

it('sets the update strategy on spec.strategy, the field a Deployment has', function () {
    $deployment = Deployment::make();

    $deployment->setUpdateStrategy('RollingUpdate');

    expect($deployment->getSpec('strategy'))->toBe([
        'rollingUpdate' => [
            'maxUnavailable' => '25%',
            'maxSurge' => '25%',
        ],
        'type' => 'RollingUpdate',
    ])->and($deployment->getSpec('updateStrategy'))->toBeNull();

    $deployment->setUpdateStrategy('RollingUpdate', '50%', '80%');

    expect($deployment->getSpec('strategy'))->toBe([
        'rollingUpdate' => [
            'maxUnavailable' => '50%',
            'maxSurge' => '80%',
        ],
        'type' => 'RollingUpdate',
    ]);

    $deployment->setUpdateStrategy('Recreate');

    expect($deployment->getSpec('strategy'))->toBe([
        'type' => 'Recreate',
    ]);
});

it('returns update strategy', function () {
    $deployment = Deployment::make([
        'spec' => [
            'strategy' => [
                'rollingUpdate' => [
                    'maxUnavailable' => '25%',
                    'maxSurge' => '25%',
                ],
                'type' => 'RollingUpdate',
            ],
        ],
    ]);

    expect($deployment->getUpdateStrategy())->toBe([
        'rollingUpdate' => [
            'maxUnavailable' => '25%',
            'maxSurge' => '25%',
        ],
        'type' => 'RollingUpdate',
    ]);
});

it('returns conditions from status', function () {
    $deployment = Deployment::make([
        'status' => [
            'conditions' => [
                [
                    'type' => 'Progressing',
                    'message' => 'ReplicaSet "my-pod" has successfuly progressed.',
                ],
            ],
        ],
    ]);

    expect($deployment->getConditions())->toBe([
        [
            'type' => 'Progressing',
            'message' => 'ReplicaSet "my-pod" has successfuly progressed.',
        ],
    ]);
});

it('sets min ready seconds', function () {
    $deployment = Deployment::make();

    $deployment->setMinReadySeconds(30);

    expect($deployment->getSpec('minReadySeconds'))->toBe(30);
});

it('gets min ready seconds', function () {
    $deployment = Deployment::make([
        'spec' => [
            'minReadySeconds' => 40,
        ],
    ]);

    expect($deployment->getMinReadySeconds())->toBe(40);
});

it('returns pods selectors', function () {
    $deployment = Deployment::make([
        'spec' => [
            'selector' => [
                'matchLabels' => [
                    'run' => 'my-app',
                    'tier' => 'database',
                ],
            ],
        ],
    ]);

    expect($deployment->getPodsSelectors())->toBe([
        'run' => 'my-app',
        'tier' => 'database',
    ]);
});

it('sets pods selectors', function () {
    $deployment = Deployment::make();

    $deployment->setPodsSelectors(['app' => 'my', 'stage' => 'prod']);

    expect($deployment->getSpec('selector.matchLabels'))
        ->toBe(['app' => 'my', 'stage' => 'prod']);
});

it('serialises an empty pod selector as the empty object', function () {
    $deployment = Deployment::make()->setName('app')->setPodsSelectors([]);

    expect($deployment->toArray()['spec']['selector'])
        ->toBeInstanceOf(EmptyObject::class)
        ->and($deployment->getPodsSelectors())->toBe([]);

    expect($deployment->toJson())->toContain('"selector":{}')
        ->and($deployment->toJson())->not->toContain('"selector":[]');
});

it('returns replicas count', function () {
    $deployment = Deployment::make([
        'status' => [
            'availableReplicas' => 4,
            'readyReplicas' => 2,
            'unavailableReplicas' => 1,
            'replicas' => 7,
        ],
    ]);

    expect($deployment)
        ->getAvailableReplicasCount()->toBe(4)
        ->getReadyReplicasCount()->toBe(2)
        ->getUnavailableReplicasCount()->toBe(1)
        ->getDesiredReplicasCount()->toBe(7);
});
