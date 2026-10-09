<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Support\InClusterConfigLoader;
use RoundlyConsulting\KubernetesApi\Tests\Fixtures\LocalServer;

/*
 * The kubelet rotates a projected service-account token (at 80% of its TTL, or every
 * 24 h) and leaves reloading it to the application. A cluster resolved once and kept
 * for the life of a queue worker must read the token file again, not reuse the token
 * it saw at boot.
 */

beforeEach(function (): void {
    $this->tokenPath = (string) tempnam(sys_get_temp_dir(), 'sa-token-');
    $this->caPath = (string) tempnam(sys_get_temp_dir(), 'sa-ca-');
    file_put_contents($this->tokenPath, "token-one\n");
    file_put_contents($this->caPath, 'CA');
});

afterEach(function (): void {
    putenv('KUBERNETES_SERVICE_HOST');
    putenv('KUBERNETES_SERVICE_PORT');
    @unlink($this->tokenPath);
    @unlink($this->caPath);
});

it('re-reads a rotated in-cluster token for every request', function (): void {
    putenv('KUBERNETES_SERVICE_HOST=10.0.0.1');
    putenv('KUBERNETES_SERVICE_PORT=443');
    app()->bind(InClusterConfigLoader::class, fn (): InClusterConfigLoader => new InClusterConfigLoader($this->tokenPath, $this->caPath));
    config()->set('kubernetes.clusters.pod', ['source' => 'in-cluster']);

    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['major' => '1', 'minor' => '34', 'gitVersion' => 'v1.34.0'])]);

    Kubernetes::cluster('pod')->version();
    file_put_contents($this->tokenPath, "token-two\n");
    Kubernetes::cluster('pod')->version();

    $sent = Http::recorded()->map(fn (array $pair): string => $pair[0]->header('Authorization')[0] ?? '')->all();

    expect($sent)->toBe(['Bearer token-one', 'Bearer token-two']);
});

it('keeps the last token when the token file cannot be read', function (): void {
    $cluster = Kubernetes::url('https://k8s.test')->withToken('fallback')->withTokenFile('/no/such/token');

    expect($cluster->getToken())->toBe('fallback')
        ->and($cluster->getTokenFile())->toBe('/no/such/token');
});

it('lets an explicit token replace a token file', function (): void {
    $cluster = Kubernetes::url('https://k8s.test')->withTokenFile($this->tokenPath)->withToken('explicit');

    expect($cluster->getToken())->toBe('explicit')
        ->and($cluster->getTokenFile())->toBeNull();
});

it('sends the rotated token on the exec websocket too', function (): void {
    $server = LocalServer::script(__DIR__.'/../Fixtures/servers/exec.php');

    try {
        $pod = Kubernetes::url($server->url())->withTokenFile($this->tokenPath)->pods()->setNamespace('default')->setName('api');

        $first = $pod->exec(['whoami'])->stdout;
        file_put_contents($this->tokenPath, 'token-two');
        $second = $pod->exec(['whoami'])->stdout;
    } finally {
        $server->stop();
    }

    expect([$first, $second])->toBe(['Bearer token-one', 'Bearer token-two']);
});

it('carries the token file from the in-cluster loader', function (): void {
    putenv('KUBERNETES_SERVICE_HOST=10.0.0.1');
    putenv('KUBERNETES_SERVICE_PORT=443');

    $config = (new InClusterConfigLoader($this->tokenPath, $this->caPath))->load();

    expect($config->token)->toBe('token-one')
        ->and($config->tokenFile)->toBe($this->tokenPath)
        ->and(Kubernetes::connect($config)->getTokenFile())->toBe($this->tokenPath);
});
