<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi;

use BadMethodCallException;
use Closure;
use Illuminate\Contracts\Container\Container;
use RoundlyConsulting\KubernetesApi\Concerns\ResolvesResources;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\VersionInfo;
use RoundlyConsulting\KubernetesApi\Exceptions\ClusterConfigurationException;
use RoundlyConsulting\KubernetesApi\Exceptions\ClusterNotFoundException;
use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;
use RoundlyConsulting\KubernetesApi\Exceptions\KubeConfigException;
use RoundlyConsulting\KubernetesApi\Exceptions\NamespaceScopeException;
use RoundlyConsulting\KubernetesApi\Http\HttpTransport;
use RoundlyConsulting\KubernetesApi\Http\Transport;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Support\InClusterConfigLoader;
use RoundlyConsulting\KubernetesApi\Support\KubeConfigLoader;
use RoundlyConsulting\KubernetesApi\Support\ResourceRegistry;

/**
 * The root of the `Kubernetes` facade: owns the named clusters (from
 * `config('kubernetes.clusters')` and {@see registerCluster()}), builds ad-hoc ones,
 * and proxies the default cluster — `Kubernetes::pods()` is
 * `Kubernetes::cluster()->pods()`.
 *
 * It never holds connection state of its own. Every client it hands out is an immutable
 * {@see Cluster}, so nothing one caller configures can leak into another caller's
 * requests. Inject it (`KubernetesManager $kubernetes`) for the same API without the
 * facade.
 */
class KubernetesManager
{
    use ResolvesResources;

    /**
     * Stored as returning `mixed`: the contract is `Closure(Cluster): Cluster`, but a
     * host closure is only checked when it runs.
     *
     * @var array<string, Closure(Cluster): mixed>
     */
    protected array $definitions = [];

    /** @var array<string, Cluster> */
    protected array $resolved = [];

    protected Transport $transport;

    public function __construct(protected Container $container)
    {
        $this->transport = $container->make(HttpTransport::class);
    }

    /**
     * A named cluster, or the default one (`config('kubernetes.default')`). Resolved
     * once and reused — clusters are immutable, so sharing one is safe.
     *
     * @throws ClusterNotFoundException
     * @throws ClusterConfigurationException
     */
    public function cluster(?string $name = null): Cluster
    {
        $name ??= $this->defaultClusterName();

        return $this->resolved[$name] ??= $this->resolveCluster($name);
    }

    /**
     * Whether a cluster of that name is configured or registered.
     */
    public function hasCluster(string $name): bool
    {
        return isset($this->definitions[$name]) || array_key_exists($name, $this->configuredClusters());
    }

    /**
     * The names of every configured and registered cluster.
     *
     * @return list<string>
     */
    public function clusters(): array
    {
        return array_values(array_unique([
            ...array_map(strval(...), array_keys($this->configuredClusters())),
            ...array_map(strval(...), array_keys($this->definitions)),
        ]));
    }

    /**
     * Define a cluster in code. The closure receives a blank client and must return the
     * configured one (clients are immutable). It runs lazily, the first time the
     * cluster is resolved, and overrides a configured cluster of the same name.
     *
     * @param  Closure(Cluster): Cluster  $definition
     */
    public function registerCluster(string $name, Closure $definition): void
    {
        $this->definitions[$name] = $definition;

        unset($this->resolved[$name]);
    }

    /**
     * Register a resource class under an accessor name for every cluster
     * (`Kubernetes::registerResource('apps', Application::class)` → `$cluster->apps()`).
     * Re-registering a packaged name (`pods`) swaps the class that name resolves to.
     *
     * @param  class-string<resource>  $resource
     *
     * @throws InvalidResourceException
     */
    public function registerResource(string $name, string $resource): void
    {
        ResourceRegistry::register($name, $resource);
    }

    /**
     * A fresh instance of any resource class, bound to the default cluster.
     *
     * @template TResource of Resource
     *
     * @param  class-string<TResource>  $resource
     * @return TResource
     */
    public function resource(string $resource): Resource
    {
        return $this->cluster()->resource($resource);
    }

    /**
     * The default cluster, scoped to one namespace.
     *
     * @throws NamespaceScopeException
     */
    public function namespace(string $namespace): Cluster
    {
        return $this->cluster()->namespace($namespace);
    }

    /**
     * Whether the default cluster's apiserver answers.
     */
    public function ping(): bool
    {
        return $this->cluster()->ping();
    }

