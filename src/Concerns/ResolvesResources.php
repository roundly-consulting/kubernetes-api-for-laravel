<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Concerns;

use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\KubernetesManager;
use RoundlyConsulting\KubernetesApi\Resources;
use RoundlyConsulting\KubernetesApi\Resources\Resource;

/**
 * One typed accessor per packaged resource, shared by {@see Cluster}
 * (bound to that cluster) and {@see KubernetesManager} (bound to
 * the default cluster).
 *
 * Each accessor reads the `kubernetes.resources` map at call time, so a host that points a
 * name at its own subclass (`'pods' => App\Kubernetes\Pod::class`) gets that class back.
 */
trait ResolvesResources
{
    /**
     * @param  class-string<TResource>  $default
     * @return TResource
     *
     * @template TResource of Resource
     */
    abstract protected function configuredResource(string $name, string $default): Resource;

    public function clusterRoles(): Resources\ClusterRole
    {
        return $this->configuredResource('clusterRoles', Resources\ClusterRole::class);
    }

    public function clusterRoleBindings(): Resources\ClusterRoleBinding
    {
        return $this->configuredResource('clusterRoleBindings', Resources\ClusterRoleBinding::class);
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

    public function endpoints(): Resources\Endpoints
    {
        return $this->configuredResource('endpoints', Resources\Endpoints::class);
    }

    public function events(): Resources\Event
    {
        return $this->configuredResource('events', Resources\Event::class);
    }

    public function horizontalPodAutoscalers(): Resources\HorizontalPodAutoscaler
    {
        return $this->configuredResource('horizontalPodAutoscalers', Resources\HorizontalPodAutoscaler::class);
    }

    public function ingresses(): Resources\Ingress
    {
        return $this->configuredResource('ingresses', Resources\Ingress::class);
    }

    public function jobs(): Resources\Job
    {
        return $this->configuredResource('jobs', Resources\Job::class);
    }

    public function limitRanges(): Resources\LimitRange
    {
        return $this->configuredResource('limitRanges', Resources\LimitRange::class);
    }

    public function namespaces(): Resources\KubernetesNamespace
    {
        return $this->configuredResource('namespaces', Resources\KubernetesNamespace::class);
    }

    public function networkPolicies(): Resources\NetworkPolicy
    {
        return $this->configuredResource('networkPolicies', Resources\NetworkPolicy::class);
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

    public function replicationControllers(): Resources\ReplicationController
    {
        return $this->configuredResource('replicationControllers', Resources\ReplicationController::class);
    }

    public function resourceQuotas(): Resources\ResourceQuota
    {
        return $this->configuredResource('resourceQuotas', Resources\ResourceQuota::class);
    }

    public function roles(): Resources\Role
    {
        return $this->configuredResource('roles', Resources\Role::class);
    }

    public function roleBindings(): Resources\RoleBinding
    {
        return $this->configuredResource('roleBindings', Resources\RoleBinding::class);
    }

    public function secrets(): Resources\Secret
    {
        return $this->configuredResource('secrets', Resources\Secret::class);
    }

    public function serviceAccounts(): Resources\ServiceAccount
    {
        return $this->configuredResource('serviceAccounts', Resources\ServiceAccount::class);
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

    public function traefikIngressRoutes(): Resources\TraefikIngressRoute
    {
        return $this->configuredResource('traefikIngressRoutes', Resources\TraefikIngressRoute::class);
    }

    public function traefikMiddlewares(): Resources\TraefikMiddleware
    {
        return $this->configuredResource('traefikMiddlewares', Resources\TraefikMiddleware::class);
    }

    public function traefikServersTransports(): Resources\TraefikServersTransport
    {
        return $this->configuredResource('traefikServersTransports', Resources\TraefikServersTransport::class);
    }

    public function traefikTlsOptions(): Resources\TraefikTlsOption
    {
        return $this->configuredResource('traefikTlsOptions', Resources\TraefikTlsOption::class);
    }

    public function traefikTlsStores(): Resources\TraefikTlsStore
    {
        return $this->configuredResource('traefikTlsStores', Resources\TraefikTlsStore::class);
    }
}
