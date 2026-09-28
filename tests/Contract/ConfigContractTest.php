<?php

declare(strict_types=1);

/**
 * The config-key contract kubernetes-api never had, pinned in both directions.
 *
 *  - forward — every key the code reads is shipped. This is shops #18, whose whole
 *    store-credit feature read `shops.payments.*` while the file shipped `payment.*`;
 *    330 tests stayed green because the suite set the same wrong key.
 *  - reverse — every shipped leaf is read. A documented key nothing reads is dead
 *    config that lies to the host: media #27's `max_file_size` cap that never applied.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/kubernetes.php')->toSatisfyConfigContract(__DIR__.'/../../src', [
        // Deliberately NO blanket `extraReadPrefixes => ['kubernetes.']`, which is the
        // obvious move here and is actively WRONG for this package: a prefix rule counts
        // any literal under it as a read, wherever it appears, and `kubernetes.` is not
        // only our config namespace — it is the Kubernetes API's OWN namespace. The
        // literal `kubernetes.io/tls` in Resources\Secret (a cluster Secret *type*) and
        // `kubectl.kubernetes.io/restartedAt` in CanRolloutRestart (an annotation) would
        // both scrape as config reads. The first genuinely did: it failed the FORWARD
        // direction as a key the file "does not ship", which is true and meaningless.
        //
        // Nothing here needs the prefix anyway — this package has no model seam and no
        // injected-Repository reads, so every real read is a literal `config()` token the
        // scraper already sees.

        // `rate_limiter()` takes the whole `rate_limits` section and reads it by offset
        // rather than through seven `config()` calls. Those offsets ARE the reads, so
        // mapping the variable is what makes them visible — and it is strictly better
        // than `allowUnread`, which would assert a falsehood: these keys are read, and
        // each one steers a real limiter decision.
        //
        // A cluster entry is read the same way: `KubernetesManager::clusterFromConfig()`
        // takes one `clusters.<name>` array and reads its offsets. The shipped entry is
        // `default`, so the offsets map onto its leaves — every cluster a host adds has
        // the same keys.
        'sectionVariables' => [
            'InteractsWithRateLimits.php' => ['$config' => 'kubernetes.rate_limits'],
            'KubernetesManager.php' => ['$definition' => 'kubernetes.clusters.default'],
        ],

        // The two genuine map-shaped reads, allowed per leaf rather than by excluding
        // their parent wholesale — `allowUnread` is rot-proof, so a stale entry that
        // silences nothing is itself a failure. Removing a resource from the config
        // fails here until this list matches, which makes the list a live pin on the
        // registered-resource surface rather than a blanket.
        'allowUnread' => [
            // `client.options` is handed to Guzzle wholesale via `withOptions()`. The
            // leaf is applied, never read by name — Guzzle owns the key vocabulary, and
            // enumerating it here would be inventing a contract we do not define.
            'kubernetes.client.options.timeout',

            // The resource registry is a MAP, not a group of keys: its leaves are
            // resource *names* the provider iterates (`foreach ($resources as $name =>
            // $class)`) and registers by name. Nothing reads
            // `config('kubernetes.resources.pods')` and nothing should — the map itself
            // IS read. The reverse direction is right that no leaf is individually read
            // and wrong about what that means. This is the metrics `trend_drivers`
            // precedent, applied for the same reason.
            'kubernetes.resources.clusterRoles',
            'kubernetes.resources.clusterRoleBindings',
            'kubernetes.resources.configMaps',
            'kubernetes.resources.cronJobs',
            'kubernetes.resources.daemonSets',
            'kubernetes.resources.deployments',
            'kubernetes.resources.endpoints',
            'kubernetes.resources.events',
            'kubernetes.resources.horizontalPodAutoscalers',
            'kubernetes.resources.ingresses',
            'kubernetes.resources.jobs',
            'kubernetes.resources.limitRanges',
            'kubernetes.resources.namespaces',
            'kubernetes.resources.networkPolicies',
            'kubernetes.resources.nodes',
            'kubernetes.resources.persistentVolumes',
            'kubernetes.resources.persistentVolumeClaims',
            'kubernetes.resources.pods',
            'kubernetes.resources.replicaSets',
            'kubernetes.resources.replicationControllers',
            'kubernetes.resources.resourceQuotas',
            'kubernetes.resources.roles',
            'kubernetes.resources.roleBindings',
            'kubernetes.resources.secrets',
            'kubernetes.resources.serviceAccounts',
            'kubernetes.resources.services',
            'kubernetes.resources.statefulSets',
            'kubernetes.resources.storageClasses',
            'kubernetes.resources.traefikIngressRoutes',
            'kubernetes.resources.traefikMiddlewares',
            'kubernetes.resources.traefikServersTransports',
            'kubernetes.resources.traefikTlsOptions',
            'kubernetes.resources.traefikTlsStores',
        ],
    ]);
});
