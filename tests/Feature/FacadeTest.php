<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\VersionInfo;
use RoundlyConsulting\KubernetesApi\Exceptions\ClusterConfigurationException;
use RoundlyConsulting\KubernetesApi\Exceptions\ClusterNotFoundException;
use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;
use RoundlyConsulting\KubernetesApi\Exceptions\KubeConfigException;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\KubernetesManager;
use RoundlyConsulting\KubernetesApi\Resources;
use RoundlyConsulting\KubernetesApi\Resources\Pod;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Tests\Fixtures\CustomPod;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

/*
 * No `toReachEveryAction()`: this package has no `src/Actions`. It is a remote-API
 * client — the facade hands out Cluster clients and resource objects instead (see the
 * skill's "remote-API resource clients" shape), and every one of them is reached
 * through the manager's native return types.
 */
it('documents its root and is fakeable', function (): void {
    expect(Kubernetes::class)
        ->toDocumentItsRoot()
        ->toBeFakeable();
});

it('resolves the default cluster from config', function (): void {
    config()->set('kubernetes.clusters.default', [
        'url' => 'https://k8s.example:6443',
        'token' => 'config-token',
        'ca_certificate' => '/ca.crt',
        'verify' => true,
        'namespace' => 'apps',
        'manager' => 'my-app',
    ]);

    $cluster = Kubernetes::cluster();

    expect($cluster->name())->toBe('default')
        ->and($cluster->getUrl())->toBe('https://k8s.example:6443')
        ->and($cluster->getToken())->toBe('config-token')
        ->and($cluster->getPathToCaCertificate())->toBe('/ca.crt')
        ->and($cluster->getManagerName())->toBe('my-app')
        ->and($cluster->defaultNamespace())->toBe('apps')
        ->and(Kubernetes::pods()->getNamespace())->toBe('apps')
        ->and(Kubernetes::cluster())->toBe($cluster);
});

it('reads a configured cluster verify switch the way an env file writes it', function (mixed $value, bool $verifies): void {
    config()->set('kubernetes.clusters.default', ['url' => 'https://k8s.example', 'verify' => $value]);

    expect(Kubernetes::cluster()->shouldVerify())->toBe($verifies);
})->with([
    'unset' => [null, true],
    'off' => ['off', false],
    '0' => ['0', false],
    'false' => [false, false],
    'yes' => ['yes', true],
    '1' => ['1', true],
]);

it('refuses an unreadable verify switch instead of turning TLS verification off (strict config)', function (): void {
    // filter_var() read KUBERNETES_VERIFY_SSL=ture as false: certificate checks silently off.
    config()->set('kubernetes.clusters.default', ['url' => 'https://k8s.example', 'verify' => 'ture']);

    expect(fn () => Kubernetes::cluster())->toThrow(
        ClusterConfigurationException::class,
        'Configuration value [kubernetes.clusters.default.verify] must be a boolean',
    );
});

it('picks the default cluster by name from config', function (): void {
    config()->set('kubernetes.default', 'prod');
    config()->set('kubernetes.clusters.prod', ['url' => 'https://prod.example']);

    expect(Kubernetes::cluster()->name())->toBe('prod')
        ->and(Kubernetes::cluster()->getUrl())->toBe('https://prod.example');
});

it('refuses a non-string or blank cluster setting instead of dropping it (strict config)', function (array $definition, string $key): void {
    config()->set('kubernetes.clusters.default', $definition);

    expect(fn () => Kubernetes::cluster())->toThrow(
        ClusterConfigurationException::class,
        "Configuration value [kubernetes.clusters.default.{$key}] must be a non-empty string",
    );
})->with([
    'url empty env' => [['url' => ''], 'url'],
    'url port only' => [['url' => 6443], 'url'],
    'token empty env' => [['url' => 'https://k8s.example', 'token' => ''], 'token'],
    'token list' => [['url' => 'https://k8s.example', 'token' => ['t']], 'token'],
    'certificate bool' => [['url' => 'https://k8s.example', 'certificate' => true], 'certificate'],
    'private key blank' => [['url' => 'https://k8s.example', 'private_key' => ' '], 'private_key'],
    'ca certificate empty env' => [['url' => 'https://k8s.example', 'ca_certificate' => ''], 'ca_certificate'],
    'namespace empty env' => [['url' => 'https://k8s.example', 'namespace' => ''], 'namespace'],
    'manager int' => [['url' => 'https://k8s.example', 'manager' => 1], 'manager'],
    'kubeconfig empty env' => [['source' => 'kubeconfig', 'kubeconfig' => ''], 'kubeconfig'],
    'context int' => [['source' => 'kubeconfig', 'context' => 3], 'context'],
]);

