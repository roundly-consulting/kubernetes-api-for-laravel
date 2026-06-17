<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Tests\Integration;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Namespaces;
use RoundlyConsulting\KubernetesApi\Support\IntegrationGuard;
use RuntimeException;
use Throwable;

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

    /**
     * Create a fresh throwaway namespace and block until it is Active, so
     * dependent objects can be created in it without races.
     */
    public static function createNamespace(Kubernetes $cluster, string $namespace): void
    {
        Namespaces::make()->setCluster($cluster)->setName($namespace)->updateOrCreate();

        retry(20, function () use ($cluster, $namespace): void {
            $ns = Namespaces::make()->setCluster($cluster)->setName($namespace)->find();
            throw_unless($ns->isActive(), new RuntimeException('namespace not active'));
        }, 250);
    }

    /**
     * Best-effort deletion of a throwaway namespace; never throws so it is safe
     * in an afterEach even when the test already failed.
     */
    public static function deleteNamespace(Kubernetes $cluster, string $namespace): void
    {
        try {
            Namespaces::make()->setCluster($cluster)->setName($namespace)->delete();
        } catch (Throwable) {
            // best-effort cleanup
        }
    }

    /**
     * Issue an authenticated raw GET against the cluster, reusing the pinned
     * OrbStack credentials. Used for read-only discovery endpoints (`/version`,
     * `/apis`) and CRD presence checks that no typed resource covers.
     */
    public static function rawGet(Kubernetes $cluster, string $path): Response
    {
        $request = Http::baseUrl($cluster->getUrl())->withUserAgent((string) $cluster->getManagerName());

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

        return $request->get($path);
    }

    /**
     * Whether the cluster serves the given API group (e.g. `traefik.io`). Used
     * to skip CRD-dependent cases on clusters that don't ship them.
     */
    public static function hasApiGroup(Kubernetes $cluster, string $group): bool
    {
        $response = self::rawGet($cluster, '/apis');

        if ($response->failed()) {
            return false;
        }

        /** @var list<array<string, mixed>> $groups */
        $groups = (array) $response->json('groups', []);

        foreach ($groups as $entry) {
            if (($entry['name'] ?? null) === $group) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a throwaway self-signed certificate + private key (PEM) for TLS
     * Secret / TLSStore integration cases. Nothing is committed; the material
     * lives only for the duration of the test run.
     *
     * @return array{cert: string, key: string}
     */
    public static function selfSignedCertificate(string $commonName = 'integration.test'): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($privateKey === false) {
            throw new RuntimeException('Unable to generate a private key for the TLS integration fixture.');
        }

        $csr = openssl_csr_new(['commonName' => $commonName], $privateKey);

        if ($csr === false) {
            throw new RuntimeException('Unable to generate a CSR for the TLS integration fixture.');
        }

        $signed = openssl_csr_sign($csr, null, $privateKey, 1);

        if ($signed === false) {
            throw new RuntimeException('Unable to self-sign the TLS integration fixture.');
        }

        openssl_x509_export($signed, $certPem);
        openssl_pkey_export($privateKey, $keyPem);

        return ['cert' => (string) $certPem, 'key' => (string) $keyPem];
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
