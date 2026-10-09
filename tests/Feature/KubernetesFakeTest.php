<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\ExecResult;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubernetesPatch;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\PodLogOptions;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\Scale;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\VersionInfo;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\WatchEvent;
use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\KubernetesManager;
use RoundlyConsulting\KubernetesApi\Resources\ConfigMap;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;
use RoundlyConsulting\KubernetesApi\Resources\KubernetesNamespace;
use RoundlyConsulting\KubernetesApi\Resources\Node;
use RoundlyConsulting\KubernetesApi\Resources\Pod;
use RoundlyConsulting\KubernetesApi\Testing\KubernetesFake;
use RoundlyConsulting\KubernetesApi\Testing\RecordedRequest;
use RoundlyConsulting\KubernetesApi\Testing\RequestVerb;

beforeEach(function (): void {
    // The fake never touches the network — any real request fails the test.
    Http::preventStrayRequests();
});

function web(int $replicas = 2, string $namespace = 'default'): array
{
    return [
        'metadata' => ['name' => 'web', 'namespace' => $namespace, 'labels' => ['app' => 'web', 'tier' => 'frontend']],
        'spec' => ['replicas' => $replicas],
    ];
}

it('installs itself behind the facade and the container', function (): void {
    $fake = Kubernetes::fake();

    expect($fake)->toBeInstanceOf(KubernetesFake::class)
        ->and(Kubernetes::getFacadeRoot())->toBe($fake)
        ->and(app(KubernetesManager::class))->toBe($fake);
});

it('keeps clusters registered before the swap', function (): void {
    Kubernetes::registerCluster('prod', fn (Cluster $cluster): Cluster => $cluster->url('https://prod.example')->withManagerName('prod-app'));

    Kubernetes::fake();

    expect(Kubernetes::hasCluster('prod'))->toBeTrue()
        ->and(Kubernetes::cluster('prod')->getManagerName())->toBe('prod-app');
});

it('round-trips seed, list, find, update, patch and delete through the facade', function (): void {
    $fake = Kubernetes::fake()->seed(Deployment::class, [web()]);

    $all = Kubernetes::deployments()->get();
    $found = Kubernetes::deployments()->setName('web')->find();

    expect($all)->toHaveCount(1)
        ->and($all->first())->toBeInstanceOf(Deployment::class)
        ->and($found->getReplicas())->toBe(2)
        ->and($found->exists())->toBeTrue();

    $found->setReplicas(4)->update();
    Kubernetes::deployments()->setName('web')->patch(KubernetesPatch::merge(['metadata' => ['labels' => ['tier' => null, 'env' => 'prod']]]));

    $patched = Kubernetes::deployments()->setName('web')->find();

    expect($patched->getReplicas())->toBe(4)
        ->and($patched->getLabels())->toBe(['app' => 'web', 'env' => 'prod']);

    Kubernetes::deployments()->setName('web')->delete();

    expect(Kubernetes::deployments()->get())->toHaveCount(0)
        ->and(Kubernetes::deployments()->setName('web')->existsOnCluster())->toBeFalse();

    $fake->assertUpdated(Deployment::class, 'web');
    $fake->assertPatched(Deployment::class, 'web');
    $fake->assertDeleted(Deployment::class, 'web');
});

it('creates objects the fake then serves', function (): void {
    $fake = Kubernetes::fake();

    $created = Kubernetes::namespace('prod')->configMaps()->setName('settings')->setData(['KEY' => 'v'])->create();

    expect($created->wasRecentlyCreated())->toBeTrue()
        ->and($created->getAttribute('metadata.uid'))->toBeString()
        ->and($created->getAttribute('metadata.resourceVersion'))->toBeString()
        ->and(Kubernetes::namespace('prod')->configMaps()->setName('settings')->find()->getData())->toBe(['KEY' => 'v'])
        ->and(Kubernetes::configMaps()->get())->toHaveCount(0);

    $fake->assertCreated(ConfigMap::class, 'settings');
    $fake->assertCreated('configMaps', fn (RecordedRequest $request): bool => $request->namespace === 'prod');
});

