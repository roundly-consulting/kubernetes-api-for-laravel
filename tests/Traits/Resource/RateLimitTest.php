<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use RoundlyConsulting\HttpClientRateLimits\Facades\RateLimits;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Exceptions\RateLimitExceededException;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;
use RoundlyConsulting\PackageToolkit\Contracts\HasRetryAfter;

function clusterResource(string $manager = 'Pest Tests', string $url = 'https://localhost'): Deployment
{
    return Deployment::make()
        ->setNamespace('production')
        ->setCluster(
            Cluster::make()->url($url)->withToken('secret')->withManagerName($manager)
        );
}

function okListing(): void
{
    Http::fake(['*' => Http::response(['items' => []])]);
}

beforeEach(function () {
    Http::preventStrayRequests();
});

it('paces every cluster request through the limiter and lets it through', function () {
    $fake = RateLimits::fake();
    okListing();

    $result = clusterResource()->get();

    expect($result)->not->toBeNull();
    $fake->assertAllowed('k8s:app:localhost');
});

it('defers a request once the per-cluster budget is exhausted', function () {
    config()->set('kubernetes.rate_limits.max_attempts', 1);
    $fake = RateLimits::fake();
    okListing();

    clusterResource()->get();
    clusterResource()->get();

    $fake->assertDeferred('k8s:app:localhost');
});

it('fails fast with a native exception when the wait exceeds max_wait', function () {
    config()->set('kubernetes.rate_limits.max_attempts', 1);
    config()->set('kubernetes.rate_limits.max_wait', 0);
    RateLimits::fake();
    okListing();

    clusterResource()->get();

    expect(fn () => clusterResource()->get())
        ->toThrow(
            RateLimitExceededException::class,
            'Rate limit for cluster [localhost] exceeded.',
        );
});

it('exposes the cluster and a retry-after hint on the fail-fast exception', function () {
    config()->set('kubernetes.rate_limits.max_attempts', 1);
    config()->set('kubernetes.rate_limits.max_wait', 0);
    RateLimits::fake();
    okListing();

    clusterResource()->get();

    try {
        clusterResource()->get();
    } catch (RateLimitExceededException $e) {
        expect($e)->toBeInstanceOf(HasRetryAfter::class)
            ->and($e->cluster)->toBe('localhost')
            ->and($e->retryAfterSeconds())->toBeGreaterThan(0);

        return;
    }

    $this->fail('Expected a RateLimitExceededException.');
});

it('bypasses the limiter entirely when disabled', function () {
    config()->set('kubernetes.rate_limits.enabled', false);
    config()->set('kubernetes.rate_limits.max_attempts', 1);
    $fake = RateLimits::fake();
    okListing();

    clusterResource()->get();
    clusterResource()->get();

    $fake->assertNothingDeferred();
});

it('reads the apiserver 429 Retry-After so the next request self-throttles', function () {
    $fake = RateLimits::fake();

    Http::fake([
        '*' => Http::response(['message' => 'too many requests'], 429, ['Retry-After' => '2']),
    ]);

    expect(fn () => clusterResource()->get())->toThrow(KubernetesException::class);

    // The 429 recorded a server penalty; the next attempt must be deferred even
    // though the client-side count is nowhere near the budget.
    try {
        clusterResource()->get();
    } catch (KubernetesException) {
        // still a 429 from the fake — we only care that it was deferred first.
    }

    $fake->assertDeferred('k8s:app:localhost');
});

it('keeps a separate budget per cluster', function () {
    config()->set('kubernetes.rate_limits.max_attempts', 1);
    $fake = RateLimits::fake();
    okListing();

    clusterResource('Alpha', 'https://alpha.test')->get();
    clusterResource('Beta', 'https://beta.test')->get();

    $fake->assertAllowed('k8s:app:alpha.test')
        ->assertAllowed('k8s:app:beta.test')
        ->assertNothingDeferred();
});

it('keys the budget by the apiserver host when no manager name is set', function () {
    $fake = RateLimits::fake();
    okListing();

    Deployment::make()
        ->setNamespace('production')
        ->setCluster(Cluster::make()->url('https://cluster.example.com')->withToken('secret'))
        ->get();

    $fake->assertAllowed('k8s:app:cluster.example.com');
});

it('counts a watch as a single hit, not one per event', function () {
    config()->set('kubernetes.rate_limits.max_attempts', 1);
    $fake = RateLimits::fake();

    $body = json_encode(['type' => 'ADDED', 'object' => ['metadata' => ['name' => 'a']]])."\n"
        .json_encode(['type' => 'MODIFIED', 'object' => ['metadata' => ['name' => 'a']]])."\n";

    Http::fake(['*' => Http::response($body)]);

    $seen = 0;
    clusterResource()->watch(function () use (&$seen): void {
        $seen++;
    });

    expect($seen)->toBe(2);
    $fake->assertAllowed('k8s:app:localhost')
        ->assertNothingDeferred();
});

it('keeps separate budgets for clusters that share a manager name', function () {
    config()->set('kubernetes.rate_limits.max_attempts', 1);
    $fake = RateLimits::fake();
    okListing();

    clusterResource('my-app', 'https://prod.example:6443')->get();
    clusterResource('my-app', 'https://staging.example:6443')->get();
    clusterResource('my-app', 'https://rancher.example/k8s/clusters/c-abc')->get();
    clusterResource('my-app', 'https://rancher.example/k8s/clusters/c-def/')->get();

    $fake->assertAllowed('k8s:app:prod.example:6443')
        ->assertAllowed('k8s:app:staging.example:6443')
        ->assertAllowed('k8s:app:rancher.example/k8s/clusters/c-abc')
        ->assertAllowed('k8s:app:rancher.example/k8s/clusters/c-def')
        ->assertNothingDeferred();
});

it('shares one budget between clients of the same apiserver', function () {
    config()->set('kubernetes.rate_limits.max_attempts', 1);
    $fake = RateLimits::fake();
    okListing();

    clusterResource('worker-a', 'https://API.example:443')->get();
    clusterResource('worker-b', 'https://api.example')->get();

    $fake->assertDeferred('k8s:app:api.example');
});

it('names a registered cluster by its name on the fail-fast exception', function () {
    config()->set('kubernetes.rate_limits.max_attempts', 1);
    config()->set('kubernetes.rate_limits.max_wait', 0);
    RateLimits::fake();
    okListing();

    Kubernetes::registerCluster('production', fn (Cluster $cluster): Cluster => $cluster->url('https://prod.example')->withToken('t'));
    Kubernetes::cluster('production')->pods()->get();

    expect(fn () => Kubernetes::cluster('production')->pods()->get())
        ->toThrow(RateLimitExceededException::class, 'Rate limit for cluster [production] exceeded.');
});

it('keys a cluster without a parsable host by a hash of its url', function () {
    $fake = RateLimits::fake();
    Http::fake(['*' => Http::response(['items' => []])]);

    $resource = Deployment::make()->setNamespace('production')->setCluster(Cluster::make()->url('localhost')->withToken('t'));

    try {
        $resource->get();
    } catch (Throwable) {
        // only the key matters
    }

    $fake->assertAllowed('k8s:app:'.substr(hash('sha256', 'localhost'), 0, 12));
});
