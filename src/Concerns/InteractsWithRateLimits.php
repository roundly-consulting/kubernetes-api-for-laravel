<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Concerns;

use Closure;
use Illuminate\Http\Client\Response;
use RoundlyConsulting\HttpClientRateLimits\Enums\Timespan;
use RoundlyConsulting\HttpClientRateLimits\Exceptions\RateLimitExceededException as HttpRateLimitExceededException;
use RoundlyConsulting\HttpClientRateLimits\Facades\RateLimits;
use RoundlyConsulting\HttpClientRateLimits\Limit;
use RoundlyConsulting\HttpClientRateLimits\RateLimit;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\Exceptions\RateLimitExceededException;
use RoundlyConsulting\PackageToolkit\Support\Config;

trait InteractsWithRateLimits
{
    /**
     * Build the client-side rate limiter for a cluster from the global
     * `kubernetes.rate_limits` config, or null when throttling is disabled.
     *
     * Requests are paced (the limiter waits until the window frees up) rather
     * than hard-failing; set a `max_wait` to fail fast instead. With `adaptive`
     * on (the default) the limiter also honours the apiserver's own
     * `Retry-After` header on a 429.
     *
     * Every key goes through package-toolkit's strict readers: an absent (null)
     * key takes its default, and a present but invalid one throws
     * InvalidConfigurationException naming it. `(int) 'five'` used to be 0, a
     * junk `max_wait` / `jitter` was dropped and a `timespan` typo became a minute.
     */
    protected function rateLimiter(Cluster $cluster): ?RateLimit
    {
        // An identity check kept throttling on for an env `0`/`off`/`no` and switched
        // `adaptive` off for `1`/`on`/`yes` or a typo.
        if (! Config::boolean('kubernetes.rate_limits.enabled', true)) {
            return null;
        }

        $owner = config('kubernetes.rate_limits.owner') === null
            ? 'app'
            : Config::requireString('kubernetes.rate_limits.owner');

        $rateLimit = RateLimits::make(new Limit(
            maxAttempts: Config::integer('kubernetes.rate_limits.max_attempts', 400, min: 1),
            timespan: Config::enum('kubernetes.rate_limits.timespan', Timespan::class, Timespan::Minute),
        ))->by("k8s:{$owner}:{$this->clusterKey($cluster)}");

        if (Config::boolean('kubernetes.rate_limits.adaptive', true)) {
            $rateLimit->adaptive();
        }

        if (config('kubernetes.rate_limits.max_wait') !== null) {
            $rateLimit->maxWait(Config::integer('kubernetes.rate_limits.max_wait', 0, min: 0));
        }

        if (config('kubernetes.rate_limits.jitter') !== null) {
            $rateLimit->jitter(Config::integer('kubernetes.rate_limits.jitter', 0, min: 0));
        }

        return $rateLimit;
    }

    /**
     * Send a cluster request through the limiter, translating hcrl's own
     * exhaustion exception into the package's typed exception so hosts catch a
     * meaningful, K8s-native type.
     *
     * @param  Closure(): Response  $send
     */
    protected function throttled(Cluster $cluster, Closure $send): Response
    {
        $rateLimit = $this->rateLimiter($cluster);

        if ($rateLimit === null) {
            return $send();
        }

        try {
            /** @var Response $response */
            $response = $rateLimit->handle($send);

            return $response;
        } catch (HttpRateLimitExceededException $exception) {
            throw RateLimitExceededException::for(
                cluster: $cluster->name() ?? $this->clusterKey($cluster),
                retryAfterSeconds: (int) ceil($exception->delayMs / 1000),
            );
        }
    }

    /**
     * Identity used to key a cluster's client-side budget: the apiserver endpoint —
     * host, non-default port and path prefix (a Rancher-style proxy serves many
     * clusters from one host). Kubernetes API Priority & Fairness is per-apiserver,
     * so two clusters never share a window, while every client of one apiserver
     * (whatever its manager name or token) shares its budget.
     */
    protected function clusterKey(Cluster $cluster): string
    {
        $url = $cluster->getUrl();
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['host'] ?? '') === '') {
            return $url !== '' ? $url : 'default';
        }

        $key = strtolower((string) $parts['host']);
        $port = $parts['port'] ?? null;

        if ($port !== null && $port !== (strtolower($parts['scheme'] ?? 'https') === 'http' ? 80 : 443)) {
            $key .= ":{$port}";
        }

        return $key.rtrim($parts['path'] ?? '', '/');
    }
}
