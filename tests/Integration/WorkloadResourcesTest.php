<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\CronJob;
use RoundlyConsulting\KubernetesApi\Resources\DaemonSet;
use RoundlyConsulting\KubernetesApi\Resources\Job;
use RoundlyConsulting\KubernetesApi\Resources\ReplicaSet;
use RoundlyConsulting\KubernetesApi\Resources\ReplicationController;
use RoundlyConsulting\KubernetesApi\Resources\StatefulSet;
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

/** @return array<string, mixed> */
function podTemplate(string $app, string $image = 'busybox', array $command = ['sleep', '300']): array
{
    return [
        'metadata' => ['labels' => ['app' => $app]],
        'spec' => ['containers' => [['name' => $app, 'image' => $image, 'command' => $command]]],
    ];
}

it('creates, reads and deletes a replica set', function () {
    $rs = ReplicaSet::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('rs')
        ->setReplicas(1)
        ->setPodsSelectors(['app' => 'rs'])
        ->setSpec('template', podTemplate('rs'));

    expect($rs->create()->wasRecentlyCreated())->toBeTrue();

    $found = ReplicaSet::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('rs')->find();
    expect($found->getReplicas())->toBe(1);

    $found->delete();
});

it('creates, reads and deletes a stateful set', function () {
    $sts = StatefulSet::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('sts')
        ->setReplicas(1)
        ->setServiceName('sts')
        ->setSpec('selector.matchLabels', ['app' => 'sts'])
        ->setSpec('template', podTemplate('sts'));

    expect($sts->create()->wasRecentlyCreated())->toBeTrue();

    $found = StatefulSet::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('sts')->find();
    expect($found->getServiceName())->toBe('sts');

    $found->delete();
});

it('creates, reads and deletes a daemon set', function () {
    $ds = DaemonSet::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('ds')
        ->setPodsSelectors(['app' => 'ds'])
        ->setSpec('template', podTemplate('ds'));

    expect($ds->create()->wasRecentlyCreated())->toBeTrue();

    $found = DaemonSet::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('ds')->find();
    expect($found->getPodsSelectors())->toBe(['app' => 'ds']);

    $found->delete();
});

it('creates, reads and deletes a replication controller', function () {
    $rc = ReplicationController::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('rc')
        ->setReplicas(1)
        ->setPodsSelectors(['app' => 'rc'])
        ->setSpec('template', podTemplate('rc'));

    expect($rc->create()->wasRecentlyCreated())->toBeTrue();

    $found = ReplicationController::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('rc')->find();
    expect($found->getPodsSelectors())->toBe(['app' => 'rc']);

    $found->delete();
});

it('runs a job to completion', function () {
    $job = Job::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('job')
        ->setSpec('template', [
            'spec' => [
                'restartPolicy' => 'Never',
                'containers' => [['name' => 'job', 'image' => 'busybox', 'command' => ['sh', '-c', 'exit 0']]],
            ],
        ]);

    expect($job->create()->wasRecentlyCreated())->toBeTrue();

    retry(40, function (): void {
        $current = Job::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('job')->find();
        throw_unless($current->getSucceededPodsCount() >= 1, new RuntimeException('job not complete'));
    }, 500);

    $done = Job::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('job')->find();
    expect($done->getSucceededPodsCount())->toBeGreaterThanOrEqual(1);

    $done->delete();
});

it('creates and reads a cron job without waiting for its schedule', function () {
    $cron = CronJob::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('cron')
        ->setSchedule('*/5 * * * *')
        ->setSpec('jobTemplate', [
            'spec' => [
                'template' => [
                    'spec' => [
                        'restartPolicy' => 'Never',
                        'containers' => [['name' => 'cron', 'image' => 'busybox', 'command' => ['sh', '-c', 'exit 0']]],
                    ],
                ],
            ],
        ]);

    expect($cron->create()->wasRecentlyCreated())->toBeTrue();

    $found = CronJob::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('cron')->find();
    expect($found->getSchedule())->toBe('*/5 * * * *');

    $found->delete();
});
