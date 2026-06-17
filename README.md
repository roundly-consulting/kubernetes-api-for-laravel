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
    'resources' => [
        'configMaps' => Resources\ConfigMap::class,
        'deployments' => Resources\Deployment::class,
        'jobs' => Resources\Job::class,
        'namespaces' => Resources\Namespaces::class,
        'nodes' => Resources\Node::class,
        'persistentVolumes' => Resources\PersistentVolume::class,
        'persistentVolumeClaims' => Resources\PersistentVolumeClaim::class,
        'pods' => Resources\Pod::class,
        'secrets' => Resources\Secret::class,
        'services' => Resources\Service::class,
        'storageClasses' => Resources\StorageClass::class,
        'traefikIngressRoutes' => Resources\TraefikIngressRoute::class,
        'traefikMiddlewares' => Resources\TraefikMiddleware::class,
    ],
];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `client.options` | `array<string, mixed>` | `['timeout' => 5]` | Guzzle/HTTP client options merged into every request the package sends (timeout, proxy, etc.). |
| `resources` | `array<string, class-string>` | the core + Traefik resources above | Maps an accessor name (e.g. `deployments`) to the resource class that backs it. Each entry becomes a magic method on a cluster (`$cluster->deployments()`). Add your own CRDs here to register them globally. |

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

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
