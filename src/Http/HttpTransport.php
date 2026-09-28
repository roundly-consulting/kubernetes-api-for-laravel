<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\Concerns\InteractsWithRateLimits;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\ExecResult;
use RoundlyConsulting\KubernetesApi\Exceptions\ClusterConfigurationException;
use RoundlyConsulting\KubernetesApi\WebSocket\ExecConnection;

/**
 * The real transport: Laravel's HTTP client (so `Http::fake()` still works), the
 * cluster's credentials, the per-cluster rate limiter, and a WebSocket for exec.
 */
final class HttpTransport implements Transport
{
    use InteractsWithRateLimits;

    /** @param array<string, mixed> $query */
    public function send(
        Cluster $cluster,
        string $method,
        string $path,
        array $query = [],
        string $body = '',
        ?string $contentType = null,
        bool $stream = false,
    ): Response {
        if ($cluster->getUrl() === '') {
            throw ClusterConfigurationException::missingUrl($cluster->name());
        }

        $request = Http::baseUrl($cluster->getUrl())
            ->withUserAgent((string) $cluster->getManagerName())
            ->withHeaders(['Accept-Encoding' => 'gzip, deflate']);

        if ($stream) {
            $request->withOptions(['stream' => true]);
        }

        $this->authenticate($request, $cluster);

        $request->withOptions((array) config('kubernetes.client.options', []));

        if ($contentType !== null) {
            $request->withBody($body, $contentType);
        } else {
            $request->withBody($body);
        }

        $queryString = $this->queryString($query);
        $url = $queryString === '' ? $path : "{$path}?{$queryString}";

        // The limiter callback returns the raw Response (429 and all) so hcrl's
        // adaptive path can read the apiserver's `Retry-After` header before the
        // cluster converts a failed response into an exception.
        return $this->throttled($cluster, fn (): Response => $request->send($method, $url));
    }

    /**
     * @codeCoverageIgnore Exercised by the live OrbStack integration suite, like
     *   the {@see ExecConnection} it opens.
     */
    public function exec(Cluster $cluster, string $path): ExecResult
    {
        return (new ExecConnection($cluster))->send($path);
    }

    private function authenticate(PendingRequest $request, Cluster $cluster): void
    {
        if ($cluster->shouldVerify()) {
            $request->withOptions([
                'verify' => $cluster->hasPathToCaCertificate() ? $cluster->getPathToCaCertificate() : true,
            ]);
        } else {
            $request->withoutVerifying();
        }

        if ($cluster->hasToken()) {
            $request->withToken((string) $cluster->getToken());
        }

        if ($cluster->hasPathToCertificate()) {
            $request->withOptions(['cert' => $cluster->getPathToCertificate()]);
        }

        if ($cluster->hasPathToPrivateKey()) {
            $request->withOptions(['ssl_key' => $cluster->getPathToPrivateKey()]);
        }
    }

    /**
     * Repeated keys (`command=a&command=b`) are sent unindexed, as the apiserver expects.
     *
     * @param  array<string, mixed>  $query
     */
    private function queryString(array $query): string
    {
        return urldecode(
            (string) preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', http_build_query($query))
        );
    }
}
