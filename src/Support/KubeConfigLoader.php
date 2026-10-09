<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Support;

use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\Exceptions\KubeConfigException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses a kubeconfig file and resolves a named context into a {@see KubeConfig},
 * materialising any inline `*-data` PEM blocks to temp files so the HTTP client
 * can consume them as paths. Relative certificate and token-file paths are resolved
 * against the directory of the kubeconfig that defines them, as kubectl does.
 *
 * Without an explicit path every file in `KUBECONFIG` is merged the way kubectl merges
 * them: missing files are skipped, the first file to set `current-context` wins, and so
 * does the first file to define a cluster, user or context of a given name.
 */
final class KubeConfigLoader
{
    public function load(?string $path = null, ?string $context = null): KubeConfig
    {
        $config = $path !== null ? $this->readFile($path) : $this->readDefault();

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

        $this->guardSupportedAuthentication($user, $userName);

        $insecure = $this->insecureSkipTlsVerify($cluster, $clusterName);
        $tokenFile = is_string($user['tokenFile'] ?? null) && $user['tokenFile'] !== '' ? $user['tokenFile'] : null;

        return new KubeConfig(
            server: $server,
            token: $this->resolveToken($user) ?? $this->readTokenFile($tokenFile, $userName),
            clientCertificatePath: $this->resolvePem($user, 'client-certificate', 'client-certificate-data', 'crt'),
            clientKeyPath: $this->resolvePem($user, 'client-key', 'client-key-data', 'key'),
            certificateAuthorityPath: $this->resolvePem($cluster, 'certificate-authority', 'certificate-authority-data', 'ca'),
            verify: ! $insecure,
            tokenFile: $tokenFile,
        );
    }

    /**
     * One kubeconfig file, its relative file references already resolved against its
     * own directory (they must be, before files are merged).
     *
     * @return array<string, mixed>
     */
    private function readFile(string $path): array
    {
        if (! is_file($path)) {
            throw new KubeConfigException("Kubeconfig not found at {$path}.");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new KubeConfigException("Unable to read kubeconfig at {$path}.");
        }

        /** @var array<string, mixed> $config */
        $config = (array) Yaml::parse($contents);
        $directory = $this->directoryOf($path);

        foreach (['clusters' => ['cluster', ['certificate-authority']], 'users' => ['user', ['client-certificate', 'client-key', 'tokenFile']]] as $section => [$key, $references]) {
            if (! is_array($config[$section] ?? null)) {
                continue;
            }

            foreach ($config[$section] as $index => $entry) {
                foreach ($references as $reference) {
                    $file = is_array($entry) && is_array($entry[$key] ?? null) ? ($entry[$key][$reference] ?? null) : null;

                    if (is_string($file) && $file !== '') {
                        $config[$section][$index][$key][$reference] = $this->resolvePath($file, $directory);
                    }
                }
            }
        }

        return $config;
    }

    /**
     * Every existing file in `KUBECONFIG`, merged; `~/.kube/config` without it.
     *
     * @return array<string, mixed>
     */
    private function readDefault(): array
    {
        $env = getenv('KUBECONFIG');

        if (! is_string($env) || $env === '') {
            return $this->readFile(rtrim((string) (getenv('HOME') ?: ''), '/').'/.kube/config');
        }

        $paths = array_values(array_unique(array_filter(explode(PATH_SEPARATOR, $env), static fn (string $path): bool => $path !== '')));
        $existing = array_values(array_filter($paths, is_file(...)));

        if ($existing === []) {
            throw new KubeConfigException('Kubeconfig not found at '.implode(', ', $paths).'.');
        }

        return $this->merge(array_map($this->readFile(...), $existing));
    }

    /**
     * kubectl's merge: the first `current-context` set wins, and so does the first
     * cluster, user or context of a given name.
     *
     * @param  list<array<string, mixed>>  $configs
     * @return array<string, mixed>
     */
    private function merge(array $configs): array
    {
        $merged = ['clusters' => [], 'users' => [], 'contexts' => []];
        $seen = [];

        foreach ($configs as $config) {
            $current = $config['current-context'] ?? null;

            if (! isset($merged['current-context']) && is_string($current) && $current !== '') {
                $merged['current-context'] = $current;
            }

            foreach (['clusters', 'users', 'contexts'] as $section) {
                foreach ((array) ($config[$section] ?? []) as $entry) {
                    $name = is_array($entry) ? ($entry['name'] ?? null) : null;

                    if (is_string($name) && ! isset($seen[$section][$name])) {
                        $seen[$section][$name] = true;
                        $merged[$section][] = $entry;
                    }
                }
            }
        }

        return $merged;
    }

    /**
     * A user authenticating any way other than a token, a token file or a client
     * certificate would otherwise load with no credentials, and every request would go
     * out anonymous (401/403) with no hint why — so it throws, naming the method.
     *
     * @param  array<string, mixed>  $user
     */
    private function guardSupportedAuthentication(array $user, string $userName): void
    {
        $unsupported = match (true) {
            ! empty($user['exec']) => 'an exec credential plugin',
            ! empty($user['auth-provider']) => 'an auth provider',
            ! empty($user['username']) || ! empty($user['password']) => 'a username and password',
            default => null,
        };

        if ($unsupported !== null) {
            throw new KubeConfigException(
                "User '{$userName}' authenticates with {$unsupported}, which is not supported: use a token, a tokenFile or a client certificate.",
            );
        }
    }

    /**
     * The token file's content at load time; requests read it again (a rotated token).
     */
    private function readTokenFile(?string $tokenFile, string $userName): ?string
    {
        if ($tokenFile === null) {
            return null;
        }

        $token = is_file($tokenFile) && is_readable($tokenFile) ? file_get_contents($tokenFile) : false;

        if ($token === false || trim($token) === '') {
            throw new KubeConfigException("User '{$userName}' has a tokenFile that cannot be read: {$tokenFile}.");
        }

        return trim($token);
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
     * A file reference from the kubeconfig: as given when absolute, otherwise relative
     * to the kubeconfig's directory (kubectl semantics, not the CWD).
     */
    private function resolvePath(string $file, string $directory): string
    {
        if ($this->isAbsolute($file)) {
            return $file;
        }

        while (str_starts_with($file, './')) {
            $file = substr($file, 2);
        }

        return rtrim($directory, '/\\').'/'.$file;
    }

    /**
     * Resolve a PEM either from a file reference (`*`, already resolved against its
     * kubeconfig's directory) or an inline base64 `*-data` block, writing inline data
     * to a private temp file (one per distinct PEM, removed when the process exits)
     * and returning its path.
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

        return TemporaryPemFiles::for($decoded, $suffix);
    }
}