it('answers a missing object with a real 404', function (): void {
    Kubernetes::fake();

    try {
        Kubernetes::pods()->setName('ghost')->find();
    } catch (KubernetesException $e) {
        expect($e->response->status())->toBe(404)
            ->and($e->getMessage())->toBe('pods "ghost" not found');

        return;
    }

    $this->fail('Expected a 404.');
});

it('refuses a duplicate create with a 409', function (): void {
    Kubernetes::fake()->seed('deployments', [web()]);

    Kubernetes::deployments()->setName('web')->create();
})->throws(KubernetesException::class, 'deployments.apps "web" already exists');

it('refuses a create without a name', function (): void {
    Kubernetes::fake();

    Kubernetes::configMaps()->create();
})->throws(KubernetesException::class, 'name or generateName is required');

it('generates a name from metadata.generateName, as the apiserver does', function (): void {
    Kubernetes::fake();

    $created = Kubernetes::configMaps()->setAttribute('metadata.generateName', 'job-')->create();
    $name = $created->getName();

    expect($created->wasRecentlyCreated())->toBeTrue()
        ->and($name)->toMatch('/^job-[a-z0-9]{5}$/')
        ->and($created->getAttribute('metadata.generateName'))->toBe('job-')
        ->and(Kubernetes::configMaps()->setName((string) $name)->find()->getName())->toBe($name)
        ->and(Kubernetes::configMaps()->setAttribute('metadata.generateName', 'job-')->create()->getName())->not->toBe($name);
});

it('refuses a stale update with a 409 conflict', function (): void {
    Kubernetes::fake()->seed(Deployment::class, [web()]);

    $stale = Kubernetes::deployments()->setName('web')->find();
    Kubernetes::deployments()->setName('web')->find()->setReplicas(3)->update();

    $stale->setReplicas(9)->update();
})->throws(KubernetesException::class, 'the object has been modified');

it('updates or creates', function (): void {
    $fake = Kubernetes::fake();

    Kubernetes::deployments()->setName('web')->setReplicas(1)->updateOrCreate();
    Kubernetes::deployments()->setName('web')->setReplicas(3)->updateOrCreate();

    expect(Kubernetes::deployments()->setName('web')->find()->getReplicas())->toBe(3);

    $fake->assertCreated(Deployment::class, 'web');
    $fake->assertUpdated(Deployment::class, 'web');
});

it('keeps the stored status on update and patch, as the apiserver does', function (): void {
    // The main resource ignores status writes (only the status subresource changes
    // it): a freshly built manifest must not wipe a seeded status.
    Kubernetes::fake()->seed(Deployment::class, [[...web(), 'status' => ['readyReplicas' => 2]]]);

    $updated = Kubernetes::deployments()->setName('web')->setReplicas(4)->updateOrCreate();

    expect($updated->getAttribute('status'))->toBe(['readyReplicas' => 2])
        ->and($updated->getReplicas())->toBe(4)
        ->and(Kubernetes::deployments()->setName('web')->find()->getAttribute('status'))->toBe(['readyReplicas' => 2]);

    $put = Kubernetes::deployments()->setName('web')->find()->setAttribute('status.readyReplicas', 99)->setReplicas(5)->update();

    expect($put->getAttribute('status'))->toBe(['readyReplicas' => 2])
        ->and($put->getReplicas())->toBe(5);

    $patched = Kubernetes::deployments()->setName('web')->patch(KubernetesPatch::merge([
        'spec' => ['replicas' => 6],
        'status' => ['readyReplicas' => 42],
    ]));

    expect($patched->getAttribute('status'))->toBe(['readyReplicas' => 2])
        ->and($patched->getReplicas())->toBe(6)
        ->and(Kubernetes::deployments()->setName('web')->patch(KubernetesPatch::json([
            ['op' => 'replace', 'path' => '/status/readyReplicas', 'value' => 7],
        ]))->getAttribute('status'))->toBe(['readyReplicas' => 2]);
});

