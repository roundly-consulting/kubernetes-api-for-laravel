<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Endpoints;
use RoundlyConsulting\KubernetesApi\Resources\Event;
use RoundlyConsulting\KubernetesApi\Resources\HorizontalPodAutoscaler;
use RoundlyConsulting\KubernetesApi\Resources\LimitRange;
use RoundlyConsulting\KubernetesApi\Resources\NetworkPolicy;
use RoundlyConsulting\KubernetesApi\Resources\ResourceQuota;
use RoundlyConsulting\KubernetesApi\Resources\ServiceAccount;
use RoundlyConsulting\KubernetesApi\Resources\Types\EmptyObject;

it('reads event fields', function () {
    $event = Event::make([
        'reason' => 'Scheduled',
        'message' => 'Successfully assigned',
        'type' => 'Normal',
        'count' => 3,
        'involvedObject' => ['kind' => 'Pod', 'name' => 'api'],
        'firstTimestamp' => '2026-06-17T10:00:00Z',
        'lastTimestamp' => '2026-06-17T10:05:00Z',
    ]);

    expect($event->getKind())->toBe('Event')
        ->and($event->getReason())->toBe('Scheduled')
        ->and($event->getMessage())->toBe('Successfully assigned')
        ->and($event->getType())->toBe('Normal')
        ->and($event->getCount())->toBe(3)
        ->and($event->getInvolvedObject())->toBe(['kind' => 'Pod', 'name' => 'api'])
        ->and($event->isNormal())->toBeTrue()
        ->and($event->isWarning())->toBeFalse()
        ->and($event->getFirstTimestamp()?->toDateString())->toBe('2026-06-17')
        ->and($event->getLastTimestamp()?->toDateString())->toBe('2026-06-17');

    expect(Event::make()->getLastTimestamp())->toBeNull();
});

it('reads endpoints subsets, addresses and ports', function () {
    $endpoints = Endpoints::make([
        'subsets' => [[
            'addresses' => [['ip' => '10.0.0.1'], ['ip' => '10.0.0.2']],
            'ports' => [['port' => 80], ['port' => 443]],
        ]],
    ]);

    expect($endpoints->getKind())->toBe('Endpoints')
        ->and($endpoints->getPluralKind())->toBe('endpoints')
        ->and($endpoints->getSubsets())->toHaveCount(1)
        ->and($endpoints->getSubset(0))->toHaveKey('addresses')
        ->and($endpoints->getReadyAddresses())->toBe(['10.0.0.1', '10.0.0.2'])
        ->and($endpoints->getPorts())->toBe([80, 443]);

    expect(Endpoints::make()->getSubset(5))->toBe([]);
});

it('configures a service account', function () {
    $sa = ServiceAccount::make()
        ->addSecret('token-secret')
        ->addImagePullSecret('registry')
        ->setAutomountServiceAccountToken(false);

    expect($sa->getKind())->toBe('ServiceAccount')
        ->and($sa->getSecrets())->toBe([['name' => 'token-secret']])
        ->and($sa->getImagePullSecrets())->toBe([['name' => 'registry']])
        ->and($sa->getAutomountServiceAccountToken())->toBeFalse();

    expect(ServiceAccount::make()->getAutomountServiceAccountToken())->toBeNull();
});

it('configures a network policy', function () {
    $policy = NetworkPolicy::make()
        ->setPodSelector(['app' => 'api'])
        ->setPolicyTypes(['Ingress', 'Egress'])
        ->addIngressRule(['from' => [['podSelector' => []]]])
        ->addEgressRule(['to' => [['ipBlock' => ['cidr' => '0.0.0.0/0']]]]);

    expect($policy->getKind())->toBe('NetworkPolicy')
        ->and($policy->getPluralKind())->toBe('networkpolicies')
        ->and($policy->getVersion())->toBe('networking.k8s.io/v1')
        ->and($policy->getPodSelector())->toBe(['app' => 'api'])
        ->and($policy->getPolicyTypes())->toBe(['Ingress', 'Egress'])
        ->and($policy->getSpec('ingress'))->toHaveCount(1)
        ->and($policy->getSpec('egress'))->toHaveCount(1);
});

it('serialises a select-all pod selector as the empty object', function () {
    $policy = NetworkPolicy::make()->setName('allow-all')->setPodSelector([]);

    expect($policy->toArray()['spec']['podSelector'])->toBeInstanceOf(EmptyObject::class)
        ->and($policy->getSpec('podSelector'))->toBe([])
        ->and($policy->getPodSelector())->toBe([]);

    $json = $policy->toJson();

    expect($json)->toContain('"podSelector":{}')
        ->and($json)->not->toContain('"podSelector":[]');
});

it('replaces a labelled pod selector when reset to select-all', function () {
    $policy = NetworkPolicy::make()
        ->setPodSelector(['app' => 'api'])
        ->setPodSelector([]);

    expect($policy->toArray()['spec']['podSelector'])->toBeInstanceOf(EmptyObject::class)
        ->and($policy->getSpec('podSelector.matchLabels'))->toBeNull();
});

it('configures a horizontal pod autoscaler', function () {
    $hpa = HorizontalPodAutoscaler::make()
        ->setScaleTargetRef('Deployment', 'api')
        ->setMinReplicas(2)
        ->setMaxReplicas(10);

    $hpa->setStatus(['currentReplicas' => 3, 'desiredReplicas' => 4]);

    expect($hpa->getKind())->toBe('HorizontalPodAutoscaler')
        ->and($hpa->getVersion())->toBe('autoscaling/v2')
        ->and($hpa->getScaleTargetRef())->toBe(['apiVersion' => 'apps/v1', 'kind' => 'Deployment', 'name' => 'api'])
        ->and($hpa->getMinReplicas())->toBe(2)
        ->and($hpa->getMaxReplicas())->toBe(10)
        ->and($hpa->getCurrentReplicas())->toBe(3)
        ->and($hpa->getDesiredReplicas())->toBe(4);

    expect(HorizontalPodAutoscaler::make()->getMinReplicas())->toBe(1);
});

it('configures a resource quota', function () {
    $quota = ResourceQuota::make()->setHard(['cpu' => '4', 'memory' => '8Gi']);
    $quota->setStatus(['used' => ['cpu' => '1']]);

    expect($quota->getKind())->toBe('ResourceQuota')
        ->and($quota->getHard())->toBe(['cpu' => '4', 'memory' => '8Gi'])
        ->and($quota->getUsed())->toBe(['cpu' => '1']);
});

it('configures a limit range', function () {
    $range = LimitRange::make()->addLimit(['type' => 'Container', 'default' => ['cpu' => '500m']]);

    expect($range->getKind())->toBe('LimitRange')
        ->and($range->getLimits())->toBe([['type' => 'Container', 'default' => ['cpu' => '500m']]]);
});
