<?php

declare(strict_types=1);

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
