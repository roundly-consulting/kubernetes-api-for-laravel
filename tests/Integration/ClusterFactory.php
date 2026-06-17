<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Tests\Integration;

use RoundlyConsulting\KubernetesApi\Kubernetes;
use RoundlyConsulting\KubernetesApi\Support\IntegrationGuard;
use RuntimeException;

/**
 * Builds a {@see Kubernetes} client pointed at the local OrbStack cluster for
 * the live integration suite, after running the {@see IntegrationGuard}. The
 * client credentials are extracted from the pinned `orbstack` kubeconfig
 * context only — never the user's global/default context.
 */
final class ClusterFactory
{
    public const RANDOM_NS_PREFIX = 'k8s-it-';

    /** @var list<string> */
    private static array $tempFiles = [];

    public static function context(): string
    {
        return env('K8S_INTEGRATION_CONTEXT', IntegrationGuard::ALLOWED_CONTEXT);
    }

    public static function shouldRun(): bool
    {
        return self::guard()->enabled();
    }

    public static function guard(): IntegrationGuard
    {
        return new IntegrationGuard(
            flag: env('K8S_INTEGRATION'),
            context: self::context(),
            server: self::serverUrl(),
        );
    }

    public static function make(): Kubernetes
    {
        self::guard()->assertSafe();

        $view = self::kubeconfigView();

        $cluster = $view['clusters'][0]['cluster'] ?? [];
        $user = $view['users'][0]['user'] ?? [];

        $client = Kubernetes::make()
            ->url(self::serverUrl())
            ->setManagerName('k8s-integration-tests');

        if (isset($cluster['certificate-authority-data'])) {
            $client->withCaCertificate(self::materialise('ca', (string) $cluster['certificate-authority-data']));
        } else {
            $client->withoutSslVerification();
        }

        if (isset($user['client-certificate-data'], $user['client-key-data'])) {
            $client
                ->withCertificate(self::materialise('crt', (string) $user['client-certificate-data']))
                ->withPrivateKey(self::materialise('key', (string) $user['client-key-data']));
        }

        return $client;
    }

    public static function namespace(): string
    {
        $configured = env('K8S_INTEGRATION_NAMESPACE');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return self::RANDOM_NS_PREFIX.bin2hex(random_bytes(4));
    }

    public static function cleanup(): void
    {
        foreach (self::$tempFiles as $file) {
            @unlink($file);
        }

        self::$tempFiles = [];
    }

    private static function serverUrl(): string
    {
        return self::kubeconfigView()['clusters'][0]['cluster']['server'] ?? '';
    }

    /** @return array<string, mixed> */
    private static function kubeconfigView(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $context = self::context();
        $command = "kubectl --context={$context} config view --raw --minify -o json";
        $output = shell_exec($command);

        if (! is_string($output) || trim($output) === '') {
            throw new RuntimeException("Unable to read kubeconfig for context '{$context}'.");
        }

        /** @var array<string, mixed> $decoded */
        $decoded = (array) json_decode($output, true);

        return $cache = $decoded;
    }

    private static function materialise(string $suffix, string $base64): string
    {
        $path = tempnam(sys_get_temp_dir(), "k8s-it-{$suffix}-");
        file_put_contents($path, base64_decode($base64));

        self::$tempFiles[] = $path;

        return $path;
    }
}
