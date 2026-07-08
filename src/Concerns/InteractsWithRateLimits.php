<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Concerns;

use Closure;
use Illuminate\Http\Client\Response;
use RoundlyConsulting\HttpClientRateLimits\Enums\Timespan;
use RoundlyConsulting\HttpClientRateLimits\Exceptions\RateLimitExceededException as HttpRateLimitExceededException;
use RoundlyConsulting\HttpClientRateLimits\Limit;
use RoundlyConsulting\HttpClientRateLimits\RateLimit;
use RoundlyConsulting\KubernetesApi\Exceptions\RateLimitExceededException;
use RoundlyConsulting\KubernetesApi\Kubernetes;

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
     */
    protected function rateLimiter(Kubernetes $cluster): ?RateLimit
    {
        /** @var array<string, mixed> $config */
        $config = config('kubernetes.rate_limits', []);

        if (($config['enabled'] ?? true) === false) {
            return null;
        }

        $timespan = Timespan::tryFrom((string) ($config['timespan'] ?? 'minute')) ?? Timespan::Minute;
        $owner = (string) ($config['owner'] ?? 'app');

        $rateLimit = RateLimit::make(new Limit(
            maxAttempts: (int) ($config['max_attempts'] ?? 400),
            timespan: $timespan,
        ))->by("k8s:{$owner}:{$this->clusterKey($cluster)}");

        if (($config['adaptive'] ?? true) === true) {
            $rateLimit->adaptive();
        }

        if (isset($config['max_wait']) && is_numeric($config['max_wait'])) {
            $rateLimit->maxWait((int) $config['max_wait']);
        }

        if (isset($config['jitter']) && is_numeric($config['jitter'])) {
            $rateLimit->jitter((int) $config['jitter']);
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
    protected function throttled(Kubernetes $cluster, Closure $send): Response
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
                cluster: $this->clusterKey($cluster),
                availableInSeconds: (int) ceil($exception->delayMs / 1000),
            );
        }
    }

    /**
     * Identity used to key a cluster's client-side budget. K8s API Priority &
     * Fairness is per-apiserver, so keying per cluster is correct — two clusters
     * never share a window. Prefers the manager name, else the request host.
     */
    protected function clusterKey(Kubernetes $cluster): string
    {
        $managerName = $cluster->getManagerName();

        if (is_string($managerName) && $managerName !== '') {
            return $managerName;
        }

        $url = $cluster->getUrl();
        $host = parse_url($url, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return $host;
        }

        return $url !== '' ? substr(md5($url), 0, 12) : 'default';
    }
}
