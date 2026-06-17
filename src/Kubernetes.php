<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi;

use BadMethodCallException;
use Closure;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Traits\HasAuthentication;
use RoundlyConsulting\KubernetesApi\Traits\HasManagerName;
use RoundlyConsulting\KubernetesApi\Traits\HasUrl;
use RoundlyConsulting\KubernetesApi\Traits\Makeable;

/**
 * @method \RoundlyConsulting\KubernetesApi\Resources\ConfigMap configMaps()
 * @method \RoundlyConsulting\KubernetesApi\Resources\Deployment deployments()
 * @method \RoundlyConsulting\KubernetesApi\Resources\Namespaces namespaces()
 * @method \RoundlyConsulting\KubernetesApi\Resources\Node nodes()
 * @method \RoundlyConsulting\KubernetesApi\Resources\PersistentVolume persistentVolumes()
 * @method \RoundlyConsulting\KubernetesApi\Resources\PersistentVolumeClaim persistentVolumeClaims()
 * @method \RoundlyConsulting\KubernetesApi\Resources\Pod pods()
 * @method \RoundlyConsulting\KubernetesApi\Resources\Secret secrets()
 * @method \RoundlyConsulting\KubernetesApi\Resources\Service services()
 * @method \RoundlyConsulting\KubernetesApi\Resources\StorageClass storageClasses()
 * @method \RoundlyConsulting\KubernetesApi\Resources\Job jobs()
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

    public function registerResource(string $name, string $resource): void
    {
        static::macro($name, function () use ($resource): Resource {
            /** @var resource $resource */
            $resource = new $resource;

            return $resource->setCluster($this);
        });
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
