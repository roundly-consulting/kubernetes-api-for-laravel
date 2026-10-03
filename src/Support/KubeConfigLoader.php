<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Support;

use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\Exceptions\KubeConfigException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses a kubeconfig file and resolves a named context into a {@see KubeConfig},
 * materialising any inline `*-data` PEM blocks to temp files so the HTTP client
 * can consume them as paths. Relative certificate paths are resolved against the
 * kubeconfig's own directory, as kubectl does.
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

        $insecure = $this->insecureSkipTlsVerify($cluster, $clusterName);
        $directory = $this->directoryOf($path);

        return new KubeConfig(
            server: $server,
            token: $this->resolveToken($user),
            clientCertificatePath: $this->resolvePem($user, 'client-certificate', 'client-certificate-data', 'crt', $directory),
            clientKeyPath: $this->resolvePem($user, 'client-key', 'client-key-data', 'key', $directory),
            certificateAuthorityPath: $this->resolvePem($cluster, 'certificate-authority', 'certificate-authority-data', 'ca', $directory),
            verify: ! $insecure,
        );
    }

    /**
     * The cluster's `insecure-skip-tls-verify`, read as a boolean. A `(bool)` cast
     * read a quoted YAML `"false"` as TRUE and skipped TLS verification; only a
     * boolean or a boolean word (`true`/`false`, `yes`/`no`, `on`/`off`, `1`/`0`)
     * is accepted now, and anything else throws rather than guessing which way
     * certificate checks should go. Absent (or null) keeps verification on.
     *
     * @param  array<string, mixed>  $cluster
     */
    private function insecureSkipTlsVerify(array $cluster, string $clusterName): bool
    {
        $value = $cluster['insecure-skip-tls-verify'] ?? null;

        if ($value === null || is_bool($value)) {
            return $value ?? false;
        }

        if (is_string($value) || is_int($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        throw new KubeConfigException(sprintf(
            "Cluster '%s' has an invalid insecure-skip-tls-verify value [%s]: use true or false.",
            $clusterName,
            is_scalar($value) ? (string) $value : get_debug_type($value),
        ));
    }

    /**
     * The kubeconfig's directory as an absolute path: the base its relative
     * certificate paths are resolved against (kubectl semantics, not the CWD).
     */
    private function directoryOf(string $path): string
    {
        $directory = dirname($path);

        return $this->isAbsolute($directory) ? $directory : rtrim((string) getcwd(), '/\\').'/'.$directory;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
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
     * Resolve a PEM either from a file reference (`*`, relative to the kubeconfig's
     * directory unless absolute) or an inline base64 `*-data` block, writing inline
     * data to a private temp file (one per distinct PEM, removed when the process
     * exits) and returning its path.
     *
     * @param  array<string, mixed>  $source
     */
    private function resolvePem(array $source, string $fileKey, string $dataKey, string $suffix, string $directory): ?string
    {
        $file = $source[$fileKey] ?? null;

        if (is_string($file) && $file !== '') {
            if ($this->isAbsolute($file)) {
                return $file;
            }

            while (str_starts_with($file, './')) {
                $file = substr($file, 2);
            }

            return rtrim($directory, '/\\').'/'.$file;
        }

        $data = $source[$dataKey] ?? null;

        if (! is_string($data) || $data === '') {
            return null;
        }

        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            throw new KubeConfigException("Invalid base64 in kubeconfig '{$dataKey}'.");
        }

        return TemporaryPemFiles::for($decoded, $suffix);
    }
}
