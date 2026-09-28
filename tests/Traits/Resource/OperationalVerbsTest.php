<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubernetesPatch;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\PodLogOptions;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\ResourcePage;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\Scale;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\WatchEvent;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;
use RoundlyConsulting\KubernetesApi\Resources\Pod;

beforeEach(function () {
    $this->cluster = Cluster::make()->url('https://localhost')->withToken('secret')->withManagerName('Pest Tests');

    $this->deployment = Deployment::make()->setNamespace('production')->setName('checkout')->setCluster($this->cluster);

    Http::preventStrayRequests();
});

it('returns a resource page with continue and remaining item count', function () {
    Http::fake(['*' => Http::response([
        'items' => [['metadata' => ['name' => 'a']]],
        'metadata' => ['continue' => 'next-token', 'remainingItemCount' => 3],
    ])]);

    $page = $this->deployment->getPage();

    expect($page)
        ->toBeInstanceOf(ResourcePage::class)
        ->continue->toBe('next-token')
        ->remainingItemCount->toBe(3)
        ->and($page->hasMore())->toBeTrue()
        ->and($page->items)->toHaveCount(1);
});

it('treats a missing continue token as the last page', function () {
    Http::fake(['*' => Http::response(['items' => [], 'metadata' => []])]);

    $page = $this->deployment->getPage();

    expect($page->continue)->toBeNull()
        ->and($page->remainingItemCount)->toBeNull()
        ->and($page->hasMore())->toBeFalse();
});

it('lazily follows continue tokens across pages', function () {
    Http::fake(['*' => Http::sequence()
        ->push(['items' => [['metadata' => ['name' => 'a']]], 'metadata' => ['continue' => 't2']])
        ->push(['items' => [['metadata' => ['name' => 'b']]], 'metadata' => ['continue' => '']]),
    ]);

    $names = [];
    $this->deployment->each(function ($item) use (&$names): void {
        $names[] = $item->getName();
    });

    expect($names)->toBe(['a', 'b']);
});

it('patches a resource with the right content type and body', function () {
    Http::fake(['*' => Http::response(['metadata' => ['name' => 'checkout']])]);

    $this->deployment->patch(KubernetesPatch::merge(['spec' => ['paused' => true]]));

    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
        && $r->hasHeader('Content-Type', 'application/merge-patch+json')
        && $r->body() === '{"spec":{"paused":true}}'
        && $r->url() === 'https://localhost/apis/apps/v1/namespaces/production/deployments/checkout?pretty=1&fieldManager=Pest%20Tests');
});

it('appends dryRun=All when dry run is enabled', function () {
    Http::fake(['*' => Http::response(['metadata' => ['name' => 'checkout']])]);

    $this->deployment->dryRun()->create();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'dryRun=All'));
});

it('scales via the scale subresource using a merge patch', function () {
    Http::fake(['*' => Http::response([
        'kind' => 'Scale',
        'apiVersion' => 'autoscaling/v1',
        'metadata' => ['name' => 'checkout', 'namespace' => 'production', 'resourceVersion' => '42'],
        'spec' => ['replicas' => 5],
        'status' => ['replicas' => 3, 'selector' => 'app=checkout'],
    ])]);

    $scale = $this->deployment->scale(5);

    expect($scale)->toEqual(new Scale(
        name: 'checkout',
        namespace: 'production',
        replicas: 5,
        currentReplicas: 3,
        selector: 'app=checkout',
        resourceVersion: '42',
    ))->and($this->deployment->getKind())->toBe('Deployment');

    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
        && $r->hasHeader('Content-Type', 'application/merge-patch+json')
        && $r->url() === 'https://localhost/apis/apps/v1/namespaces/production/deployments/checkout/scale?pretty=1&fieldManager=Pest%20Tests'
        && $r->body() === '{"spec":{"replicas":5}}');
});

it('triggers a rollout restart by patching the restartedAt annotation', function () {
    Carbon::setTestNow('2026-06-17T20:00:00+00:00');

    Http::fake(['*' => Http::response(['metadata' => ['name' => 'checkout']])]);

    $this->deployment->rolloutRestart();

    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
        && $r->hasHeader('Content-Type', 'application/strategic-merge-patch+json')
        && str_contains($r->body(), 'kubectl.kubernetes.io/restartedAt')
        && str_contains($r->body(), '2026-06-17T20:00:00'));

    Carbon::setTestNow();
});

it('reports whether a resource is missing on the cluster', function () {
    Http::fake(['*' => Http::response(['message' => 'not found'], 404)]);

    expect($this->deployment->missingOnCluster())->toBeTrue();
});

it('fetches pod logs from the log subresource', function () {
    Http::fake(['*' => Http::response("line-1\nline-2\n")]);

    $pod = Pod::make()->setNamespace('production')->setName('api')->setCluster($this->cluster);

    expect($pod->logs())->toBe("line-1\nline-2\n");

    Http::assertSent(fn (Request $r) => $r->method() === 'GET'
        && $r->url() === 'https://localhost/api/v1/namespaces/production/pods/api/log');
});

it('passes pod log options as query parameters', function () {
    Http::fake(['*' => Http::response('logs')]);

    $pod = Pod::make()->setNamespace('production')->setName('api')->setCluster($this->cluster);

    $pod->logs(new PodLogOptions(
        container: 'app', tailLines: 10, sinceSeconds: 60, timestamps: true, previous: true, limitBytes: 1024,
    ));

    Http::assertSent(function (Request $r): bool {
        $url = urldecode($r->url());

        return str_contains($url, 'container=app')
            && str_contains($url, 'tailLines=10')
            && str_contains($url, 'sinceSeconds=60')
            && str_contains($url, 'timestamps=true')
            && str_contains($url, 'previous=true')
            && str_contains($url, 'limitBytes=1024');
    });
});

it('decodes a watch stream into watch events and skips blank lines', function () {
    $stream = json_encode(['type' => 'ADDED', 'object' => ['metadata' => ['name' => 'a']]])."\n"
        ."\n"
        .json_encode(['type' => 'DELETED', 'object' => ['metadata' => ['name' => 'a']]])."\n";

    Http::fake(['*' => Http::response($stream)]);

    $events = [];
    $this->deployment->watch(function (WatchEvent $event) use (&$events): void {
        $events[] = $event;
    });

    expect($events)->toHaveCount(2)
        ->and($events[0]->isAdded())->toBeTrue()
        ->and($events[1]->isDeleted())->toBeTrue();
});

it('sends client certificate, key and ca verification options', function () {
    $cluster = Cluster::make()
        ->url('https://localhost')
        ->withCertificate('/tmp/client.crt')
        ->withPrivateKey('/tmp/client.key')
        ->withCaCertificate('/tmp/ca.crt')
        ->withManagerName('Pest Tests');

    $deployment = Deployment::make()->setNamespace('production')->setName('checkout')->setCluster($cluster);

    Http::fake(['*' => Http::response(['metadata' => ['name' => 'checkout']])]);

    $deployment->find();

    Http::assertSent(fn (Request $r) => $r->url() === 'https://localhost/apis/apps/v1/namespaces/production/deployments/checkout?pretty=1');
});