it('refuses a non-string cluster source instead of assuming url (strict config)', function (): void {
    config()->set('kubernetes.clusters.default', ['source' => true, 'url' => 'https://k8s.example']);

    expect(fn () => Kubernetes::cluster())->toThrow(ClusterConfigurationException::class, "unknown source 'true'");
});

it('refuses a cluster definition that is not an array (strict config)', function (): void {
    config()->set('kubernetes.clusters.prod', 'https://prod.example');

    expect(fn () => Kubernetes::cluster('prod'))->toThrow(
        ClusterConfigurationException::class,
        'Configuration value [kubernetes.clusters.prod] must be an array, [https://prod.example] given.',
    );
});

it('refuses a clusters map that is not an array (strict config)', function (): void {
    config()->set('kubernetes.clusters', 'default');

    expect(fn () => Kubernetes::cluster())->toThrow(
        ClusterConfigurationException::class,
        'Configuration value [kubernetes.clusters] must be an array, [default] given.',
    );
});

it('refuses a blank or non-string default cluster name (strict config)', function (mixed $value): void {
    config()->set('kubernetes.default', $value);

    expect(fn () => Kubernetes::cluster())->toThrow(
        ClusterConfigurationException::class,
        'Configuration value [kubernetes.default] must be a non-empty string',
    );
})->with(['empty env' => [''], 'int' => [1], 'list' => [['prod']]]);

it('uses the default cluster name when kubernetes.default is unset (strict config)', function (): void {
    config()->set('kubernetes.default', null);
    config()->set('kubernetes.clusters.default', ['url' => 'https://k8s.example']);

    expect(Kubernetes::cluster()->name())->toBe('default');
});

it('refuses a configured resource class that is not a resource (strict config)', function (): void {
    config()->set('kubernetes.resources.pods', stdClass::class);

    expect(fn () => Kubernetes::pods())->toThrow(InvalidResourceException::class, 'stdClass is not a '.Resource::class);
});

it('uses the bundled resource class when no resources map is configured (strict config)', function (): void {
    config()->set('kubernetes.resources', null);

    expect(Kubernetes::pods())->toBeInstanceOf(Pod::class);
});

it('refuses a resources map that is not an array (strict config)', function (): void {
    config()->set('kubernetes.resources', Pod::class);

    expect(fn () => Kubernetes::pods())->toThrow(
        InvalidResourceException::class,
        'Configuration value [kubernetes.resources] must be an array of resource classes',
    );
});

it('builds a configured cluster from a kubeconfig file', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'kc-');
    file_put_contents($path, <<<'YAML'
        apiVersion: v1
        current-context: ctx
        clusters:
          - name: c
            cluster:
              server: https://api:6443
        users:
          - name: u
            user:
              token: from-file
        contexts:
          - name: ctx
            context:
              cluster: c
              user: u
        YAML);

    config()->set('kubernetes.clusters.local', ['source' => 'kubeconfig', 'kubeconfig' => $path, 'context' => 'ctx']);

    $cluster = Kubernetes::cluster('local');

    expect($cluster->name())->toBe('local')
        ->and($cluster->getUrl())->toBe('https://api:6443')
        ->and($cluster->getToken())->toBe('from-file');

    @unlink($path);
});

it('refuses an unknown cluster source', function (): void {
    config()->set('kubernetes.clusters.odd', ['source' => 'carrier-pigeon']);

    Kubernetes::cluster('odd');
})->throws(ClusterConfigurationException::class, "unknown source 'carrier-pigeon'");

it('reads in-cluster credentials for an in-cluster source', function (): void {
    config()->set('kubernetes.clusters.pod', ['source' => 'in-cluster']);

    putenv('KUBERNETES_SERVICE_HOST');

    Kubernetes::cluster('pod');
})->throws(KubeConfigException::class, 'Not running in-cluster');

it('registers clusters in code, overriding config, and lists them', function (): void {
    Kubernetes::registerCluster('prod', fn (Cluster $cluster): Cluster => $cluster->url('https://prod.example')->withToken('prod-token'));
    Kubernetes::registerCluster('default', fn (Cluster $cluster): Cluster => $cluster->url('https://code.example'));

    expect(Kubernetes::hasCluster('prod'))->toBeTrue()
        ->and(Kubernetes::hasCluster('default'))->toBeTrue()
        ->and(Kubernetes::hasCluster('staging'))->toBeFalse()
        ->and(Kubernetes::clusters())->toBe(['default', 'prod'])
        ->and(Kubernetes::cluster('prod')->name())->toBe('prod')
        ->and(Kubernetes::cluster('prod')->getToken())->toBe('prod-token')
        ->and(Kubernetes::cluster()->getUrl())->toBe('https://code.example');
});

