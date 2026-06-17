<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Support;

use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\Exceptions\KubeConfigException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses a kubeconfig file and resolves a named context into a {@see KubeConfig},
 * materialising any inline `*-data` PEM blocks to temp files so the HTTP client
 * can consume them as paths.
 */
final class KubeConfigLoader
{
    public function load(?string $path = null, ?string $context = null): KubeConfig
    {
        $path ??= $this->defaultPath();

        if (! is_file($path)) {
            throw new KubeConfigException("Kubeconfig not found at {$path}.");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new KubeConfigException("Unable to read kubeconfig at {$path}.");
        }

        /** @var array<string, mixed> $config */
        $config = (array) Yaml::parse($contents);

        $contextName = $context ?? (is_string($config['current-context'] ?? null) ? $config['current-context'] : null);

        if ($contextName === null || $contextName === '') {
            throw new KubeConfigException('No context specified and kubeconfig has no current-context.');
        }

        $contextEntry = $this->findNamed($config, 'contexts', $contextName);
        /** @var array<string, mixed> $contextData */
        $contextData = (array) ($contextEntry['context'] ?? []);

        $clusterName = (string) ($contextData['cluster'] ?? '');
        $userName = (string) ($contextData['user'] ?? '');

        $clusterEntry = $this->findNamed($config, 'clusters', $clusterName);
        /** @var array<string, mixed> $cluster */
        $cluster = (array) ($clusterEntry['cluster'] ?? []);

        $userEntry = $this->findNamed($config, 'users', $userName);
        /** @var array<string, mixed> $user */
        $user = (array) ($userEntry['user'] ?? []);

        $server = (string) ($cluster['server'] ?? '');

        if ($server === '') {
            throw new KubeConfigException("Cluster '{$clusterName}' has no server URL.");
        }

        $insecure = (bool) ($cluster['insecure-skip-tls-verify'] ?? false);

        return new KubeConfig(
            server: $server,
            token: $this->resolveToken($user),
            clientCertificatePath: $this->resolvePem($user, 'client-certificate', 'client-certificate-data', 'crt'),
            clientKeyPath: $this->resolvePem($user, 'client-key', 'client-key-data', 'key'),
            certificateAuthorityPath: $this->resolvePem($cluster, 'certificate-authority', 'certificate-authority-data', 'ca'),
            verify: ! $insecure,
        );
    }

    private function defaultPath(): string
    {
        $env = getenv('KUBECONFIG');

        if (is_string($env) && $env !== '') {
            return explode(PATH_SEPARATOR, $env)[0];
        }

        return rtrim((string) (getenv('HOME') ?: ''), '/').'/.kube/config';
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function findNamed(array $config, string $key, string $name): array
    {
        /** @var array<int, array<string, mixed>> $entries */
        $entries = (array) ($config[$key] ?? []);

        foreach ($entries as $entry) {
            if (($entry['name'] ?? null) === $name) {
                return $entry;
            }
        }

        throw new KubeConfigException("No '{$name}' entry found under '{$key}' in kubeconfig.");
    }

    /** @param array<string, mixed> $user */
    private function resolveToken(array $user): ?string
    {
        $token = $user['token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Resolve a PEM either from a file reference (`*`) or an inline base64
     * `*-data` block, writing inline data to a temp file and returning its path.
     *
     * @param  array<string, mixed>  $source
     */
    private function resolvePem(array $source, string $fileKey, string $dataKey, string $suffix): ?string
    {
        $file = $source[$fileKey] ?? null;

        if (is_string($file) && $file !== '') {
            return $file;
        }

        $data = $source[$dataKey] ?? null;

        if (! is_string($data) || $data === '') {
            return null;
        }

        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            throw new KubeConfigException("Invalid base64 in kubeconfig '{$dataKey}'.");
        }

        $tempPath = tempnam(sys_get_temp_dir(), "k8s-{$suffix}-");

        if ($tempPath === false) {
            throw new KubeConfigException('Unable to create temp file for kubeconfig PEM.');
        }

        file_put_contents($tempPath, $decoded);

        return $tempPath;
    }
}
