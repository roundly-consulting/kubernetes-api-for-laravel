<?php

declare(strict_types=1);

use Illuminate\Support\Traits\Macroable;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\Exceptions\KubeConfigException;
use RoundlyConsulting\KubernetesApi\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;
use RoundlyConsulting\KubernetesApi\Resources\Pod;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\StatefulSet;
use RoundlyConsulting\KubernetesApi\Traits\HasAuthentication;
use RoundlyConsulting\KubernetesApi\Traits\HasManagerName;
use RoundlyConsulting\KubernetesApi\Traits\HasUrl;

it('uses required traits', function () {
    expect(Kubernetes::class)->toUse([
        Macroable::class,
        HasUrl::class,
        HasAuthentication::class,
        HasManagerName::class,
    ]);
});

it('registers cluster instances as macros', function () {
    $kubernetes = new Kubernetes;
    $kubernetes->registerCluster('main', function (Kubernetes $cluster) {
        $cluster->setManagerName('My Cluster Definition');
    });

    expect(Kubernetes::hasMacro('getMainCluster'))
        ->toBeTrue()
        ->and(Kubernetes::getMainCluster())
        ->toBeInstanceOf(Kubernetes::class)
        ->getManagerName()
        ->toBe('My Cluster Definition');
});

it('resolves cluster definition from cluster method', function () {
    $kubernetes = new Kubernetes;
    $kubernetes->registerCluster('main', function (Kubernetes $cluster) {
        $cluster->setManagerName('My Cluster Definition');
    });

    expect($kubernetes->cluster('main'))
        ->toBeInstanceOf(Kubernetes::class)
        ->getManagerName()
        ->toBe('My Cluster Definition');
});

it('throws exception when no cluster with name has been registered', function () {
    $kubernetes = new Kubernetes;

    $kubernetes->cluster('alfa');
})->throws(BadMethodCallException::class, "No cluster 'alfa' definition found.");

it('registers custom resources using macros', function () {
    class CustomResource extends Resource
    {
        //
    }

    $kubernetes = new Kubernetes;
    $kubernetes->setManagerName('My Manager');
    $kubernetes->registerResource('customResource', CustomResource::class);

    expect($kubernetes->customResource())
        ->toBeInstanceOf(CustomResource::class)
        ->getCluster()->toBe($kubernetes);
});

it('exposes strongly typed, cluster-bound resource accessors', function () {
    $kubernetes = (new Kubernetes)->setManagerName('typed');

    expect($kubernetes->pods())->toBeInstanceOf(Pod::class)
        ->and($kubernetes->pods()->getCluster())->toBe($kubernetes)
        ->and($kubernetes->deployments())->toBeInstanceOf(Deployment::class)
        ->and($kubernetes->statefulSets())->toBeInstanceOf(StatefulSet::class);
});

it('resolves an arbitrary resource class bound to the cluster', function () {
    $kubernetes = new Kubernetes;

    expect($kubernetes->resource(Pod::class))
        ->toBeInstanceOf(Pod::class)
        ->getCluster()->toBe($kubernetes);
});

it('applies a kubeconfig to the client', function () {
    $config = new KubeConfig(
        server: 'https://api.test:6443',
        token: 'tok',
        clientCertificatePath: '/c.crt',
        clientKeyPath: '/c.key',
        certificateAuthorityPath: '/ca.crt',
        verify: false,
    );

    $cluster = (new Kubernetes)->applyConfig($config);

    expect($cluster->getUrl())->toBe('https://api.test:6443')
        ->and($cluster->getToken())->toBe('tok')
        ->and($cluster->getPathToCertificate())->toBe('/c.crt')
        ->and($cluster->getPathToPrivateKey())->toBe('/c.key')
        ->and($cluster->getPathToCaCertificate())->toBe('/ca.crt')
        ->and($cluster->shouldVerify())->toBeFalse();
});

it('builds a verifying client from a kubeconfig file', function () {
    $path = tempnam(sys_get_temp_dir(), 'kc-');
    file_put_contents($path, <<<'YAML'
        apiVersion: v1
        current-context: ctx
        clusters:
          - name: c
            cluster:
              server: https://api:6443
        users:
          - name: u
            user:
              token: from-file
        contexts:
          - name: ctx
            context:
              cluster: c
              user: u
        YAML);

    $cluster = Kubernetes::fromKubeConfig($path);

    expect($cluster->getUrl())->toBe('https://api:6443')
        ->and($cluster->getToken())->toBe('from-file')
        ->and($cluster->shouldVerify())->toBeTrue();

    @unlink($path);
});

it('builds a client from in-cluster credentials when mounted', function () {
    $tokenPath = '/var/run/secrets/kubernetes.io/serviceaccount/token';

    putenv('KUBERNETES_SERVICE_HOST=10.1.2.3');
    putenv('KUBERNETES_SERVICE_PORT=443');

    try {
        $cluster = Kubernetes::inCluster();

        // Only reached when the test host actually has a mounted token.
        expect($cluster->getUrl())->toBe('https://10.1.2.3:443');
    } catch (KubeConfigException $e) {
        expect(is_file($tokenPath))->toBeFalse()
            ->and($e->getMessage())->toContain('token not found');
    } finally {
        putenv('KUBERNETES_SERVICE_HOST');
        putenv('KUBERNETES_SERVICE_PORT');
    }
});
