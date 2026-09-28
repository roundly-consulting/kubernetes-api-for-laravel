<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Http;

use Illuminate\Http\Client\Response;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\ExecResult;

/**
 * How a {@see Cluster} reaches its apiserver. {@see HttpTransport} talks to the real
 * cluster; `Kubernetes::fake()` swaps in an in-memory one, so every resource call —
 * typed accessors, raw `request()`, logs, watch and exec — is intercepted in one place.
 */
interface Transport
{
    /**
     * Send one request and return the raw response, failed statuses included; the
     * cluster turns a failed response into a `KubernetesException`.
     *
     * @param  array<string, mixed>  $query
     */
    public function send(
        Cluster $cluster,
        string $method,
        string $path,
        array $query = [],
        string $body = '',
        ?string $contentType = null,
        bool $stream = false,
    ): Response;

    /**
     * Run an exec subresource request (path includes its query string).
     */
    public function exec(Cluster $cluster, string $path): ExecResult;
}
