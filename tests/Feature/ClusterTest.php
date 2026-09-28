<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Traits\Macroable;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\Exceptions\ClusterConfigurationException;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Exceptions\RateLimitExceededException;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;
use RoundlyConsulting\KubernetesApi\Resources\Pod;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\StatefulSet;
use RoundlyConsulting\KubernetesApi\Resources\TraefikServersTransport;
use RoundlyConsulting\KubernetesApi\Resources\TraefikTlsOption;
use RoundlyConsulting\KubernetesApi\Resources\TraefikTlsStore;
use RoundlyConsulting\KubernetesApi\Traits\HasAuthentication;
use RoundlyConsulting\KubernetesApi\Traits\HasManagerName;
use RoundlyConsulting\KubernetesApi\Traits\HasUrl;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('uses required traits', function () {
    expect(Cluster::class)->toUse([
        Macroable::class,
        HasUrl::class,
        HasAuthentication::class,
        HasManagerName::class,
    ]);
});

it('is immutable: every mutator returns a configured copy', function () {
    $blank = Cluster::make();

    $configured = $blank->url('https://k8s.example')
        ->withToken('tok')
        ->withManagerName('app')
        ->withDefaultNamespace('apps');

    expect($configured)->not->toBe($blank)
        ->and($blank->getUrl())->toBe('')
        ->and($blank->getToken())->toBeNull()
        ->and($blank->getManagerName())->toBeNull()
        ->and($blank->defaultNamespace())->toBe('default')
        ->and($configured->getUrl())->toBe('https://k8s.example')
        ->and($configured->getToken())->toBe('tok')
        ->and($configured->getManagerName())->toBe('app')
        ->and($configured->defaultNamespace())->toBe('apps');
});

it('exposes strongly typed, cluster-bound resource accessors', function () {
    $cluster = Cluster::make()->withManagerName('typed');

    expect($cluster->pods())->toBeInstanceOf(Pod::class)
        ->and($cluster->pods()->getCluster())->toBe($cluster)
        ->and($cluster->deployments())->toBeInstanceOf(Deployment::class)
        ->and($cluster->statefulSets())->toBeInstanceOf(StatefulSet::class);
});

it('registers the traefik tls and transport accessors from config', function () {
    $cluster = Cluster::make()->withManagerName('traefik');

    expect($cluster->traefikTlsStores())->toBeInstanceOf(TraefikTlsStore::class)
        ->and($cluster->traefikTlsStores()->getCluster())->toBe($cluster)
        ->and($cluster->traefikServersTransports())->toBeInstanceOf(TraefikServersTransport::class)
        ->and($cluster->traefikTlsOptions())->toBeInstanceOf(TraefikTlsOption::class);
});

it('resolves an arbitrary resource class with the cluster default namespace', function () {
    $cluster = Cluster::make()->withDefaultNamespace('apps');

    expect($cluster->resource(Pod::class))
        ->toBeInstanceOf(Pod::class)
        ->getCluster()->toBe($cluster)
        ->getNamespace()->toBe('apps');
});

it('resolves a resource registered in config by name', function () {
    config()->set('kubernetes.resources.customThings', Pod::class);

    $cluster = Cluster::make();

    expect($cluster->hasResource('customThings'))->toBeTrue()
        ->and($cluster->hasResource('nope'))->toBeFalse()
        ->and($cluster->customThings())->toBeInstanceOf(Pod::class);
});

it('throws for an unknown method', function () {
    Cluster::make()->notAResource();
})->throws(BadMethodCallException::class, 'Cluster::notAResource does not exist.');

it('applies a kubeconfig to a copy of the client', function () {
    $config = new KubeConfig(
        server: 'https://api.test:6443',
        token: 'tok',
        clientCertificatePath: '/c.crt',
        clientKeyPath: '/c.key',
        certificateAuthorityPath: '/ca.crt',
        verify: false,
    );

    $blank = new Cluster;
    $cluster = $blank->applyConfig($config);

    expect($cluster->getUrl())->toBe('https://api.test:6443')
        ->and($cluster->getToken())->toBe('tok')
        ->and($cluster->getPathToCertificate())->toBe('/c.crt')
        ->and($cluster->getPathToPrivateKey())->toBe('/c.key')
        ->and($cluster->getPathToCaCertificate())->toBe('/ca.crt')
        ->and($cluster->shouldVerify())->toBeFalse()
        ->and($blank->getUrl())->toBe('');
});

it('sends a raw request through the transport', function () {
    Http::fake(['https://k8s.example/healthz?verbose=1' => Http::response('ok')]);

    $response = Cluster::make()->url('https://k8s.example')->request('GET', '/healthz', ['verbose' => 1]);

    expect($response->body())->toBe('ok');
});

it('sends a path without a trailing ? when there is no query', function () {
    Http::fake(['*' => Http::response('ok')]);

    Cluster::make()->url('https://k8s.example')->request('GET', '/livez');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://k8s.example/livez');
});

it('turns a failed response into a KubernetesException', function () {
    Http::fake(['*' => Http::response(['message' => 'forbidden: nope'], 403)]);

    Cluster::make()->url('https://k8s.example')->request('GET', '/api/v1/secrets');
})->throws(KubernetesException::class, 'forbidden: nope');

it('refuses to send without a url', function () {
    Cluster::make()->request('GET', '/version');
})->throws(ClusterConfigurationException::class, 'No cluster URL configured for this ad-hoc cluster.');

it('refuses to send from a resource bound to no cluster', function () {
    Pod::make()->setName('api')->find();
})->throws(ClusterConfigurationException::class, 'is not bound to a cluster');

it('lets a rate-limit exhaustion escape ping', function () {
    config()->set('kubernetes.rate_limits.max_wait', 0);
    config()->set('kubernetes.rate_limits.max_attempts', 1);
    Http::fake(['*' => Http::response(['gitVersion' => 'v1'])]);

    $cluster = Cluster::make()->url('https://busy.example');

    expect($cluster->ping())->toBeTrue()
        ->and(fn () => $cluster->ping())->toThrow(RateLimitExceededException::class);
});

it('keeps Resource as the base of every accessor', function () {
    expect(Cluster::make()->nodes())->toBeInstanceOf(Resource::class);
});
