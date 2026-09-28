<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Exceptions\NamespaceScopeException;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Pod;

/*
 * `Kubernetes::namespace('prod')` is a security boundary, not a default: everything it
 * hands out is pinned to `prod` and refuses — loudly — to be pointed anywhere else.
 */

beforeEach(function (): void {
    Http::preventStrayRequests();
    config()->set('kubernetes.clusters.default.url', 'https://k8s.example');
});

it('pins namespaced resources to the scope', function (): void {
    $pods = Kubernetes::namespace('prod')->pods();

    expect($pods->getNamespace())->toBe('prod')
        ->and($pods->namespaceScope())->toBe('prod')
        ->and($pods->getAttribute('metadata.namespace'))->toBe('prod')
        ->and(Kubernetes::namespace('prod')->namespaceScope())->toBe('prod')
        ->and(Kubernetes::cluster()->namespaceScope())->toBeNull();
});

it('refuses a resource pointed at another namespace', function (): void {
    Kubernetes::namespace('prod')->pods()->setNamespace('kube-system');
})->throws(NamespaceScopeException::class, "scoped to namespace 'prod' and refuses namespace 'kube-system'");

it('refuses another default namespace on a scoped resource', function (): void {
    Kubernetes::namespace('prod')->pods()->setDefaultNamespace('kube-system');
})->throws(NamespaceScopeException::class);

it('refuses to list across all namespaces', function (): void {
    Kubernetes::namespace('prod')->pods()->allNamespaces();
})->throws(NamespaceScopeException::class, 'refuses to list across all namespaces');

it('refuses to drop the namespace from a namespaced resource', function (): void {
    Kubernetes::namespace('prod')->pods()->ignoreNamespace();
})->throws(NamespaceScopeException::class, 'refuses to drop the namespace');

it('refuses to re-scope a scoped cluster', function (): void {
    Kubernetes::namespace('prod')->namespace('staging');
})->throws(NamespaceScopeException::class);

it('refuses another default namespace on a scoped cluster', function (): void {
    Kubernetes::namespace('prod')->withDefaultNamespace('staging');
})->throws(NamespaceScopeException::class);

it('refuses to bind a scoped resource to a cluster scoped elsewhere', function (): void {
    Pod::make()->setCluster(Kubernetes::namespace('prod'))->setCluster(Kubernetes::namespace('staging'));
})->throws(NamespaceScopeException::class);

it('allows re-affirming the same scope', function (): void {
    $cluster = Kubernetes::namespace('prod')->namespace('prod')->withDefaultNamespace('prod');

    expect($cluster->pods()->setNamespace('prod')->allNamespaces(false)->getNamespace())->toBe('prod');
});

it('pins a standalone resource bound to a scoped cluster', function (): void {
    $pod = Pod::make()->setCluster(Kubernetes::namespace('prod'));

    expect($pod->getNamespace())->toBe('prod');
});

it('sends every request to the scoped namespace even if the payload says otherwise', function (): void {
    Http::fake(['*' => Http::response(['metadata' => ['name' => 'api']])]);

    $pod = Kubernetes::namespace('prod')->pods()->setName('api');
    $pod->setAttribute('metadata.namespace', 'kube-system');

    $pod->find();

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://k8s.example/api/v1/namespaces/prod/pods/api'));
});

it('keeps listed items pinned to the scope', function (): void {
    Http::fake(['*' => Http::response(['items' => [['metadata' => ['name' => 'api', 'namespace' => 'prod']]]])]);

    $pod = Kubernetes::namespace('prod')->pods()->get()->first();

    expect($pod->namespaceScope())->toBe('prod')
        ->and(fn () => $pod->setNamespace('kube-system'))->toThrow(NamespaceScopeException::class);
});

it('leaves cluster-scoped resources cluster-wide', function (): void {
    Http::fake(['*' => Http::response(['items' => []])]);

    Kubernetes::namespace('prod')->nodes()->get();

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://k8s.example/api/v1/nodes?'));
});

it('exposes the scope on the exception', function (): void {
    try {
        Kubernetes::namespace('prod')->pods()->setNamespace('other');
    } catch (NamespaceScopeException $e) {
        expect($e->scope)->toBe('prod');

        return;
    }

    $this->fail('No NamespaceScopeException was thrown.');
});