    /**
     * The default cluster's apiserver build information.
     */
    public function version(): VersionInfo
    {
        return $this->cluster()->version();
    }

    /**
     * A new, blank, ad-hoc cluster pointed at a URL. It inherits nothing from the
     * default cluster — no token, no certificate.
     */
    public function url(string $url): Cluster
    {
        return $this->newCluster()->url($url);
    }

    /**
     * A new ad-hoc cluster from a resolved connection.
     */
    public function connect(KubeConfig $config): Cluster
    {
        return $this->newCluster()->applyConfig($config);
    }

    /**
     * A new ad-hoc cluster from a kubeconfig context — the file's `current-context`
     * and the standard path / `KUBECONFIG` env by default.
     *
     * @throws KubeConfigException
     */
    public function fromKubeConfig(?string $path = null, ?string $context = null): Cluster
    {
        return $this->connect($this->loadKubeConfig($path, $context));
    }

    /**
     * A new ad-hoc cluster from the service-account credentials mounted into a pod.
     *
     * @throws KubeConfigException
     */
    public function inCluster(): Cluster
    {
        return $this->connect($this->loadInClusterConfig());
    }

    /**
     * Resources registered by name (`Kubernetes::apps()`) and `Cluster` macros, on the
     * default cluster.
     *
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (ResourceRegistry::classFor($method) !== null || Cluster::hasMacro($method)) {
            return $this->cluster()->{$method}(...$parameters);
        }

        throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $method));
    }

    protected function newCluster(?string $name = null): Cluster
    {
        return new Cluster($this->transport, $name);
    }

    protected function loadKubeConfig(?string $path, ?string $context): KubeConfig
    {
        return $this->container->make(KubeConfigLoader::class)->load($path, $context);
    }

    protected function loadInClusterConfig(): KubeConfig
    {
        return $this->container->make(InClusterConfigLoader::class)->load();
    }

    /**
     * @param  class-string<TResource>  $default
     * @return TResource
     *
     * @template TResource of Resource
     */
    protected function configuredResource(string $name, string $default): Resource
    {
        /** @var class-string<TResource> $class */
        $class = ResourceRegistry::classFor($name) ?? $default;

        return $this->cluster()->resource($class);
    }

    protected function defaultClusterName(): string
    {
        $name = config('kubernetes.default');

        return is_string($name) && $name !== '' ? $name : 'default';
    }

    private function resolveCluster(string $name): Cluster
    {
        if (isset($this->definitions[$name])) {
            $cluster = ($this->definitions[$name])($this->newCluster($name));

            if (! $cluster instanceof Cluster) {
                throw ClusterConfigurationException::invalidDefinition($name, $cluster);
            }

            return $cluster;
        }

        $configured = $this->configuredClusters();

        if (! array_key_exists($name, $configured)) {
            throw ClusterNotFoundException::named($name);
        }

        return $this->clusterFromConfig($name, (array) $configured[$name]);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function clusterFromConfig(string $name, array $definition): Cluster
    {
        $source = is_string($definition['source'] ?? null) ? $definition['source'] : 'url';
        $string = static fn (mixed $value): ?string => is_string($value) && $value !== '' ? $value : null;

        $connection = match ($source) {
            'url' => new KubeConfig(
                server: $string($definition['url'] ?? null) ?? '',
                token: $string($definition['token'] ?? null),
                clientCertificatePath: $string($definition['certificate'] ?? null),
                clientKeyPath: $string($definition['private_key'] ?? null),
                certificateAuthorityPath: $string($definition['ca_certificate'] ?? null),
                verify: filter_var($definition['verify'] ?? true, FILTER_VALIDATE_BOOL),
            ),
            'kubeconfig' => $this->loadKubeConfig(
                $string($definition['kubeconfig'] ?? null),
                $string($definition['context'] ?? null),
            ),
            'in-cluster' => $this->loadInClusterConfig(),
            default => throw ClusterConfigurationException::unknownSource($name, $source),
        };

        return $this->newCluster($name)
            ->applyConfig($connection)
            ->withManagerName($string($definition['manager'] ?? null))
            ->withDefaultNamespace($string($definition['namespace'] ?? null) ?? 'default');
    }

    /** @return array<array-key, mixed> */
    private function configuredClusters(): array
    {
        return (array) config('kubernetes.clusters', []);
    }
}