it('resolves a registered cluster once and re-resolves after re-registration', function (): void {
    $runs = 0;

    Kubernetes::registerCluster('prod', function (Cluster $cluster) use (&$runs): Cluster {
        $runs++;

        return $cluster->url('https://one.example');
    });

    Kubernetes::cluster('prod');
    Kubernetes::cluster('prod');

    Kubernetes::registerCluster('prod', fn (Cluster $cluster): Cluster => $cluster->url('https://two.example'));

    expect($runs)->toBe(1)
        ->and(Kubernetes::cluster('prod')->getUrl())->toBe('https://two.example');
});

it('throws for an unknown cluster', function (): void {
    Kubernetes::cluster('nowhere');
})->throws(ClusterNotFoundException::class, "No cluster 'nowhere' definition found.");

it('throws when a definition returns no cluster', function (): void {
    Kubernetes::registerCluster('broken', fn (Cluster $cluster) => 'nope');

    Kubernetes::cluster('broken');
})->throws(ClusterConfigurationException::class, 'got string');

it('registers a custom resource for every cluster', function (): void {
    Kubernetes::registerCluster('prod', fn (Cluster $cluster): Cluster => $cluster->url('https://prod.example'));
    $prod = Kubernetes::cluster('prod');

    Kubernetes::registerResource('applications', CustomPod::class);

    expect(Kubernetes::applications())->toBeInstanceOf(CustomPod::class)
        ->and(Kubernetes::applications()->getCluster())->toBe(Kubernetes::cluster())
        ->and($prod->applications()->getCluster())->toBe($prod)
        ->and($prod->hasResource('applications'))->toBeTrue();
});

it('swaps a packaged accessor at runtime', function (): void {
    Kubernetes::registerResource('pods', CustomPod::class);

    expect(Kubernetes::pods())->toBeInstanceOf(CustomPod::class);
});

it('refuses a class that is not a resource', function (): void {
    Kubernetes::registerResource('widgets', stdClass::class);
})->throws(InvalidResourceException::class, 'is not a');

it('refuses a name a cluster method already answers', function (string $name): void {
    Kubernetes::registerResource($name, Pod::class);
})->with(['url', 'namespace', 'not a method'])->throws(InvalidResourceException::class, 'cannot be used as a resource name');

it('throws for an unknown method on the facade', function (): void {
    Kubernetes::nothingHere();
})->throws(BadMethodCallException::class, 'KubernetesManager::nothingHere does not exist.');

it('forwards cluster macros from the facade', function (): void {
    Cluster::macro('describe', fn (): string => 'cluster '.$this->name());

    expect(Kubernetes::describe())->toBe('cluster default');
});

it('resolves an arbitrary resource on the default cluster', function (): void {
    expect(Kubernetes::resource(Pod::class))->toBeInstanceOf(Pod::class)
        ->and(Kubernetes::resource(Pod::class)->getCluster())->toBe(Kubernetes::cluster());
});

it('exposes a typed accessor per packaged resource on the default cluster', function (string $accessor, string $class): void {
    /** @var resource $resource */
    $resource = Kubernetes::{$accessor}();

    expect($resource)->toBeInstanceOf($class)
        ->and($resource->getCluster())->toBe(Kubernetes::cluster());
})->with([
    ['clusterRoles', Resources\ClusterRole::class],
    ['clusterRoleBindings', Resources\ClusterRoleBinding::class],
    ['configMaps', Resources\ConfigMap::class],
    ['cronJobs', Resources\CronJob::class],
    ['daemonSets', Resources\DaemonSet::class],
    ['deployments', Resources\Deployment::class],
    ['endpoints', Resources\Endpoints::class],
    ['events', Resources\Event::class],
    ['horizontalPodAutoscalers', Resources\HorizontalPodAutoscaler::class],
    ['ingresses', Resources\Ingress::class],
    ['jobs', Resources\Job::class],
    ['limitRanges', Resources\LimitRange::class],
    ['namespaces', Resources\KubernetesNamespace::class],
    ['networkPolicies', Resources\NetworkPolicy::class],
    ['nodes', Resources\Node::class],
    ['persistentVolumes', Resources\PersistentVolume::class],
    ['persistentVolumeClaims', Resources\PersistentVolumeClaim::class],
    ['pods', Pod::class],
    ['replicaSets', Resources\ReplicaSet::class],
    ['replicationControllers', Resources\ReplicationController::class],
    ['resourceQuotas', Resources\ResourceQuota::class],
    ['roles', Resources\Role::class],
    ['roleBindings', Resources\RoleBinding::class],
    ['secrets', Resources\Secret::class],
    ['serviceAccounts', Resources\ServiceAccount::class],
    ['services', Resources\Service::class],
    ['statefulSets', Resources\StatefulSet::class],
    ['storageClasses', Resources\StorageClass::class],
    ['traefikIngressRoutes', Resources\TraefikIngressRoute::class],
    ['traefikMiddlewares', Resources\TraefikMiddleware::class],
    ['traefikServersTransports', Resources\TraefikServersTransport::class],
    ['traefikTlsOptions', Resources\TraefikTlsOption::class],
    ['traefikTlsStores', Resources\TraefikTlsStore::class],
]);