it('does not invent a status an object never had', function (): void {
    Kubernetes::fake()->seed(ConfigMap::class, [['metadata' => ['name' => 'cfg'], 'data' => ['a' => 'b']]]);

    $updated = Kubernetes::configMaps()->setName('cfg')->setAttribute('status', ['x' => 1])->setData(['a' => 'c'])->update();

    expect($updated->getAttribute('status'))->toBeNull()
        ->and($updated->getData())->toBe(['a' => 'c']);
});

it('filters by label and field selectors', function (): void {
    Kubernetes::fake()->seed('pods', [
        ['metadata' => ['name' => 'web-1', 'labels' => ['app' => 'web', 'tier' => 'frontend']], 'status' => ['phase' => 'Running']],
        ['metadata' => ['name' => 'web-2', 'labels' => ['app' => 'web', 'tier' => 'backend']], 'status' => ['phase' => 'Pending']],
        ['metadata' => ['name' => 'db', 'labels' => ['app' => 'db', 'team' => 'data']], 'status' => ['phase' => 'Running']],
    ]);

    $names = fn ($pods): array => $pods->map(fn (Pod $pod): string => $pod->getName())->all();

    expect($names(Kubernetes::pods()->whereLabel('app', 'web')->get()))->toBe(['web-1', 'web-2'])
        ->and($names(Kubernetes::pods()->whereLabelNot('app', 'web')->get()))->toBe(['db'])
        ->and($names(Kubernetes::pods()->whereLabelIn('tier', ['frontend', 'edge'])->get()))->toBe(['web-1'])
        ->and($names(Kubernetes::pods()->whereLabelNotIn('tier', ['frontend'])->get()))->toBe(['db', 'web-2'])
        ->and($names(Kubernetes::pods()->whereLabelExists('team')->get()))->toBe(['db'])
        ->and($names(Kubernetes::pods()->whereLabelMissing('team')->get()))->toBe(['web-1', 'web-2'])
        ->and($names(Kubernetes::pods()->whereField('status.phase', 'Running')->get()))->toBe(['db', 'web-1'])
        ->and($names(Kubernetes::pods()->whereFieldNot('metadata.name', 'db')->get()))->toBe(['web-1', 'web-2'])
        ->and($names(Kubernetes::pods()->whereLabel('app', 'web')->whereField('status.phase', 'Pending')->get()))->toBe(['web-2']);
});

it('matches exists and missing field requirements', function (): void {
    Kubernetes::fake()->seed('pods', [
        ['metadata' => ['name' => 'a'], 'spec' => ['nodeName' => 'n1']],
        ['metadata' => ['name' => 'b']],
    ]);

    $fake = Kubernetes::getFacadeRoot();

    expect(Kubernetes::cluster()->request('GET', '/api/v1/namespaces/default/pods', ['fieldSelector' => 'spec.nodeName'])->json('items.*.metadata.name'))->toBe(['a'])
        ->and(Kubernetes::cluster()->request('GET', '/api/v1/namespaces/default/pods', ['fieldSelector' => '!spec.nodeName'])->json('items.*.metadata.name'))->toBe(['b'])
        ->and($fake)->toBeInstanceOf(KubernetesFake::class);
});

it('paginates with limit and continue', function (): void {
    Kubernetes::fake()->seed('configMaps', array_map(
        fn (int $i): array => ['metadata' => ['name' => "cm-{$i}"]],
        range(1, 5),
    ));

    $first = Kubernetes::configMaps()->limit(2)->getPage();
    $second = Kubernetes::configMaps()->limit(2)->continueFrom($first->continue)->getPage();
    $everything = iterator_to_array(Kubernetes::configMaps()->limit(2)->lazy(), false);

    expect($first->items)->toHaveCount(2)
        ->and($first->continue)->toBe('2')
        ->and($first->remainingItemCount)->toBe(3)
        ->and($second->items->first()->getName())->toBe('cm-3')
        ->and($everything)->toHaveCount(5);
});

it('reads limit 0 as no limit, like the apiserver', function (): void {
    Kubernetes::fake()->seed('configMaps', array_map(
        fn (string $name): array => ['metadata' => ['name' => $name]],
        ['a', 'b', 'c'],
    ));

    $page = Kubernetes::configMaps()->limit(0)->getPage();

    expect($page->items)->toHaveCount(3)
        ->and($page->continue)->toBeNull()
        ->and($page->remainingItemCount)->toBeNull();
});

