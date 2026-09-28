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
use RoundlyConsulting\PackageToolkit\Support\Config;

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
            ->withHeaders(['Accept-Encoding' => 'gzip, deflate']);

        $manager = $cluster->getManagerName();

        if ($manager !== null && $manager !== '') {
            $request->withUserAgent($manager);
        }

        $this->authenticate($request, $cluster);

        $request->withOptions((array) config('kubernetes.client.options', []));

        if ($stream) {
            $request->withOptions($this->streamOptions());
        }

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

    public function exec(Cluster $cluster, string $path): ExecResult
    {
        return (new ExecConnection($cluster, idleTimeout: self::streamTimeout()))->send($path);
    }

    /**
     * A watch or a log follow is long-lived and may sit silent for minutes, so the
     * request-wide `timeout` must not cut it off. It gets no overall deadline and an
     * idle read timeout only when `kubernetes.client.stream_timeout` sets one (-1 is
     * PHP's "wait indefinitely"); when it passes the stream ends cleanly.
     *
     * @return array<string, mixed>
     */
    private function streamOptions(): array
    {
        $idle = self::streamTimeout();

        return [
            'stream' => true,
            'timeout' => 0,
            'read_timeout' => $idle > 0 ? $idle : -1,
        ];
    }

    /**
     * @internal The idle read timeout for streams (watch, log follow, exec) in
     *           seconds; 0 = none.
     */
    public static function streamTimeout(): int
    {
        if (in_array(config('kubernetes.client.stream_timeout'), [null, ''], true)) {
            return 0;
        }

        return Config::using(ClusterConfigurationException::class)
            ->intBetween('kubernetes.client.stream_timeout', 0, 86_400, 0);
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
     * RFC 3986-encoded, so a selector value can never add a parameter (`&`), turn into
     * a space (`+`) or end the query (`#`). Repeated keys (`command=a&command=b`) are
     * sent unindexed, as the apiserver expects.
     *
     * @param  array<string, mixed>  $query
     */
    private function queryString(array $query): string
    {
        return (string) preg_replace(
            '/%5B(?:[0-9]|[1-9][0-9]+)%5D=/',
            '=',
            http_build_query($query, '', '&', PHP_QUERY_RFC3986),
        );
    }
}
