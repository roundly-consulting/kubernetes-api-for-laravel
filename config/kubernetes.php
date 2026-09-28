<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources;

return [
    /*
     * The cluster `Kubernetes::pods()`, `Kubernetes::ping()` and every other
     * default-cluster shortcut talk to, and `kubernetes:ping` checks without an
     * argument. Must name an entry below or a cluster registered in code.
     */
    'default' => env('KUBERNETES_CLUSTER', 'default'),

    /*
     * Named clusters, resolved lazily the first time they are used. `source` picks
     * where the connection comes from:
     *  - `url`        — the `url` and credential keys below;
     *  - `kubeconfig` — a kubeconfig file (`kubeconfig`, null = KUBECONFIG or
     *                   ~/.kube/config) and `context` (null = current-context);
     *  - `in-cluster` — the service account mounted into the pod.
     * `namespace` is the default for namespaced resources; `manager` is the field
     * manager / user agent (and keys the cluster's rate-limit budget). Clusters
     * registered with `Kubernetes::registerCluster()` override entries of the same name.
     */
    'clusters' => [
        'default' => [
            'source' => env('KUBERNETES_SOURCE', 'url'),
            'url' => env('KUBERNETES_URL'),
            'token' => env('KUBERNETES_TOKEN'),
            'certificate' => env('KUBERNETES_CLIENT_CERTIFICATE'),
            'private_key' => env('KUBERNETES_CLIENT_KEY'),
            'ca_certificate' => env('KUBERNETES_CA_CERTIFICATE'),
            'verify' => env('KUBERNETES_VERIFY_SSL', true),
            'kubeconfig' => env('KUBERNETES_KUBECONFIG'),
            'context' => env('KUBERNETES_CONTEXT'),
            'namespace' => env('KUBERNETES_NAMESPACE', 'default'),
            'manager' => env('KUBERNETES_MANAGER'),
        ],
    ],

    'client' => [
        'options' => [
            'timeout' => 5,
        ],
    ],

    /*
     * Client-side rate limiting for every apiserver request, powered by
     * roundly-consulting/http-client-rate-limits. Each cluster gets its own
     * budget (keyed by manager name or host), so one busy cluster never starves
     * another. By default requests are *paced* (the limiter waits for the window
     * to free up); set `max_wait` to fail fast with a RateLimitExceededException
     * instead. With `adaptive` on, a 429 `Retry-After` from the apiserver
     * self-tunes the limiter. Set `enabled => false` for the raw, unthrottled
     * client. For a budget shared across workers, point hcrl's `store` at
     * Cache/Redis/Database via HTTP_CLIENT_RATE_LIMITS_STORE.
     */
    'rate_limits' => [
        'enabled' => env('KUBERNETES_RATELIMIT_ENABLED', true),
        'owner' => env('KUBERNETES_RATELIMIT_OWNER', 'app'),
        'max_attempts' => env('KUBERNETES_RATELIMIT', 400),
        'timespan' => env('KUBERNETES_RATELIMIT_TIMESPAN', 'minute'), // second|minute|hour|day
        'adaptive' => env('KUBERNETES_RATELIMIT_ADAPTIVE', true),     // honour 429 Retry-After
        'max_wait' => env('KUBERNETES_RATELIMIT_MAX_WAIT'),           // ms; null = pace, set = fail fast
        'jitter' => env('KUBERNETES_RATELIMIT_JITTER'),               // ms; null = none
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
        'namespaces' => Resources\KubernetesNamespace::class,
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
        'traefikServersTransports' => Resources\TraefikServersTransport::class,
        'traefikTlsOptions' => Resources\TraefikTlsOption::class,
        'traefikTlsStores' => Resources\TraefikTlsStore::class,
    ],
];
