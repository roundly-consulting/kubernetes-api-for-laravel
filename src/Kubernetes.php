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
        return $this->resource(Resources\ConfigMap::class);
    }

    public function cronJobs(): Resources\CronJob
    {
        return $this->resource(Resources\CronJob::class);
    }

    public function daemonSets(): Resources\DaemonSet
    {
        return $this->resource(Resources\DaemonSet::class);
    }

    public function deployments(): Resources\Deployment
    {
        return $this->resource(Resources\Deployment::class);
    }

    public function ingresses(): Resources\Ingress
    {
        return $this->resource(Resources\Ingress::class);
    }

    public function jobs(): Resources\Job
    {
        return $this->resource(Resources\Job::class);
    }

    public function namespaces(): Resources\Namespaces
    {
        return $this->resource(Resources\Namespaces::class);
    }

    public function nodes(): Resources\Node
    {
        return $this->resource(Resources\Node::class);
    }

    public function persistentVolumes(): Resources\PersistentVolume
    {
        return $this->resource(Resources\PersistentVolume::class);
    }

    public function persistentVolumeClaims(): Resources\PersistentVolumeClaim
    {
        return $this->resource(Resources\PersistentVolumeClaim::class);
    }

    public function pods(): Resources\Pod
    {
        return $this->resource(Resources\Pod::class);
    }

    public function replicaSets(): Resources\ReplicaSet
    {
        return $this->resource(Resources\ReplicaSet::class);
    }

    public function secrets(): Resources\Secret
    {
        return $this->resource(Resources\Secret::class);
    }

    public function services(): Resources\Service
    {
        return $this->resource(Resources\Service::class);
    }

    public function statefulSets(): Resources\StatefulSet
    {
        return $this->resource(Resources\StatefulSet::class);
    }

    public function storageClasses(): Resources\StorageClass
    {
        return $this->resource(Resources\StorageClass::class);
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
