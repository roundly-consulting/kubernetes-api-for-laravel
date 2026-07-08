<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/kubernetes-api-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=kubernetes-api-for-laravel">
    <img src="art/hero.png" alt="Kubernetes API for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

# Kubernetes API for Laravel

A fluent, Eloquent-style client for the Kubernetes API in Laravel. Talk to one or many
clusters with an expressive, chainable API built on top of Laravel's HTTP client, with
first-class support for the core Kubernetes resources, Traefik CRDs, and your own custom
resource definitions.

```php
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;

$deployments = Kubernetes::cluster('production')->deployments()->get();
```

## Requirements

- PHP 8.4 or higher
- Laravel 12 or 13

## Integrates with

This package builds on two sibling roundly-consulting packages, both hard requirements
(wired by path locally and VCS on CI until they land on Packagist):

- **[`roundly-consulting/enums-for-laravel`](https://github.com/roundly-consulting/enums-for-laravel)** —
  powers the richer `PatchType` enum. Beyond `PatchType::StrategicMerge->contentType()` you now get
  `PatchType::values()`, `PatchType::validationRule()`, `PatchType::options()`/`toOptions()`, clean
  `readable()`/`label()` names ("Strategic Merge", "Merge", "Json", "Apply"), and case lookups
  (`fromName()`, `fromLabel()`).
- **[`roundly-consulting/http-client-rate-limits-for-laravel`](https://github.com/roundly-consulting/http-client-rate-limits-for-laravel)** —
  paces every apiserver request per cluster, honours a `429 Retry-After`, and offers a fail-fast
  ceiling. See [Client-side rate limiting](#client-side-rate-limiting).

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/kubernetes-api-for-laravel
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="kubernetes-config"
```

Every resource listed in the published config is registered automatically by the package's
service provider, so custom resources you add there are available immediately.

## Configuration

The published `config/kubernetes.php` looks like this:

```php
use RoundlyConsulting\KubernetesApi\Resources;

return [
    'client' => [
        'options' => [
            'timeout' => 5,
        ],
    ],
    'rate_limits' => [
        'enabled' => env('KUBERNETES_RATELIMIT_ENABLED', true),
        'owner' => env('KUBERNETES_RATELIMIT_OWNER', 'app'),
        'max_attempts' => env('KUBERNETES_RATELIMIT', 400),
        'timespan' => env('KUBERNETES_RATELIMIT_TIMESPAN', 'minute'),
        'adaptive' => env('KUBERNETES_RATELIMIT_ADAPTIVE', true),
        'max_wait' => env('KUBERNETES_RATELIMIT_MAX_WAIT'),
        'jitter' => env('KUBERNETES_RATELIMIT_JITTER'),
    ],
    'traefik' => [
        'group' => 'traefik.io/v1alpha1',
    ],
    'resources' => [
        'clusterRoles' => Resources\ClusterRole::class,
        'clusterRoleBindings' => Resources\ClusterRoleBinding::class,
        'configMaps' => Resources\ConfigMap::class,
        'cronJobs' => Resources\CronJob::class,
        'daemonSets' => Resources\DaemonSet::class,
        'deployments' => Resources\Deployment::class,
        'endpoints' => Resources\Endpoints::class,
        'events' => Resources\Event::class,
        'horizontalPodAutoscalers' => Resources\HorizontalPodAutoscaler::class,
        'ingresses' => Resources\Ingress::class,
        'jobs' => Resources\Job::class,
        'limitRanges' => Resources\LimitRange::class,
        'namespaces' => Resources\Namespaces::class,
        'networkPolicies' => Resources\NetworkPolicy::class,
        'nodes' => Resources\Node::class,
        'persistentVolumes' => Resources\PersistentVolume::class,
        'persistentVolumeClaims' => Resources\PersistentVolumeClaim::class,
        'pods' => Resources\Pod::class,
        'replicaSets' => Resources\ReplicaSet::class,
        'replicationControllers' => Resources\ReplicationController::class,
        'resourceQuotas' => Resources\ResourceQuota::class,
        'roles' => Resources\Role::class,
        'roleBindings' => Resources\RoleBinding::class,
        'secrets' => Resources\Secret::class,
        'serviceAccounts' => Resources\ServiceAccount::class,
        'services' => Resources\Service::class,
        'statefulSets' => Resources\StatefulSet::class,
        'storageClasses' => Resources\StorageClass::class,
        'traefikIngressRoutes' => Resources\TraefikIngressRoute::class,
        'traefikMiddlewares' => Resources\TraefikMiddleware::class,
        'traefikServersTransports' => Resources\TraefikServersTransport::class,
        'traefikTlsOptions' => Resources\TraefikTlsOption::class,
        'traefikTlsStores' => Resources\TraefikTlsStore::class,
    ],
];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `client.options` | `array<string, mixed>` | `['timeout' => 5]` | Laravel HTTP client options merged into every request the package sends (timeout, proxy, etc.). |
| `rate_limits.enabled` | `bool` | `true` (`KUBERNETES_RATELIMIT_ENABLED`) | Toggle client-side rate limiting. `false` sends raw, unthrottled requests. |
| `rate_limits.owner` | `string` | `app` (`KUBERNETES_RATELIMIT_OWNER`) | Namespaces the budget key, so several apps/workers can share (or isolate) a cluster budget. |
| `rate_limits.max_attempts` | `int` | `400` (`KUBERNETES_RATELIMIT`) | Requests allowed per cluster per window before pacing kicks in. |
| `rate_limits.timespan` | `string` | `minute` (`KUBERNETES_RATELIMIT_TIMESPAN`) | Window length: `second`, `minute`, `hour`, or `day`. |
| `rate_limits.adaptive` | `bool` | `true` (`KUBERNETES_RATELIMIT_ADAPTIVE`) | Honour the apiserver's `Retry-After` header on a `429`, self-tuning the limiter. |
| `rate_limits.max_wait` | `int\|null` (ms) | `null` (`KUBERNETES_RATELIMIT_MAX_WAIT`) | `null` paces (waits). Set a ceiling in ms to fail fast with a `RateLimitExceededException` instead. |
| `rate_limits.jitter` | `int\|null` (ms) | `null` (`KUBERNETES_RATELIMIT_JITTER`) | Random spread added to each defer, smoothing thundering-herd bursts. |
| `traefik.group` | `string` | `traefik.io/v1alpha1` | The API group/version the bundled Traefik resources target. Set it to `traefik.containo.us/v1alpha1` for Traefik installations older than v3. |
| `resources` | `array<string, class-string>` | the core, workload, RBAC, policy + Traefik resources above | Maps an accessor name (e.g. `deployments`) to the resource class that backs it. Each entry becomes a method/magic method on a cluster (`$cluster->deployments()`). Add your own CRDs here to register them globally. |

The built-in accessors cover config maps, secrets, pods, deployments, replica sets, stateful
sets, daemon sets, replication controllers, jobs, cron jobs, services, endpoints, ingresses,
namespaces, nodes, events, persistent volumes and claims, storage classes, RBAC
(roles/role bindings/cluster roles/cluster role bindings/service accounts), network policies,
horizontal pod autoscalers, resource quotas, limit ranges, and the Traefik CRDs
(`IngressRoute`, `Middleware`, `TLSStore`, `ServersTransport`, `TLSOption`).

The package works with zero configuration — clusters are defined in code (see below) and the
default `resources` map ships built in.

## Usage

### Creating a cluster on the fly

```php
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;

$cluster = Kubernetes::url('https://api.my-cluster.example:6443')
    ->withToken('service-account-token')
    ->withCertificate('/path/to/client.crt')
    ->withPrivateKey('/path/to/client.key')
    ->withCaCertificate('/path/to/ca.crt')
    ->withSslVerification()
    ->setManagerName('MyApp'); // sets the field manager on resources this app manages
```

For local development you can skip the certificates and disable verification (not
recommended for production):

```php
$cluster = Kubernetes::url('https://127.0.0.1:6443')
    ->withToken('token')
    ->withoutSslVerification();
```

### From a kubeconfig or in-cluster

Point the client at a cluster in one line instead of hand-wiring the URL and credentials.
`fromKubeConfig()` parses the kubeconfig, resolves the named context's cluster and user, and
materialises any inline certificate data to temp files. `inCluster()` reads the
service-account credentials mounted into a pod.

```php
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;

// Current context from the default kubeconfig (or the KUBECONFIG env path):
$cluster = Kubernetes::fromKubeConfig();

// A specific context, optionally from an explicit kubeconfig path:
$cluster = Kubernetes::fromKubeConfig(context: 'orbstack');
$cluster = Kubernetes::fromKubeConfig(path: '/path/to/kubeconfig', context: 'staging');

// From in-cluster service-account mounts (when running inside a pod):
$cluster = Kubernetes::inCluster()->setManagerName('my-app');
```

### Registering named clusters

Register clusters once (for example in a service provider's `boot` method) and resolve them
anywhere by name:

```php
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Kubernetes as Cluster;

Kubernetes::registerCluster('production', function (Cluster $cluster) {
    return $cluster
        ->url('https://api.prod.example:6443')
        ->withToken('prod-token')
        ->withCaCertificate('/path/to/ca.crt');
});

// Resolve it later by name…
$cluster = Kubernetes::cluster('production');

// …or via the generated accessor…
$cluster = Kubernetes::getProductionCluster();

// …or through dependency injection on the underlying class.
public function index(\RoundlyConsulting\KubernetesApi\Kubernetes $kubernetes): void
{
    $cluster = $kubernetes->cluster('production');
}
```

### Registering custom resources (CRDs)

Define a resource class and register it either in `config/kubernetes.php` or at runtime:

```php
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Resource;

class Application extends Resource
{
    protected string $version = 'example.com/v2';

    protected string $kind = 'Application';

    protected bool $usesNamespaces = true;
}

Kubernetes::registerResource('apps', Application::class);

Kubernetes::getProductionCluster()->apps()->get(); // all Application resources
```

### Cluster operations

Operations use Laravel's HTTP client, so you can fake them with `Http::fake()` in your tests.

```php
$cluster = Kubernetes::cluster('production');

// List every deployment — returns a ResourcesCollection of Deployment resources.
$cluster->deployments()->get();

// Find a single deployment — returns a Deployment resource.
$cluster->deployments()->withName('checkout')->find();

// Check existence — returns a boolean.
$cluster->deployments()->withName('checkout')->existsOnCluster();

// Create a deployment.
$deployment = $cluster->deployments()
    ->setName('checkout')
    ->setReplicas(3)
    ->create();

$deployment->wasRecentlyCreated(); // true
$deployment->exists();             // true

// Update a deployment.
$cluster->deployments()
    ->withName('checkout')
    ->find()
    ->setReplicas(5)
    ->update();

// Update if it exists, otherwise create it.
$cluster->deployments()
    ->withName('checkout')
    ->setReplicas(2)
    ->updateOrCreate();

// Delete a deployment.
$deleted = $cluster->deployments()->withName('checkout')->delete();
$deleted->exists(); // false
```

`updateOrCreate()` carries the server's current `resourceVersion` into the update so a
concurrent change surfaces as a typed 409 conflict instead of being silently clobbered.

### Listing with selectors and pagination

Push filtering to the apiserver with label/field selectors, and paginate large lists instead
of fetching everything at once:

```php
// Label and field selectors build the labelSelector / fieldSelector query params.
$pods = $cluster->pods()
    ->whereLabel('app', 'checkout')
    ->whereLabelIn('tier', ['web', 'api'])
    ->whereLabelExists('team')
    ->whereField('status.phase', 'Running')
    ->limit(100)
    ->get();

// List across every namespace.
$all = $cluster->pods()->allNamespaces()->get();

// Page explicitly with a continue token.
$page = $cluster->pods()->limit(50)->getPage();
$page->items;               // ResourcesCollection
$page->continue;            // ?string — pass to ->continueFrom(...) for the next page
$page->remainingItemCount;  // ?int

// Or iterate every item lazily, auto-following continue tokens.
$cluster->pods()->each(function ($pod): void {
    // ...
});
```

### Patch, scale, rollout, and dry-run

```php
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubernetesPatch;

// Targeted patches (strategic-merge / merge / JSON Patch / server-side apply).
$cluster->deployments()->withName('checkout')
    ->patch(KubernetesPatch::merge(['spec' => ['paused' => true]]));

// Scale via the /scale subresource.
$cluster->deployments()->withName('checkout')->scale(5);

// Roll the pods by stamping the restartedAt annotation (like `kubectl rollout restart`).
$cluster->deployments()->withName('checkout')->rolloutRestart();

// Validate a write without persisting it by appending ?dryRun=All.
$cluster->deployments()->withName('checkout')->dryRun()->scale(5);
```

`scale()` is available on deployments, replica sets, stateful sets, and replication
controllers; `rolloutRestart()` on deployments, stateful sets, and daemon sets.

### Watching for changes

```php
use RoundlyConsulting\KubernetesApi\DataTransferObjects\WatchEvent;

$cluster->pods()->whereLabel('app', 'checkout')->watch(function (WatchEvent $event): void {
    $event->type;           // ADDED / MODIFIED / DELETED
    $event->object;         // the Pod resource
});
```

Long-lived watches suit queued or console contexts rather than web requests.

### Pod logs and exec

```php
use RoundlyConsulting\KubernetesApi\DataTransferObjects\PodLogOptions;

// Fetch logs as a string.
$logs = $cluster->pods()->withName('api')->logs(
    new PodLogOptions(container: 'app', tailLines: 200, timestamps: true),
);

// Stream logs line by line (tail).
foreach ($cluster->pods()->withName('api')->streamLogs(new PodLogOptions(follow: true)) as $line) {
    echo $line . PHP_EOL;
}

// Exec a command inside a pod (over a WebSocket; returns stdout, stderr, and exit code).
$result = $cluster->pods()->withName('api')->exec(['sh', '-c', 'echo hi']);
$result->stdout;     // "hi\n"
$result->exitCode;   // 0
$result->successful();
```

### Diagnostics command

Verify connectivity to a registered cluster:

```bash
php artisan kubernetes:ping production
# Connected to production — server v1.34.0
```

### Building resources with value objects

Resources compose from typed value objects such as `Container`, `Port`, `Probe`, `Volume`,
and `VolumeMount`:

```php
use RoundlyConsulting\KubernetesApi\Resources\Pod;
use RoundlyConsulting\KubernetesApi\Resources\Types\Container;
use RoundlyConsulting\KubernetesApi\Resources\Types\Probe;

$pod = Pod::make()
    ->setName('checkout')
    ->setContainers([
        Container::make()
            ->setName('app')
            ->setImage('registry.example/checkout', 'v1.4.0')
            ->addPort(8080)
            ->setMinimumMemory(256, 'Mi')
            ->setReadinessProbe(Probe::http('/healthz', 8080)),
    ]);
```

### TLS certificate secrets

`Secret` supports a typed `type` plus a TLS convenience that base64-encodes a PEM
certificate and key into a `kubernetes.io/tls` Secret:

```php
use RoundlyConsulting\KubernetesApi\Resources\Secret;

$secret = $cluster->secrets()
    ->setNamespace('default')
    ->setName('wildcard-tls')
    ->asTlsCertificate($pemCertificate, $pemPrivateKey)   // sets type + data['tls.crt'] / data['tls.key']
    ->create();

$secret->getType();              // "kubernetes.io/tls"
$secret->getData('tls.crt');     // the decoded PEM certificate

// Or set an arbitrary Secret type explicitly:
$cluster->secrets()->setName('dockercfg')->setType('kubernetes.io/dockerconfigjson');
```

### Select-all and empty selectors

An empty label selector means "select all" in Kubernetes and must be sent as the empty
object `{}`, not `[]` (which the apiserver rejects). The selector setters handle this for
you — passing an empty array serialises correctly:

```php
use RoundlyConsulting\KubernetesApi\Resources\NetworkPolicy;

// Default-deny / select-all: an empty podSelector matches every pod in the namespace.
$cluster->networkPolicies()
    ->setNamespace('default')
    ->setName('default-deny')
    ->setPodSelector([])           // serialises to "podSelector": {}
    ->setPolicyTypes(['Ingress'])
    ->create();
```

The same applies to `Service::setSelectors([])` and the workload pod selectors
(`Deployment`, `ReplicaSet`, `StatefulSet`, `DaemonSet`, `ReplicationController`).

### Traefik TLS and transport CRDs

Beyond `IngressRoute` and `Middleware`, the package ships first-class resources for
Traefik's TLS and transport CRDs. Their REST plurals (`tlsstores`, `serverstransports`,
`tlsoptions`) are pinned to match the real CRDs, and they honour the configurable
`kubernetes.traefik.group`:

```php
// Point a default certificate at a kubernetes.io/tls Secret.
$cluster->traefikTlsStores()
    ->setNamespace('default')
    ->setName('default')
    ->setDefaultCertificate('wildcard-tls')
    ->create();

// Configure how Traefik dials a backend.
$cluster->traefikServersTransports()
    ->setNamespace('default')
    ->setName('backend')
    ->setServerName('backend.internal')
    ->insecureSkipVerify()
    ->setRootCAsSecrets(['backend-ca'])
    ->create();

// Constrain TLS versions and cipher suites.
$cluster->traefikTlsOptions()
    ->setNamespace('default')
    ->setName('modern')
    ->setMinVersion('VersionTLS12')
    ->setCipherSuites(['TLS_AES_256_GCM_SHA384'])
    ->create();
```

### Attributes, labels, and annotations

Every resource exposes Eloquent-style helpers for attributes, labels, and annotations:

```php
$service = $cluster->services()->withName('checkout')->find();

$service->getLabels();                       // array<string, string>
$service->setLabel('tier', 'frontend');
$service->getAnnotation('prometheus.io/scrape', 'false');
$service->getClusterDns();                   // checkout.default.svc.cluster.local
```

### Macros and conditionals

Both the `Kubernetes` client and every resource use Laravel's `Macroable` and
`Conditionable` traits, so you can extend and branch fluently:

```php
use RoundlyConsulting\KubernetesApi\Resources\Deployment;

Deployment::macro('findCheckout', fn (): Deployment => Deployment::make()->withName('checkout')->find());

Deployment::make()
    ->when($scaleUp, fn (Deployment $deployment) => $deployment->setReplicas(5))
    ->update();
```

### Error handling

Failed requests throw a `RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException`,
which carries the underlying `Illuminate\Http\Client\Response` so you can inspect the
Kubernetes API error:

```php
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;

try {
    $cluster->deployments()->withName('missing')->find();
} catch (KubernetesException $e) {
    $e->response->status();          // e.g. 404
    $e->response->json('message');   // the Kubernetes error message
}
```

### Client-side rate limiting

Every apiserver request is paced through
[`roundly-consulting/http-client-rate-limits-for-laravel`](https://github.com/roundly-consulting/http-client-rate-limits-for-laravel),
keyed **per cluster** (by manager name, falling back to the request host) so one busy cluster never
starves another. Kubernetes API Priority & Fairness is per-apiserver, so a per-cluster budget maps
directly onto how the server enforces its own limits.

By default the client makes up to **400 requests per minute** per cluster and **paces** anything
beyond that (it waits for the window to free up rather than erroring). With `adaptive` on, a `429`
from the apiserver is read for its `Retry-After` value and self-tunes the limiter — so the next
request already backs off by exactly what the server asked for.

Tune it entirely from config/env (see the [Configuration](#configuration) table):

```dotenv
KUBERNETES_RATELIMIT=400            # attempts per window, per cluster
KUBERNETES_RATELIMIT_TIMESPAN=minute
KUBERNETES_RATELIMIT_ADAPTIVE=true  # honour 429 Retry-After
```

**Fail fast instead of waiting.** Set a `max_wait` ceiling (milliseconds). When a request would
have to wait longer than that, it throws
`RoundlyConsulting\KubernetesApi\Exceptions\RateLimitExceededException` (which exposes `->cluster`
and `->availableInSeconds`) instead of blocking:

```php
use RoundlyConsulting\KubernetesApi\Exceptions\RateLimitExceededException;

try {
    $cluster->pods()->get();
} catch (RateLimitExceededException $e) {
    report("Cluster {$e->cluster} is throttled; retry in {$e->availableInSeconds}s");
}
```

**Disable it** entirely with `KUBERNETES_RATELIMIT_ENABLED=false` for the raw, unthrottled client.

**Shared budgets across workers.** The limiter defaults to an in-memory store (per process — ideal
for a single worker or CLI run). For a budget shared across queue workers or servers, point the
underlying package's store at Cache, Redis, or the database via its own
`HTTP_CLIENT_RATE_LIMITS_STORE` setting; this package does not force a store.

## Testing

The default suite is fully faked with `Http::fake()` and needs no cluster:

```bash
composer test
```

### Live integration tests (optional)

A separate, opt-in suite runs the real CRUD / exec / listing matrix against a local
**OrbStack** cluster. It is excluded from `composer test` and guarded so it can only ever run
against OrbStack — never a production context.

```bash
K8S_INTEGRATION=1 composer test-integration
```

| Variable | Default | Purpose |
|---|---|---|
| `K8S_INTEGRATION` | _(unset)_ | Set to `1` to enable the suite; absent → every integration test skips. |
| `K8S_INTEGRATION_CONTEXT` | `orbstack` | The kube context to extract credentials from. Must be `orbstack`. |
| `K8S_INTEGRATION_NAMESPACE` | random `k8s-it-…` | Override the throwaway namespace the suite creates and cleans up. |

The guard hard-refuses to run unless `K8S_INTEGRATION=1`, the active context is exactly
`orbstack`, and the apiserver host is loopback or `*.orb.local`. Each run is isolated to a
unique throwaway namespace that is deleted afterwards. See `tests/Integration/README.md`.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