it('scopes the default cluster to a namespace', function (): void {
    config()->set('kubernetes.clusters.default.url', 'https://k8s.example');
    Http::fake(['*' => Http::response(['items' => []])]);

    Kubernetes::namespace('prod')->pods()->whereLabel('app', 'web')->get();

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://k8s.example/api/v1/namespaces/prod/pods?')
        && str_contains($request->url(), 'labelSelector=app%3Dweb'));
});

it('pings and reads the version of the default cluster', function (): void {
    config()->set('kubernetes.clusters.default.url', 'https://k8s.example');
    Http::fake(['https://k8s.example/version' => Http::response([
        'major' => '1', 'minor' => '34', 'gitVersion' => 'v1.34.1', 'platform' => 'linux/arm64',
    ])]);

    $version = Kubernetes::version();

    expect(Kubernetes::ping())->toBeTrue()
        ->and($version)->toBeInstanceOf(VersionInfo::class)
        ->and($version->major)->toBe('1')
        ->and($version->minor)->toBe('34')
        ->and($version->gitVersion)->toBe('v1.34.1')
        ->and($version->platform)->toBe('linux/arm64');
});

it('reports an unconfigured or failing cluster as down', function (): void {
    expect(Kubernetes::ping())->toBeFalse();

    config()->set('kubernetes.clusters.flaky', ['url' => 'https://flaky.example']);
    Http::fake(['*' => Http::response(['message' => 'etcd down'], 503)]);

    expect(Kubernetes::cluster('flaky')->ping())->toBeFalse();
});

it('builds blank ad-hoc clusters that inherit nothing from the default', function (): void {
    config()->set('kubernetes.clusters.default', ['url' => 'https://default.example', 'token' => 'default-token']);

    $adHoc = Kubernetes::url('https://other.example');

    expect($adHoc->name())->toBeNull()
        ->and($adHoc->getUrl())->toBe('https://other.example')
        ->and($adHoc->getToken())->toBeNull()
        ->and(Kubernetes::cluster()->getToken())->toBe('default-token');
});

it('connects an ad-hoc cluster from a resolved connection', function (): void {
    $cluster = Kubernetes::connect(new KubeConfig(server: 'https://api.test:6443', token: 'tok', verify: false));

    expect($cluster->getUrl())->toBe('https://api.test:6443')
        ->and($cluster->getToken())->toBe('tok')
        ->and($cluster->shouldVerify())->toBeFalse()
        ->and($cluster->name())->toBeNull();
});

it('builds an ad-hoc cluster from a kubeconfig file', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'kc-');
    file_put_contents($path, <<<'YAML'
        apiVersion: v1
        current-context: ctx
        clusters:
          - name: c
            cluster:
              server: https://api:6443
        users:
          - name: u
            user:
              token: from-file
        contexts:
          - name: ctx
            context:
              cluster: c
              user: u
        YAML);

    $cluster = Kubernetes::fromKubeConfig($path);

    expect($cluster->getUrl())->toBe('https://api:6443')
        ->and($cluster->getToken())->toBe('from-file')
        ->and($cluster->shouldVerify())->toBeTrue();

    @unlink($path);
});

it('builds an ad-hoc cluster from in-cluster credentials when mounted', function (): void {
    $tokenPath = '/var/run/secrets/kubernetes.io/serviceaccount/token';

    putenv('KUBERNETES_SERVICE_HOST=10.1.2.3');
    putenv('KUBERNETES_SERVICE_PORT=443');

    try {
        $cluster = Kubernetes::inCluster();

        // Only reached when the test host actually has a mounted token.
        expect($cluster->getUrl())->toBe('https://10.1.2.3:443');
    } catch (KubeConfigException $e) {
        expect(is_file($tokenPath))->toBeFalse()
            ->and($e->getMessage())->toContain('token not found');
    } finally {
        putenv('KUBERNETES_SERVICE_HOST');
        putenv('KUBERNETES_SERVICE_PORT');
    }
});

it('serves the same API to an injected manager', function (): void {
    config()->set('kubernetes.clusters.default.url', 'https://k8s.example');
    Http::fake(['*' => Http::response(['gitVersion' => 'v1.34.0'])]);

    $manager = app(KubernetesManager::class);

    expect($manager)->toBe(Kubernetes::getFacadeRoot())
        ->and($manager->version()->gitVersion)->toBe('v1.34.0')
        ->and($manager->deployments()->getCluster())->toBe($manager->cluster());
});