it('leaves the builder on its own page after lazy iteration', function (): void {
    // lazy() used to leave the last continue token on the builder: a second lazy()
    // or get() returned only the last page.
    Kubernetes::fake()->seed('configMaps', array_map(
        fn (string $name): array => ['metadata' => ['name' => $name]],
        ['a', 'b', 'c'],
    ));

    $names = fn (iterable $items): array => array_map(fn (ConfigMap $item): ?string => $item->getName(), [...$items]);
    $builder = Kubernetes::configMaps()->limit(1);

    expect($names($builder->lazy()))->toBe(['a', 'b', 'c'])
        ->and($names($builder->lazy()))->toBe(['a', 'b', 'c'])
        ->and($names($builder->get()))->toBe(['a']);

    $names = [];
    $builder->each(function (ConfigMap $item) use (&$names): void {
        $names[] = $item->getName();
    });

    expect($names)->toBe(['a', 'b', 'c'])
        ->and($builder->get()->map(fn (ConfigMap $item): ?string => $item->getName())->all())->toBe(['a']);
});

it('starts lazy iteration from a set continue token and keeps it', function (): void {
    Kubernetes::fake()->seed('configMaps', array_map(
        fn (string $name): array => ['metadata' => ['name' => $name]],
        ['a', 'b', 'c'],
    ));

    $builder = Kubernetes::configMaps()->limit(1)->continueFrom('1');
    $names = array_map(fn (ConfigMap $item): ?string => $item->getName(), [...$builder->lazy()]);

    expect($names)->toBe(['b', 'c'])
        ->and($builder->get()->map(fn (ConfigMap $item): ?string => $item->getName())->all())->toBe(['b']);
});

it('lists across namespaces and keeps namespaces apart', function (): void {
    Kubernetes::fake()->seed(Deployment::class, [web(namespace: 'prod'), web(namespace: 'staging')]);

    expect(Kubernetes::deployments()->allNamespaces()->get())->toHaveCount(2)
        ->and(Kubernetes::namespace('prod')->deployments()->get())->toHaveCount(1)
        ->and(Kubernetes::deployments()->get())->toHaveCount(0);
});

it('keeps every cluster in its own store', function (): void {
    Kubernetes::registerCluster('prod', fn (Cluster $cluster): Cluster => $cluster);
    Kubernetes::fake()->seed(Deployment::class, [web()], cluster: 'prod');

    expect(Kubernetes::cluster('prod')->deployments()->get())->toHaveCount(1)
        ->and(Kubernetes::deployments()->get())->toHaveCount(0);

    Kubernetes::deployments()->setName('web')->create();

    expect(Kubernetes::deployments()->get())->toHaveCount(1);
});

it('serves ad-hoc clusters from the default store and never reads credentials', function (): void {
    Kubernetes::fake()->seed('nodes', [['metadata' => ['name' => 'node-1']]]);

    expect(Kubernetes::fromKubeConfig('/does/not/exist')->nodes()->get())->toHaveCount(1)
        ->and(Kubernetes::inCluster()->nodes()->get())->toHaveCount(1)
        ->and(Kubernetes::url('https://ad-hoc.example')->nodes()->get())->toHaveCount(1);
});

it('does not read credentials for kubeconfig and in-cluster sources under the fake', function (): void {
    config()->set('kubernetes.clusters.local', ['source' => 'kubeconfig', 'kubeconfig' => '/does/not/exist']);
    config()->set('kubernetes.clusters.pod', ['source' => 'in-cluster', 'namespace' => 'apps']);

    Kubernetes::fake();

    expect(Kubernetes::cluster('local')->ping())->toBeTrue()
        ->and(Kubernetes::cluster('pod')->pods()->getNamespace())->toBe('apps');
});

it('seeds resource objects and cluster-scoped kinds', function (): void {
    Kubernetes::fake()->seed(KubernetesNamespace::class, [KubernetesNamespace::make()->setName('prod')]);

    expect(Kubernetes::namespaces()->setName('prod')->find()->getName())->toBe('prod')
        ->and(Kubernetes::namespaces()->get())->toHaveCount(1);
});

