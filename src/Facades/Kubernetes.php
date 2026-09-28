<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\VersionInfo;
use RoundlyConsulting\KubernetesApi\KubernetesManager;
use RoundlyConsulting\KubernetesApi\Resources;
use RoundlyConsulting\KubernetesApi\Resources\Resource;

/**
 * @method static Cluster cluster(?string $name = null)
 * @method static bool hasCluster(string $name)
 * @method static list<string> clusters()
 * @method static void registerCluster(string $name, \Closure(Cluster): Cluster $definition)
 * @method static void registerResource(string $name, class-string<Resource> $resource)
 * @method static Resource resource(class-string<Resource> $resource)
 * @method static Cluster namespace(string $namespace)
 * @method static bool ping()
 * @method static VersionInfo version()
 * @method static Cluster url(string $url)
 * @method static Cluster connect(KubeConfig $config)
 * @method static Cluster fromKubeConfig(?string $path = null, ?string $context = null)
 * @method static Cluster inCluster()
 * @method static Resources\ClusterRole clusterRoles()
 * @method static Resources\ClusterRoleBinding clusterRoleBindings()
 * @method static Resources\ConfigMap configMaps()
 * @method static Resources\CronJob cronJobs()
 * @method static Resources\DaemonSet daemonSets()
 * @method static Resources\Deployment deployments()
 * @method static Resources\Endpoints endpoints()
 * @method static Resources\Event events()
 * @method static Resources\HorizontalPodAutoscaler horizontalPodAutoscalers()
 * @method static Resources\Ingress ingresses()
 * @method static Resources\Job jobs()
 * @method static Resources\LimitRange limitRanges()
 * @method static Resources\KubernetesNamespace namespaces()
 * @method static Resources\NetworkPolicy networkPolicies()
 * @method static Resources\Node nodes()
 * @method static Resources\PersistentVolume persistentVolumes()
 * @method static Resources\PersistentVolumeClaim persistentVolumeClaims()
 * @method static Resources\Pod pods()
 * @method static Resources\ReplicaSet replicaSets()
 * @method static Resources\ReplicationController replicationControllers()
 * @method static Resources\ResourceQuota resourceQuotas()
 * @method static Resources\Role roles()
 * @method static Resources\RoleBinding roleBindings()
 * @method static Resources\Secret secrets()
 * @method static Resources\ServiceAccount serviceAccounts()
 * @method static Resources\Service services()
 * @method static Resources\StatefulSet statefulSets()
 * @method static Resources\StorageClass storageClasses()
 * @method static Resources\TraefikIngressRoute traefikIngressRoutes()
 * @method static Resources\TraefikMiddleware traefikMiddlewares()
 * @method static Resources\TraefikServersTransport traefikServersTransports()
 * @method static Resources\TraefikTlsOption traefikTlsOptions()
 * @method static Resources\TraefikTlsStore traefikTlsStores()
 *
 * @see KubernetesManager
 */
final class Kubernetes extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return KubernetesManager::class;
    }
}
