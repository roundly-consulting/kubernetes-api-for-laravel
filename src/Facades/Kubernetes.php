<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Facades;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\ExecResult;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\VersionInfo;
use RoundlyConsulting\KubernetesApi\KubernetesManager;
use RoundlyConsulting\KubernetesApi\Resources;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Testing\KubernetesFake;
use RoundlyConsulting\KubernetesApi\Testing\RecordedRequest;

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
 * @method static KubernetesFake seed(string $resource, list<array<string, mixed>|Resource> $items, ?string $cluster = null)
 * @method static KubernetesFake seedLogs(string $pod, string $logs, string $namespace = 'default', ?string $cluster = null)
 * @method static KubernetesFake stubExec(ExecResult|\Closure(RecordedRequest): ExecResult $result)
 * @method static KubernetesFake stubVersion(VersionInfo|string $version)
 * @method static KubernetesFake unreachable(bool $unreachable = true)
 * @method static list<RecordedRequest> recorded(?\Closure(RecordedRequest): bool $filter = null)
 * @method static void assertSent(\Closure(RecordedRequest): bool $callback)
 * @method static void assertNothingSent()
 * @method static void assertCreated(string $resource, string|(\Closure(RecordedRequest): bool)|null $constraint = null)
 * @method static void assertNothingCreated()
 * @method static void assertUpdated(string $resource, string|(\Closure(RecordedRequest): bool)|null $constraint = null)
 * @method static void assertNothingUpdated()
 * @method static void assertPatched(string $resource, string|(\Closure(RecordedRequest): bool)|null $constraint = null)
 * @method static void assertNothingPatched()
 * @method static void assertScaled(string $resource, string $name, ?int $replicas = null)
 * @method static void assertNothingScaled()
 * @method static void assertRestarted(string $resource, string $name)
 * @method static void assertNothingRestarted()
 * @method static void assertDeleted(string $resource, string|(\Closure(RecordedRequest): bool)|null $constraint = null)
 * @method static void assertNothingDeleted()
 * @method static void assertExecuted(string $pod, ?list<string> $command = null)
 * @method static void assertNothingExecuted()
 *
 * @see KubernetesManager
 */
final class Kubernetes extends Facade
{
    /**
     * Swap the manager — behind the facade and in the container — for an in-memory
     * apiserver that records every request and never touches the network. Clusters
     * registered before the swap carry over.
     */
    public static function fake(): KubernetesFake
    {
        $app = self::getFacadeApplication();
        $current = self::getFacadeRoot();

        $fake = new KubernetesFake(
            $app ?? Container::getInstance(),
            $current instanceof KubernetesManager ? $current : null,
        );

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return KubernetesManager::class;
    }
}