it('refuses to seed an object without a name or of an unknown resource', function (string $resource, array $item, string $message): void {
    expect(fn () => Kubernetes::fake()->seed($resource, [$item]))->toThrow(InvalidResourceException::class, $message);
})->with([
    'no name' => ['pods', ['spec' => []], 'needs metadata.name'],
    'unknown resource' => ['gizmos', ['metadata' => ['name' => 'a']], "'gizmos' is neither a resource class nor a registered resource name"],
]);

it('scales and reads the scale subresource', function (): void {
    $fake = Kubernetes::fake()->seed(Deployment::class, [web()]);

    $scale = Kubernetes::deployments()->setName('web')->scale(5);

    expect($scale)->toBeInstanceOf(Scale::class)
        ->and($scale->name)->toBe('web')
        ->and($scale->namespace)->toBe('default')
        ->and($scale->replicas)->toBe(5)
        ->and($scale->currentReplicas)->toBe(5)
        ->and($scale->resourceVersion)->not->toBeNull()
        ->and(Kubernetes::deployments()->setName('web')->find()->getReplicas())->toBe(5)
        ->and(Kubernetes::cluster()->request('GET', '/apis/apps/v1/namespaces/default/deployments/web/scale')->json('spec.replicas'))->toBe(5);

    $fake->assertScaled(Deployment::class, 'web');
    $fake->assertScaled(Deployment::class, 'web', 5);
    $fake->assertPatched(Deployment::class, 'web');

    expect(fn () => $fake->assertScaled(Deployment::class, 'web', 7))->toThrow(AssertionFailedError::class, "Expected RoundlyConsulting\\KubernetesApi\\Resources\\Deployment 'web' to be scaled to 7");
});

it('records a rollout restart', function (): void {
    $fake = Kubernetes::fake()->seed(Deployment::class, [web()]);

    Kubernetes::deployments()->setName('web')->rolloutRestart();

    expect(Kubernetes::deployments()->setName('web')->find()->getAttribute('spec.template.metadata.annotations'))->toHaveKey('kubectl.kubernetes.io/restartedAt');

    $fake->assertRestarted(Deployment::class, 'web');
    expect(fn () => $fake->assertRestarted(Deployment::class, 'api'))->toThrow(AssertionFailedError::class, "'api' to be rollout-restarted")
        ->and(fn () => $fake->assertNothingRestarted())->toThrow(AssertionFailedError::class, 'Expected no rollout restart, but 1 were sent.');
});

it('applies json patches', function (): void {
    Kubernetes::fake()->seed(Deployment::class, [[
        'metadata' => ['name' => 'web', 'annotations' => ['a~b' => 'x', 'drop' => 'me']],
        'spec' => ['replicas' => 1, 'template' => ['spec' => ['containers' => [['name' => 'app'], ['name' => 'sidecar']]]]],
    ]]);

    $patched = Kubernetes::deployments()->setName('web')->patch(KubernetesPatch::json([
        ['op' => 'test', 'path' => '/spec/replicas', 'value' => 1],
        ['op' => 'replace', 'path' => '/spec/replicas', 'value' => 3],
        ['op' => 'add', 'path' => '/metadata/labels/app', 'value' => 'web'],
        ['op' => 'add', 'path' => '/spec/template/spec/containers/1', 'value' => ['name' => 'init']],
        ['op' => 'add', 'path' => '/spec/template/spec/containers/-', 'value' => ['name' => 'last']],
        ['op' => 'remove', 'path' => '/spec/template/spec/containers/0'],
        ['op' => 'remove', 'path' => '/metadata/annotations/drop'],
        ['op' => 'replace', 'path' => '/metadata/annotations/a~0b', 'value' => 'y'],
        ['op' => 'remove', 'path' => '/status/missing/deep'],
    ]));

    expect($patched->getReplicas())->toBe(3)
        ->and($patched->getLabels())->toBe(['app' => 'web'])
        ->and($patched->getAnnotations())->toBe(['a~b' => 'y'])
        ->and(array_column($patched->getAttribute('spec.template.spec.containers'), 'name'))->toBe(['init', 'sidecar', 'last']);
});

