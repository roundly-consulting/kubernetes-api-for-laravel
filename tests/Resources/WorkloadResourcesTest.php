<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\CronJob;
use RoundlyConsulting\KubernetesApi\Resources\DaemonSet;
use RoundlyConsulting\KubernetesApi\Resources\Ingress;
use RoundlyConsulting\KubernetesApi\Resources\Job;
use RoundlyConsulting\KubernetesApi\Resources\ReplicaSet;
use RoundlyConsulting\KubernetesApi\Resources\ReplicationController;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\StatefulSet;
use RoundlyConsulting\KubernetesApi\Resources\Types\PersistentVolumeClaimTemplate;

it('configures a replica set', function () {
    expect(ReplicaSet::class)->toUse(Resource::class);

    $rs = ReplicaSet::make()->setReplicas(3)->setPodsSelectors(['app' => 'api']);
    $rs->setStatus(['readyReplicas' => 2, 'availableReplicas' => 2, 'fullyLabeledReplicas' => 3]);

    expect($rs->getKind())->toBe('ReplicaSet')
        ->and($rs->getVersion())->toBe('apps/v1')
        ->and($rs->usesNamespaces())->toBeTrue()
        ->and($rs->getReplicas())->toBe(3)
        ->and($rs->getPodsSelectors())->toBe(['app' => 'api'])
        ->and($rs->getReadyReplicasCount())->toBe(2)
        ->and($rs->getAvailableReplicasCount())->toBe(2)
        ->and($rs->getFullyLabeledReplicasCount())->toBe(3);
});

it('configures a stateful set with volume claim templates', function () {
    $template = PersistentVolumeClaimTemplate::make()
        ->setName('data')
        ->setAccessModes(['ReadWriteOnce'])
        ->setStorageClassName('fast')
        ->setStorageRequest('1Gi');

    $set = StatefulSet::make()
        ->setServiceName('db')
        ->setReplicas(2)
        ->setVolumeClaimTemplates([$template]);

    $set->setStatus(['readyReplicas' => 1, 'currentReplicas' => 2, 'updatedReplicas' => 2]);

    expect($set->getKind())->toBe('StatefulSet')
        ->and($set->getVersion())->toBe('apps/v1')
        ->and($set->getServiceName())->toBe('db')
        ->and($set->getReadyReplicasCount())->toBe(1)
        ->and($set->getCurrentReplicasCount())->toBe(2)
        ->and($set->getUpdatedReplicasCount())->toBe(2);

    $templates = $set->getVolumeClaimTemplates();

    expect($templates)->toHaveCount(1)
        ->and($templates[0])->toBeInstanceOf(PersistentVolumeClaimTemplate::class)
        ->and($templates[0]->getName())->toBe('data')
        ->and($templates[0]->getAccessModes())->toBe(['ReadWriteOnce'])
        ->and($templates[0]->getStorageClassName())->toBe('fast')
        ->and($templates[0]->getStorageRequest())->toBe('1Gi');

    expect(StatefulSet::make()->getServiceName())->toBeNull();
});

it('configures a daemon set', function () {
    $ds = DaemonSet::make()
        ->setPodsSelectors(['app' => 'agent'])
        ->setUpdateStrategy('RollingUpdate', '2');

    $ds->setStatus([
        'desiredNumberScheduled' => 4,
        'currentNumberScheduled' => 4,
        'numberReady' => 3,
        'numberAvailable' => 3,
    ]);

    expect($ds->getKind())->toBe('DaemonSet')
        ->and($ds->getVersion())->toBe('apps/v1')
        ->and($ds->getPodsSelectors())->toBe(['app' => 'agent'])
        ->and($ds->getUpdateStrategy())->toBe([
            'rollingUpdate' => ['maxUnavailable' => '2'],
            'type' => 'RollingUpdate',
        ])
        ->and($ds->getDesiredNumberScheduled())->toBe(4)
        ->and($ds->getCurrentNumberScheduled())->toBe(4)
        ->and($ds->getNumberReady())->toBe(3)
        ->and($ds->getNumberAvailable())->toBe(3);
});

it('sets a non-rolling daemon set update strategy', function () {
    $ds = DaemonSet::make()->setUpdateStrategy('OnDelete');

    expect($ds->getUpdateStrategy())->toBe(['type' => 'OnDelete']);
});

it('configures a cron job', function () {
    $cron = CronJob::make()
        ->setSchedule('*/5 * * * *')
        ->suspend()
        ->setConcurrencyPolicy('Forbid')
        ->setSuccessfulJobsHistoryLimit(5)
        ->setFailedJobsHistoryLimit(2)
        ->setJobTemplate(Job::make()->setName('cleanup'));

    $cron->setStatus([
        'lastScheduleTime' => '2026-06-17T10:00:00Z',
        'lastSuccessfulTime' => '2026-06-17T10:01:00Z',
    ]);

    expect($cron->getKind())->toBe('CronJob')
        ->and($cron->getVersion())->toBe('batch/v1')
        ->and($cron->getSchedule())->toBe('*/5 * * * *')
        ->and($cron->isSuspended())->toBeTrue()
        ->and($cron->getConcurrencyPolicy())->toBe('Forbid')
        ->and($cron->getSuccessfulJobsHistoryLimit())->toBe(5)
        ->and($cron->getFailedJobsHistoryLimit())->toBe(2)
        ->and($cron->getJobTemplate())->toBeInstanceOf(Job::class)
        ->and($cron->getLastScheduleTime()?->toDateString())->toBe('2026-06-17')
        ->and($cron->getLastSuccessfulTime()?->toDateString())->toBe('2026-06-17');
});

it('returns cron job defaults when unset', function () {
    $cron = CronJob::make();

    expect($cron->getSchedule())->toBeNull()
        ->and($cron->isSuspended())->toBeFalse()
        ->and($cron->getConcurrencyPolicy())->toBe('Allow')
        ->and($cron->getSuccessfulJobsHistoryLimit())->toBe(3)
        ->and($cron->getFailedJobsHistoryLimit())->toBe(1)
        ->and($cron->getLastScheduleTime())->toBeNull()
        ->and($cron->getLastSuccessfulTime())->toBeNull();
});

it('configures an ingress with rules and tls', function () {
    $ingress = Ingress::make()
        ->setIngressClassName('nginx')
        ->addRule('shop.test', '/', 'shop-svc', 80)
        ->addTls(['shop.test'], 'shop-tls');

    $ingress->setStatus(['loadBalancer' => ['ingress' => [['ip' => '10.0.0.1']]]]);

    expect($ingress->getKind())->toBe('Ingress')
        ->and($ingress->getVersion())->toBe('networking.k8s.io/v1')
        ->and($ingress->getIngressClassName())->toBe('nginx')
        ->and($ingress->getRules())->toHaveCount(1)
        ->and($ingress->getRules()[0]['host'])->toBe('shop.test')
        ->and($ingress->getTls())->toBe([['hosts' => ['shop.test'], 'secretName' => 'shop-tls']])
        ->and($ingress->getLoadBalancerIngress())->toBe([['ip' => '10.0.0.1']]);
});

it('configures a replication controller', function () {
    $rc = ReplicationController::make()->setReplicas(2)->setPodsSelectors(['app' => 'legacy']);
    $rc->setStatus(['readyReplicas' => 2, 'availableReplicas' => 2]);

    expect($rc->getKind())->toBe('ReplicationController')
        ->and($rc->getPodsSelectors())->toBe(['app' => 'legacy'])
        ->and($rc->getReadyReplicasCount())->toBe(2)
        ->and($rc->getAvailableReplicasCount())->toBe(2);
});
