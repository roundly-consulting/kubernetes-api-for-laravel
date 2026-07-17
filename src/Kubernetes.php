<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi;

use BadMethodCallException;
use Closure;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Support\InClusterConfigLoader;
use RoundlyConsulting\KubernetesApi\Support\KubeConfigLoader;
use RoundlyConsulting\KubernetesApi\Traits\HasAuthentication;
use RoundlyConsulting\KubernetesApi\Traits\HasManagerName;
use RoundlyConsulting\KubernetesApi\Traits\HasUrl;
use RoundlyConsulting\KubernetesApi\Traits\Makeable;

/**
 * @method \RoundlyConsulting\KubernetesApi\Resources\ClusterRole clusterRoles()
 * @method \RoundlyConsulting\KubernetesApi\Resources\ClusterRoleBinding clusterRoleBindings()
 * @method \RoundlyConsulting\KubernetesApi\Resources\Endpoints endpoints()
 * @method \RoundlyConsulting\KubernetesApi\Resources\Event events()
 * @method \RoundlyConsulting\KubernetesApi\Resources\HorizontalPodAutoscaler horizontalPodAutoscalers()
 * @method \RoundlyConsulting\KubernetesApi\Resources\LimitRange limitRanges()
 * @method \RoundlyConsulting\KubernetesApi\Resources\NetworkPolicy networkPolicies()
 * @method \RoundlyConsulting\KubernetesApi\Resources\ReplicationController replicationControllers()
 * @method \RoundlyConsulting\KubernetesApi\Resources\ResourceQuota resourceQuotas()
 * @method \RoundlyConsulting\KubernetesApi\Resources\Role roles()
 * @method \RoundlyConsulting\KubernetesApi\Resources\RoleBinding roleBindings()
 * @method \RoundlyConsulting\KubernetesApi\Resources\ServiceAccount serviceAccounts()
 * @method \RoundlyConsulting\KubernetesApi\Resources\TraefikIngressRoute traefikIngressRoutes()
 * @method \RoundlyConsulting\KubernetesApi\Resources\TraefikMiddleware traefikMiddlewares()
 * @method \RoundlyConsulting\KubernetesApi\Resources\TraefikServersTransport traefikServersTransports()
 * @method \RoundlyConsulting\KubernetesApi\Resources\TraefikTlsOption traefikTlsOptions()
 * @method \RoundlyConsulting\KubernetesApi\Resources\TraefikTlsStore traefikTlsStores()
 *
 * @phpstan-consistent-constructor
 */
class Kubernetes
{
    use Conditionable;
    use HasAuthentication;
    use HasManagerName;
    use HasUrl;
    use Macroable;
    use Makeable;

    public function cluster(string $name): static
    {
        $macroName = $this->getClusterMacroName($name);

        if (static::hasMacro($macroName)) {
            return static::$macroName();
        }

        throw new BadMethodCallException("No cluster '{$name}' definition found.");
    }

    public function registerCluster(string $name, Closure $configure): void
    {
        static::macro($this->getClusterMacroName($name), function () use ($configure): Kubernetes {
            $cluster = new static;

            $configure($cluster);

            return $cluster;
        });
    }

    /** @param class-string<resource> $resource */
    public function registerResource(string $name, string $resource): void
    {
        static::macro($name, function () use ($resource): Resource {
            $instance = new $resource;

            return $instance->setCluster($this);
        });
    }

    /**
     * Resolve a fresh, cluster-bound instance of the given resource class.
     *
     * @template TResource of Resource
     *
     * @param  class-string<TResource>  $resource
     * @return TResource
     */
    public function resource(string $resource): Resource
    {
        return (new $resource)->setCluster($this);
    }

    /**
     * Build a client from a kubeconfig context. Defaults to the file's
     * current-context and the standard kubeconfig path / `KUBECONFIG` env.
     */
    public static function fromKubeConfig(?string $path = null, ?string $context = null): static
    {
        return static::make()->applyConfig(
            (new KubeConfigLoader)->load($path, $context),
        );
    }

    /**
     * Build a client from the in-cluster service-account credentials mounted
     * into a pod.
     */
    public static function inCluster(): static
    {
        return static::make()->applyConfig(
            (new InClusterConfigLoader)->load(),
        );
    }