it('rejects a json patch that fails', function (array $operation): void {
    Kubernetes::fake()->seed(Deployment::class, [web()]);

    Kubernetes::deployments()->setName('web')->patch(KubernetesPatch::json([$operation]));
})->with([
    'failed test' => [['op' => 'test', 'path' => '/spec/replicas', 'value' => 99]],
    'unsupported op' => [['op' => 'move', 'from' => '/a', 'path' => '/b']],
    'no path' => [['op' => 'add']],
])->throws(KubernetesException::class, 'could not be applied');

it('answers a patch, update or delete of a missing object with a 404', function (Closure $call): void {
    Kubernetes::fake();

    $call();
})->with([
    'patch' => [fn () => Kubernetes::deployments()->setName('ghost')->patch(KubernetesPatch::merge(['a' => 1]))],
    'update' => [fn () => Kubernetes::deployments()->setName('ghost')->update()],
    'delete' => [fn () => Kubernetes::deployments()->setName('ghost')->delete()],
])->throws(KubernetesException::class, 'deployments.apps "ghost" not found');

it('validates dry runs without persisting them or satisfying the asserts', function (): void {
    $fake = Kubernetes::fake()->seed(Deployment::class, [web()]);

    Kubernetes::configMaps()->setName('draft')->dryRun()->create();
    Kubernetes::deployments()->setName('web')->dryRun()->scale(9);
    Kubernetes::deployments()->setName('web')->find()->setReplicas(8)->dryRun()->update();
    Kubernetes::deployments()->setName('web')->dryRun()->delete();

    expect(Kubernetes::configMaps()->get())->toHaveCount(0)
        ->and(Kubernetes::deployments()->setName('web')->find()->getReplicas())->toBe(2)
        ->and($fake->recorded(fn (RecordedRequest $request): bool => $request->isDryRun()))->toHaveCount(4);

    $fake->assertNothingCreated();
    $fake->assertNothingScaled();
    $fake->assertNothingUpdated();
    $fake->assertNothingDeleted();
});

it('streams a watch as ADDED events for the matching objects', function (): void {
    Kubernetes::fake()->seed('pods', [
        ['metadata' => ['name' => 'a', 'labels' => ['app' => 'web']]],
        ['metadata' => ['name' => 'b', 'labels' => ['app' => 'db']]],
    ]);

    $events = [];
    Kubernetes::pods()->whereLabel('app', 'web')->watch(function (WatchEvent $event) use (&$events): void {
        $events[] = $event;
    });

    $none = [];
    Kubernetes::pods()->whereLabel('app', 'nothing')->watch(function (WatchEvent $event) use (&$none): void {
        $none[] = $event;
    });

    expect($events)->toHaveCount(1)
        ->and($events[0]->isAdded())->toBeTrue()
        ->and($events[0]->object->getName())->toBe('a')
        ->and($none)->toBe([]);
});

it('serves seeded logs, tailed and streamed', function (): void {
    Kubernetes::fake()->seedLogs('api', "one\ntwo\nthree\n", namespace: 'prod');

    $pod = Kubernetes::namespace('prod')->pods()->setName('api');

    expect($pod->logs())->toBe("one\ntwo\nthree\n")
        ->and($pod->logs(new PodLogOptions(tailLines: 2)))->toBe("two\nthree")
        ->and(iterator_to_array($pod->streamLogs(), false))->toBe(['one', 'two', 'three'])
        ->and(Kubernetes::pods()->setName('api')->logs())->toBe('');
});

