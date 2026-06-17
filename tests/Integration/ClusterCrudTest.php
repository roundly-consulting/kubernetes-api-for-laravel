<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Resources\ConfigMap;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;
use RoundlyConsulting\KubernetesApi\Resources\Namespaces;
use RoundlyConsulting\KubernetesApi\Resources\Pod;
use RoundlyConsulting\KubernetesApi\Resources\Secret;
use RoundlyConsulting\KubernetesApi\Resources\Types\Container;
use RoundlyConsulting\KubernetesApi\Tests\Integration\ClusterFactory;

uses()->group('integration');

beforeEach(function () {
    if (! ClusterFactory::shouldRun()) {
        $this->markTestSkipped('Set K8S_INTEGRATION=1 to run the live OrbStack integration suite.');
    }

    $this->cluster = ClusterFactory::make();
    $this->ns = ClusterFactory::namespace();

    Namespaces::make()->setCluster($this->cluster)->setName($this->ns)->updateOrCreate();

    // Wait for the namespace to become active.
    retry(20, function (): void {
        $ns = Namespaces::make()->setCluster($this->cluster)->setName($this->ns)->find();
        throw_unless($ns->isActive(), new RuntimeException('namespace not active'));
    }, 250);
});

afterEach(function () {
    if (isset($this->cluster, $this->ns)) {
        try {
            Namespaces::make()->setCluster($this->cluster)->setName($this->ns)->delete();
        } catch (Throwable) {
            // best-effort cleanup
        }
    }

    ClusterFactory::cleanup();
});

it('creates, finds, updates and deletes a config map', function () {
    $cm = ConfigMap::make()->setCluster($this->cluster)->setNamespace($this->ns)
        ->setName('app-config')->setData(['KEY' => 'value']);

    $created = $cm->create();
    expect($created->wasRecentlyCreated())->toBeTrue();

    $found = ConfigMap::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('app-config')->find();
    expect($found->getData()['KEY'])->toBe('value');

    $found->setData(['KEY' => 'updated'])->update();
    $reread = ConfigMap::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('app-config')->find();
    expect($reread->getData()['KEY'])->toBe('updated');

    $reread->delete();
});

it('round-trips a secret with base64 data', function () {
    $secret = Secret::make()->setCluster($this->cluster)->setNamespace($this->ns)
        ->setName('app-secret')->setData(['token' => 'super-secret']);

    $secret->create();

    $found = Secret::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('app-secret')->find();
    expect($found->getData()['token'])->toBe('super-secret');
});

it('runs a pod to completion and execs a command in it', function () {
    $pod = Pod::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('exec-pod')
        ->setContainers([
            Container::make()->setName('main')->setImage('busybox')->setCommand(['sleep', '300']),
        ]);

    $pod->create();

    retry(40, function (): void {
        $current = Pod::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('exec-pod')->find();
        throw_unless($current->isRunning(), new RuntimeException('pod not running'));
    }, 500);

    $result = Pod::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('exec-pod')
        ->exec(['sh', '-c', 'echo hello-stdout; echo hello-stderr 1>&2; exit 0']);

    expect($result->stdout)->toContain('hello-stdout')
        ->and($result->stderr)->toContain('hello-stderr')
        ->and($result->exitCode)->toBe(0);

    $failure = Pod::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('exec-pod')
        ->exec(['sh', '-c', 'exit 7']);

    expect($failure->exitCode)->toBe(7);
});

it('scales and rollout-restarts a deployment', function () {
    // Real Deployments live under the apps/v1 group; set it explicitly.
    $newDeployment = fn (): Deployment => Deployment::make()
        ->setCluster($this->cluster)->setNamespace($this->ns)->setVersion('apps/v1')->setName('web');

    $newDeployment()
        ->setReplicas(1)
        ->setPodsSelectors(['app' => 'web'])
        ->setSpec('template', [
            'metadata' => ['labels' => ['app' => 'web']],
            'spec' => ['containers' => [['name' => 'web', 'image' => 'nginx:alpine']]],
        ])
        ->create();

    $newDeployment()->scale(2);

    retry(40, function () use ($newDeployment): void {
        throw_unless($newDeployment()->find()->getReplicas() === 2, new RuntimeException('not scaled'));
    }, 500);

    expect($newDeployment()->find()->getReplicas())->toBe(2);

    $restarted = $newDeployment()->rolloutRestart();

    expect($restarted->getSpec('template.metadata.annotations'))
        ->toHaveKey('kubectl.kubernetes.io/restartedAt');
});

it('filters config maps by label and paginates', function () {
    foreach (['a', 'b', 'c'] as $i => $name) {
        ConfigMap::make()->setCluster($this->cluster)->setNamespace($this->ns)
            ->setName("cm-{$name}")
            ->setLabels(['tier' => $i < 2 ? 'web' : 'batch'])
            ->setData(['n' => $name])
            ->create();
    }

    $web = ConfigMap::make()->setCluster($this->cluster)->setNamespace($this->ns)
        ->whereLabel('tier', 'web')->get();

    expect($web)->toHaveCount(2);

    $page = ConfigMap::make()->setCluster($this->cluster)->setNamespace($this->ns)
        ->whereLabel('tier', 'web')->limit(1)->getPage();

    expect($page->items)->toHaveCount(1)
        ->and($page->hasMore())->toBeTrue();
});

it('maps a missing resource to a not-found exception', function () {
    ConfigMap::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('nope')->find();
})->throws(KubernetesException::class);
