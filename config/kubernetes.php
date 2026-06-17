<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources;

return [
    'client' => [
        'options' => [
            'timeout' => 5,
        ],
    ],

    /*
     * Traefik ships its CRDs under the `traefik.io` API group since v3
     * (formerly `traefik.containo.us`). Override this to point the bundled
     * Traefik resources at whichever group your cluster exposes; set it to
     * `traefik.containo.us/v1alpha1` for older Traefik installations.
     */
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
    ],
];