it('records exec and returns the stubbed result', function (): void {
    $fake = Kubernetes::fake();

    $default = Kubernetes::pods()->setName('api')->exec(['ls']);

    $fake->stubExec(new ExecResult("hi\n", '', 0));
    $fixed = Kubernetes::pods()->setName('api')->exec(['sh', '-c', 'echo hi'], container: 'app');

    $fake->stubExec(fn (RecordedRequest $request): ExecResult => new ExecResult('', implode(' ', $request->command), 1));
    $dynamic = Kubernetes::pods()->setName('worker')->exec(['false']);

    expect($default->successful())->toBeTrue()
        ->and($fixed->stdout)->toBe("hi\n")
        ->and($dynamic->stderr)->toBe('false')
        ->and($dynamic->exitCode)->toBe(1)
        ->and($fake->recorded(fn (RecordedRequest $request): bool => $request->verb === RequestVerb::Exec)[1]->container)->toBe('app');

    $fake->assertExecuted('api');
    $fake->assertExecuted('api', ['sh', '-c', 'echo hi']);

    expect(fn () => $fake->assertExecuted('api', ['rm', '-rf', '/']))->toThrow(AssertionFailedError::class, "Expected `rm -rf /` to be executed in pod 'api'")
        ->and(fn () => $fake->assertExecuted('db'))->toThrow(AssertionFailedError::class, "Expected to be executed in pod 'db'")
        ->and(fn () => $fake->assertNothingExecuted())->toThrow(AssertionFailedError::class, 'Expected nothing to be executed, but 3 request(s) were.');
});

it('reports a stubbed version and pings', function (): void {
    $fake = Kubernetes::fake();

    expect(Kubernetes::version()->gitVersion)->toBe('v1.34.0')
        ->and(Kubernetes::ping())->toBeTrue();

    $fake->stubVersion('v1.30.2');
    expect(Kubernetes::version())->major->toBe('1')->minor->toBe('30');

    $fake->stubVersion(new VersionInfo(major: '1', minor: '29', gitVersion: 'v1.29.0+k3s1', platform: 'linux/arm64'));
    expect(Kubernetes::version()->platform)->toBe('linux/arm64');
});

it('simulates an unreachable apiserver', function (): void {
    $fake = Kubernetes::fake()->unreachable();

    expect(Kubernetes::ping())->toBeFalse()
        ->and(fn () => Kubernetes::pods()->get())->toThrow(ConnectionException::class, 'Kubernetes fake: cluster default is unreachable.')
        ->and(fn () => Kubernetes::url('https://x.example')->pods()->setName('a')->exec(['ls']))->toThrow(ConnectionException::class, 'cluster ad-hoc is unreachable');

    $fake->unreachable(false);

    expect(Kubernetes::ping())->toBeTrue();
});

it('answers an unknown path with a 404', function (string $method, string $path): void {
    Kubernetes::fake();

    Kubernetes::cluster()->request($method, $path);
})->with([
    ['GET', '/healthz'],
    ['POST', '/api/v1/namespaces/default/pods/api/eviction'],
    ['PUT', '/api/v1/namespaces/default/pods'],
    ['OPTIONS', '/api/v1/pods'],
])->throws(KubernetesException::class, 'the server could not find the requested resource');

it('records every request and asserts on any of them', function (): void {
    $fake = Kubernetes::fake();

    $fake->assertNothingSent();
    expect(fn () => $fake->assertSent(fn (): bool => true))->toThrow(AssertionFailedError::class, 'No matching Kubernetes request was sent.');

    Kubernetes::pods()->get();

    $fake->assertSent(fn (RecordedRequest $request): bool => $request->verb === RequestVerb::List && $request->plural === 'pods');

    expect($fake->recorded())->toHaveCount(1)
        ->and($fake->recorded()[0]->cluster)->toBe('default')
        ->and($fake->recorded()[0]->input('metadata.name', 'none'))->toBe('none')
        ->and(fn () => $fake->assertNothingSent())->toThrow(AssertionFailedError::class, 'Expected no Kubernetes request, but 1 were sent.');
});

