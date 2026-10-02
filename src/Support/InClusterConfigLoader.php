<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Support;

use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\Exceptions\KubeConfigException;

/**
 * Builds a {@see KubeConfig} from the service-account credentials mounted into
 * a pod at `/var/run/secrets/kubernetes.io/serviceaccount`, plus the
 * `KUBERNETES_SERVICE_HOST`/`KUBERNETES_SERVICE_PORT` env the kubelet injects.
 */
final class InClusterConfigLoader
{
    private const TOKEN_PATH = '/var/run/secrets/kubernetes.io/serviceaccount/token';

    private const CA_PATH = '/var/run/secrets/kubernetes.io/serviceaccount/ca.crt';

    public function __construct(
        private readonly string $tokenPath = self::TOKEN_PATH,
        private readonly string $caPath = self::CA_PATH,
    ) {}

    public function load(): KubeConfig
    {
        $host = getenv('KUBERNETES_SERVICE_HOST');
        $port = getenv('KUBERNETES_SERVICE_PORT');

        if (! is_string($host) || $host === '' || ! is_string($port) || $port === '') {
            throw new KubeConfigException('Not running in-cluster: KUBERNETES_SERVICE_HOST/PORT are not set.');
        }

        if (! is_file($this->tokenPath)) {
            throw new KubeConfigException("Service-account token not found at {$this->tokenPath}.");
        }

        $token = file_get_contents($this->tokenPath);

        if ($token === false || trim($token) === '') {
            throw new KubeConfigException('Service-account token is empty.');
        }

        // The service-account token must never travel over unverified TLS: without the
        // mounted cluster CA there is nothing to verify the apiserver against.
        if (! is_file($this->caPath)) {
            throw new KubeConfigException("Service-account CA certificate not found at {$this->caPath}.");
        }

        // An IPv6 service host goes in brackets, or the URL's port is ambiguous.
        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            $host = "[{$host}]";
        }

        return new KubeConfig(
            server: "https://{$host}:{$port}",
            token: trim($token),
            certificateAuthorityPath: $this->caPath,
            verify: true,
        );
    }
}
