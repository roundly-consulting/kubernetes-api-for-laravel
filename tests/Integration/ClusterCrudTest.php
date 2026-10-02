<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubernetesPatch;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\WatchEvent;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Resources\ConfigMap;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;
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

    ClusterFactory::createNamespace($this->cluster, $this->ns);
});

afterEach(function () {
    if (isset($this->cluster, $this->ns)) {
        ClusterFactory::deleteNamespace($this->cluster, $this->ns);
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

it('creates, reads and deletes a kubernetes.io/tls secret', function () {
    ['cert' => $cert, 'key' => $key] = ClusterFactory::selfSignedCertificate('tls.integration.test');

    $secret = Secret::make()->setCluster($this->cluster)->setNamespace($this->ns)
        ->setName('tls-cert')->asTlsCertificate($cert, $key);

    expect($secret->create()->wasRecentlyCreated())->toBeTrue();

    $found = Secret::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('tls-cert')->find();

    expect($found->getType())->toBe('kubernetes.io/tls')
        ->and($found->getData('tls.crt'))->toBe($cert)
        ->and($found->getData('tls.key'))->toBe($key);

    $found->delete();
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

it('reads logs from a running pod', function () {
    $pod = Pod::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('log-pod')
        ->setContainers([
            Container::make()->setName('main')->setImage('busybox')
                ->setCommand(['sh', '-c', 'echo hello-from-logs; sleep 300']),
        ]);

    $pod->create();

    retry(40, function (): void {
        $current = Pod::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('log-pod')->find();
        throw_unless($current->isRunning(), new RuntimeException('pod not running'));
    }, 500);

    retry(20, function (): void {
        $logs = Pod::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('log-pod')->logs();
        throw_unless(str_contains($logs, 'hello-from-logs'), new RuntimeException('logs not ready'));
    }, 500);

    $logs = Pod::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('log-pod')->logs();

    expect($logs)->toContain('hello-from-logs');
});

it('scales and rollout-restarts a deployment', function () {
    // The Deployment resource now defaults to the apps/v1 group on its own; a
    // live round-trip here is the proof of the apiVersion fix (no override).
    $newDeployment = fn (): Deployment => Deployment::make()
        ->setCluster($this->cluster)->setNamespace($this->ns)->setName('web');

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

it('writes a found deployment back unchanged, empty objects included', function () {
    $deployment = fn (): Deployment => Deployment::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('roundtrip');

    $deployment()
        ->setReplicas(1)
        ->setPodsSelectors(['app' => 'roundtrip'])
        ->setTemplate(Pod::make()->setLabels(['app' => 'roundtrip'])->setContainers([Container::make()->setName('web')->setImage('nginx', 'alpine')]))
        ->create();

    // The apiserver fills in `resources: {}` and `securityContext: {}`; both must go back as
    // objects. The controller's own status writes can win the race, hence the 409 retry.
    $updated = retry(
        5,
        fn (): Deployment => $deployment()->find()->setReplicas(2)->update(),
        300,
        fn (Throwable $e): bool => $e instanceof KubernetesException && $e->response->status() === 409,
    );

    expect($updated->getReplicas())->toBe(2);
});

it('server-side applies with the manager name as the field manager', function () {
    ConfigMap::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('applied')->setData(['a' => '1'])->create();

    $applied = ConfigMap::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('applied')
        ->patch(KubernetesPatch::apply([
            'apiVersion' => 'v1',
            'kind' => 'ConfigMap',
            'metadata' => ['name' => 'applied'],
            'data' => ['nginx.conf' => 'events {}'],
        ], force: true));

    $managers = collect($applied->getAttribute('metadata.managedFields', []))
        ->map(fn (array $entry): string => $entry['manager'].':'.$entry['operation'])
        ->all();

    expect($managers)->toContain('k8s-integration-tests:Apply')
        ->and($applied->getData('nginx.conf'))->toBe('events {}');
});

it('hands watch events over as they arrive and ends on the idle timeout', function () {
    config()->set('kubernetes.client.stream_timeout', 2);

    $started = microtime(true);
    $arrivals = [];

    ConfigMap::make()->setCluster($this->cluster)->setNamespace($this->ns)
        ->watch(function (WatchEvent $event) use (&$arrivals, $started): void {
            $arrivals[] = microtime(true) - $started;
        });

    expect($arrivals)->not->toBeEmpty()
        ->and($arrivals[0])->toBeLessThan(1.5)
        ->and(microtime(true) - $started)->toBeLessThan(5.0);
});