it('passes and fails every mutation assert', function (): void {
    $fake = Kubernetes::fake()->seed(Deployment::class, [web()]);

    foreach (['assertNothingCreated', 'assertNothingUpdated', 'assertNothingPatched', 'assertNothingDeleted', 'assertNothingScaled', 'assertNothingRestarted', 'assertNothingExecuted'] as $assert) {
        $fake->{$assert}();
    }

    expect(fn () => $fake->assertCreated(Deployment::class))->toThrow(AssertionFailedError::class, 'Expected RoundlyConsulting\KubernetesApi\Resources\Deployment to be created, but it was not.')
        ->and(fn () => $fake->assertUpdated(Deployment::class, 'web'))->toThrow(AssertionFailedError::class, "Deployment 'web' to be updated")
        ->and(fn () => $fake->assertPatched('deployments', 'web'))->toThrow(AssertionFailedError::class, "Deployment 'web' to be patched")
        ->and(fn () => $fake->assertDeleted(Deployment::class, 'web'))->toThrow(AssertionFailedError::class, "Deployment 'web' to be deleted")
        ->and(fn () => $fake->assertScaled(Deployment::class, 'web'))->toThrow(AssertionFailedError::class, "Deployment 'web' to be scaled");

    Kubernetes::deployments()->setName('api')->create();
    Kubernetes::deployments()->setName('web')->find()->update();
    Kubernetes::deployments()->setName('web')->patch(KubernetesPatch::strategicMerge(['spec' => ['paused' => true]]));
    Kubernetes::deployments()->setName('web')->scale(3);
    Kubernetes::deployments()->setName('web')->delete();

    $fake->assertCreated(Deployment::class);
    $fake->assertCreated(Deployment::class, 'api');
    $fake->assertUpdated(Deployment::class, 'web');
    $fake->assertPatched(Deployment::class, fn (RecordedRequest $request): bool => $request->input('spec.paused') === true);
    $fake->assertDeleted('deployments', 'web');

    expect(fn () => $fake->assertCreated(Deployment::class, 'web'))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertCreated(Node::class))->toThrow(AssertionFailedError::class, 'Node to be created')
        ->and(fn () => $fake->assertNothingCreated())->toThrow(AssertionFailedError::class, 'Expected nothing to be created, but 1 request(s) were.')
        ->and(fn () => $fake->assertNothingUpdated())->toThrow(AssertionFailedError::class, 'to be updated, but 1')
        ->and(fn () => $fake->assertNothingPatched())->toThrow(AssertionFailedError::class, 'to be patched, but 2')
        ->and(fn () => $fake->assertNothingDeleted())->toThrow(AssertionFailedError::class, 'to be deleted, but 1')
        ->and(fn () => $fake->assertNothingScaled())->toThrow(AssertionFailedError::class, 'Expected nothing to be scaled, but 1 scale request(s) were sent.');
});

it('records calls made through a standalone resource bound to a faked cluster', function (): void {
    $fake = Kubernetes::fake();

    ConfigMap::make()->setCluster(Kubernetes::cluster())->setName('settings')->create();

    $fake->assertCreated(ConfigMap::class, 'settings');
});

it('records the cluster a request went to', function (): void {
    Kubernetes::registerCluster('prod', fn (Cluster $cluster): Cluster => $cluster);
    $fake = Kubernetes::fake();

    Kubernetes::cluster('prod')->configMaps()->setName('a')->create();

    $fake->assertCreated(ConfigMap::class, fn (RecordedRequest $request): bool => $request->cluster === 'prod');

    expect(fn () => $fake->assertCreated(ConfigMap::class, fn (RecordedRequest $request): bool => $request->cluster === 'default'))
        ->toThrow(AssertionFailedError::class);
});

it('never overwrites a locally set resourceVersion in updateOrCreate', function (): void {
    Kubernetes::fake()->seed(Deployment::class, [web()]);

    Kubernetes::deployments()->setName('web')->setAttribute('metadata.resourceVersion', 'stale')->updateOrCreate();
})->throws(KubernetesException::class, 'the object has been modified');

it('tells mutating verbs from reads', function (RequestVerb $verb, bool $mutates): void {
    expect($verb->mutates())->toBe($mutates);
})->with([
    [RequestVerb::Create, true],
    [RequestVerb::Update, true],
    [RequestVerb::Patch, true],
    [RequestVerb::Delete, true],
    [RequestVerb::Exec, true],
    [RequestVerb::Get, false],
    [RequestVerb::List, false],
    [RequestVerb::Watch, false],
    [RequestVerb::Logs, false],
    [RequestVerb::Version, false],
    [RequestVerb::Unknown, false],
]);
