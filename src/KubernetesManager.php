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
use RoundlyConsulting\KubernetesApi\Support\ConfigValue;
use RoundlyConsulting\KubernetesApi\Support\InClusterConfigLoader;
use RoundlyConsulting\KubernetesApi\Support\KubeConfigLoader;
use RoundlyConsulting\KubernetesApi\Support\ResourceRegistry;
use RoundlyConsulting\PackageToolkit\Support\Config;

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
     * and the standard path / `KUBECONFIG` env by default. The context's namespace, if
     * it names one, is the cluster's default namespace.
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

    /**
     * `kubernetes.default`, or `default` when not set — absent, null or blank (a
     * host's `KUBERNETES_CLUSTER=`). A non-string value throws instead of quietly
     * talking to the cluster named `default`.
     */
    protected function defaultClusterName(): string
    {
        $name = config('kubernetes.default');

        return ConfigValue::isSet($name) ? self::requiredString('kubernetes.default', $name) : 'default';
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

        $definition = $configured[$name];

        if (! is_array($definition)) {
            throw ClusterConfigurationException::invalidSetting("kubernetes.clusters.{$name}", 'an array', $definition);
        }

        /** @var array<string, mixed> $definition */
        return $this->clusterFromConfig($name, $definition);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function clusterFromConfig(string $name, array $definition): Cluster
    {
        $source = ConfigValue::isSet($definition['source'] ?? null) ? $definition['source'] : 'url';

        // A setting that is not set — absent, null or blank (a host's `KUBERNETES_TOKEN=`)
        // — takes its default; a set one must be a string. A wrong-typed value throws,
        // naming the key, instead of being quietly dropped.
        $string = static fn (string $leaf, mixed $value): ?string => ConfigValue::isSet($value)
            ? self::requiredString("kubernetes.clusters.{$name}.{$leaf}", $value)
            : null;

        $connection = match ($source) {
            'url' => new KubeConfig(
                server: $string('url', $definition['url'] ?? null) ?? '',
                token: $string('token', $definition['token'] ?? null),
                clientCertificatePath: $string('certificate', $definition['certificate'] ?? null),
                clientKeyPath: $string('private_key', $definition['private_key'] ?? null),
                certificateAuthorityPath: $string('ca_certificate', $definition['ca_certificate'] ?? null),
                verify: self::flag($name, 'verify', $definition['verify'] ?? null, true),
            ),
            'kubeconfig' => $this->loadKubeConfig(
                $string('kubeconfig', $definition['kubeconfig'] ?? null),
                $string('context', $definition['context'] ?? null),
            ),
            'in-cluster' => $this->loadInClusterConfig(),
            default => throw ClusterConfigurationException::unknownSource($name, match (true) {
                is_string($source) => $source,
                is_scalar($source) => var_export($source, true),
                default => get_debug_type($source),
            }),
        };

        // The cluster's own `namespace` wins, then a kubeconfig context's, then `default`.
        $cluster = $this->newCluster($name)
            ->applyConfig($connection)
            ->withManagerName($string('manager', $definition['manager'] ?? null))
            ->withDefaultNamespace($string('namespace', $definition['namespace'] ?? null) ?? $connection->namespace ?? 'default');

        // Transport and error policy, applied whatever the source.
        if (self::flag($name, 'follow_redirects', $definition['follow_redirects'] ?? null, false)) {
            $cluster = $cluster->withRedirects();
        }

        if (self::flag($name, 'redact_errors', $definition['redact_errors'] ?? null, false)) {
            $cluster = $cluster->withRedactedErrors();
        }

        return $cluster;
    }

    /**
     * A present string setting: a non-empty string, or a ClusterConfigurationException
     * naming the key.
     */
    private static function requiredString(string $key, mixed $value): string
    {
        return Config::for([$key => $value], ClusterConfigurationException::class)->requireString($key);
    }

    /**
     * A cluster switch (`verify`, `follow_redirects`, `redact_errors`), read strictly; not set (absent,
     * null or blank) takes the default. `filter_var()` once read a typo'd
     * `KUBERNETES_VERIFY_SSL` as false and turned TLS verification off without a
     * word; anything but a boolean spelling now throws, naming the cluster's key.
     */
    private static function flag(string $cluster, string $leaf, mixed $value, bool $default): bool
    {
        $key = "kubernetes.clusters.{$cluster}.{$leaf}";

        return Config::for([$key => $value], ClusterConfigurationException::class)->boolean($key, $default);
    }

    /**
     * The `kubernetes.clusters` map (absent = none); anything but an array throws.
     *
     * @return array<array-key, mixed>
     */
    private function configuredClusters(): array
    {
        $clusters = config('kubernetes.clusters');

        if ($clusters !== null && ! is_array($clusters)) {
            throw ClusterConfigurationException::invalidSetting('kubernetes.clusters', 'an array', $clusters);
        }

        return $clusters ?? [];
    }
}