    public function applyConfig(KubeConfig $config): static
    {
        $this->url($config->server);

        if ($config->token !== null) {
            $this->withToken($config->token);
        }

        if ($config->clientCertificatePath !== null) {
            $this->withCertificate($config->clientCertificatePath);
        }

        if ($config->clientKeyPath !== null) {
            $this->withPrivateKey($config->clientKeyPath);
        }

        if ($config->certificateAuthorityPath !== null) {
            $this->withCaCertificate($config->certificateAuthorityPath);
        }

        $config->verify ? $this->withSslVerification() : $this->withoutSslVerification();

        return $this;
    }

    public function configMaps(): Resources\ConfigMap
    {
        return $this->configuredResource('configMaps', Resources\ConfigMap::class);
    }

    public function cronJobs(): Resources\CronJob
    {
        return $this->configuredResource('cronJobs', Resources\CronJob::class);
    }

    public function daemonSets(): Resources\DaemonSet
    {
        return $this->configuredResource('daemonSets', Resources\DaemonSet::class);
    }

    public function deployments(): Resources\Deployment
    {
        return $this->configuredResource('deployments', Resources\Deployment::class);
    }

    public function ingresses(): Resources\Ingress
    {
        return $this->configuredResource('ingresses', Resources\Ingress::class);
    }

    public function jobs(): Resources\Job
    {
        return $this->configuredResource('jobs', Resources\Job::class);
    }

    public function namespaces(): Resources\Namespaces
    {
        return $this->configuredResource('namespaces', Resources\Namespaces::class);
    }

    public function nodes(): Resources\Node
    {
        return $this->configuredResource('nodes', Resources\Node::class);
    }

    public function persistentVolumes(): Resources\PersistentVolume
    {
        return $this->configuredResource('persistentVolumes', Resources\PersistentVolume::class);
    }

    public function persistentVolumeClaims(): Resources\PersistentVolumeClaim
    {
        return $this->configuredResource('persistentVolumeClaims', Resources\PersistentVolumeClaim::class);
    }

    public function pods(): Resources\Pod
    {
        return $this->configuredResource('pods', Resources\Pod::class);
    }

    public function replicaSets(): Resources\ReplicaSet
    {
        return $this->configuredResource('replicaSets', Resources\ReplicaSet::class);
    }

    public function secrets(): Resources\Secret
    {
        return $this->configuredResource('secrets', Resources\Secret::class);
    }

    public function services(): Resources\Service
    {
        return $this->configuredResource('services', Resources\Service::class);
    }

    public function statefulSets(): Resources\StatefulSet
    {
        return $this->configuredResource('statefulSets', Resources\StatefulSet::class);
    }

    public function storageClasses(): Resources\StorageClass
    {
        return $this->configuredResource('storageClasses', Resources\StorageClass::class);
    }

    /**
     * Resolve a resource accessor through the `kubernetes.resources` seam.
     *
     * These accessors used to hard-code the packaged class — `$this->resource(
     * Resources\Pod::class)` — while the provider separately registered a macro per
     * configured name. A real method always wins over a macro (`Macroable::__call` only
     * fires when the method does not exist), so for all sixteen of them the documented
     * `kubernetes.resources` key was dead: a host pointing `pods` at its own subclass
     * still got the packaged Pod.
     *
     * The map is read wholesale and indexed, rather than through a
     * `config("kubernetes.resources.{$name}")` interpolation, so the read stays a literal
     * the config contract can see.
     *
     * @param  class-string<TResource>  $default
     * @return TResource
     *
     * @template TResource of Resource
     */
    private function configuredResource(string $name, string $default): Resource
    {
        /** @var array<string, class-string<resource>> $resources */
        $resources = config('kubernetes.resources', []);

        /** @var class-string<TResource> $class */
        $class = $resources[$name] ?? $default;

        return $this->resource($class);
    }

    protected function getClusterMacroName(string $name): string
    {
        return str($name)
            ->camel()
            ->ucfirst()
            ->prepend('get')
            ->append('Cluster')
            ->toString();
    }
}
