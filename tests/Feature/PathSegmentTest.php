<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;
use RoundlyConsulting\KubernetesApi\Resources\Pod;

/*
 * Every value that becomes a URL path segment — the name, the namespace, the plural and
 * the apiVersion — is validated before it reaches the transport, and the name is
 * percent-encoded. Without that a resource name like `../../kube-system/secrets/x`
 * walked a namespace-scoped client straight into another namespace.
 */

beforeEach(function (): void {
    Http::preventStrayRequests();
    config()->set('kubernetes.clusters.default.url', 'https://k8s.example');
});

it('refuses a name that walks a scoped client out of its namespace', function (string $verb): void {
    Http::fake(['*' => Http::response(['kind' => 'Secret', 'metadata' => ['name' => 'x']])]);

    $secret = Kubernetes::namespace('shop')->secrets()->withName('../../kube-system/secrets/admin-token');

    expect(fn () => $secret->{$verb}())->toThrow(InvalidResourceException::class, "'../../kube-system/secrets/admin-token' is not a valid resource name");

    Http::assertNothingSent();
})->with(['find', 'delete', 'update', 'existsOnCluster']);

it('refuses the name on subresources: logs, scale and exec', function (): void {
    Http::fake();
    $pod = Kubernetes::namespace('shop')->pods()->setName('../../kube-system/pods/etcd');

    expect(fn () => $pod->logs())->toThrow(InvalidResourceException::class)
        ->and(fn () => $pod->exec(['id']))->toThrow(InvalidResourceException::class)
        ->and(fn () => Kubernetes::namespace('shop')->deployments()->setName('..')->scale(1))->toThrow(InvalidResourceException::class);

    Http::assertNothingSent();
});

it('refuses names the apiserver could never route', function (string $name): void {
    Http::fake();

    expect(fn () => Kubernetes::pods()->setName($name)->find())->toThrow(InvalidResourceException::class);

    Http::assertNothingSent();
})->with([
    'empty' => [''],
    'dot' => ['.'],
    'dot-dot' => ['..'],
    'slash' => ['a/b'],
    'percent' => ['a%2Fb'],
    'whitespace' => ['a b'],
    'newline' => ["a\nb"],
    'too long' => [str_repeat('a', 254)],
]);

it('requires a name for single-object requests', function (): void {
    Http::fake();

    expect(fn () => Kubernetes::pods()->find())->toThrow(InvalidResourceException::class, 'A resource name is required');
});

it('percent-encodes reserved characters so a name stays one segment', function (): void {
    Http::fake(['*' => Http::response(['kind' => 'ClusterRole'])]);

    Kubernetes::clusterRoles()->setName('system:controller:job?x#y')->find();

    Http::assertSent(fn (Request $request): bool => $request->url()
        === 'https://k8s.example/apis/rbac.authorization.k8s.io/v1/clusterroles/system:controller:job%3Fx%23y?pretty=1');
});

it('refuses an invalid namespace', function (string $namespace): void {
    Http::fake();

    expect(fn () => Kubernetes::pods()->setNamespace($namespace)->setName('api')->find())
        ->toThrow(InvalidResourceException::class, 'is not a valid namespace');

    Http::assertNothingSent();
})->with(['../kube-system', 'kube-system/pods', 'Shop', '', str_repeat('a', 64)]);

it('refuses to scope a cluster to an invalid namespace', function (): void {
    Kubernetes::namespace('../kube-system');
})->throws(InvalidResourceException::class, "'../kube-system' is not a valid namespace");

it('refuses an injected apiVersion or kind', function (): void {
    Http::fake();

    expect(fn () => Deployment::make(['apiVersion' => 'v2/../../api/v1/namespaces/kube-system/secrets/x?'])
        ->setCluster(Kubernetes::namespace('shop'))->setName('web')->find())
        ->toThrow(InvalidResourceException::class, 'is not a valid apiVersion')
        ->and(fn () => Pod::make(['kind' => '../secrets'])->setCluster(Kubernetes::namespace('shop'))->get())
        ->toThrow(InvalidResourceException::class, 'is not a valid resource plural');

    Http::assertNothingSent();
});

it('still reaches ordinary names, namespaces and groups', function (): void {
    Http::fake(['*' => Http::response(['kind' => 'Deployment'])]);

    Kubernetes::namespace('shop-2')->deployments()->setName('web.v1-2')->find();
    Kubernetes::cluster()->traefikIngressRoutes()->setNamespace('edge')->setName('app')->find();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://k8s.example/apis/apps/v1/namespaces/shop-2/deployments/web.v1-2?pretty=1');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://k8s.example/apis/traefik.io/v1alpha1/namespaces/edge/ingressroutes/app?pretty=1');
});
