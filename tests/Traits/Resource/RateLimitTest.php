<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use RoundlyConsulting\HttpClientRateLimits\Facades\RateLimits;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Exceptions\RateLimitExceededException;
use RoundlyConsulting\KubernetesApi\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;

function clusterResource(string $manager = 'Pest Tests', string $url = 'https://localhost'): Deployment
{
    return Deployment::make()
        ->setNamespace('production')
        ->setCluster(
            Kubernetes::make()->url($url)->withToken('secret')->setManagerName($manager)
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
    $fake->assertAllowed('k8s:app:Pest Tests');
});

it('defers a request once the per-cluster budget is exhausted', function () {
    config()->set('kubernetes.rate_limits.max_attempts', 1);
    $fake = RateLimits::fake();
    okListing();

    clusterResource()->get();
    clusterResource()->get();

    $fake->assertDeferred('k8s:app:Pest Tests');
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
            'Rate limit for cluster [Pest Tests] exceeded.',
        );
});

it('exposes the cluster and wait window on the fail-fast exception', function () {
    config()->set('kubernetes.rate_limits.max_attempts', 1);
    config()->set('kubernetes.rate_limits.max_wait', 0);
    RateLimits::fake();
    okListing();

    clusterResource()->get();

    try {
        clusterResource()->get();
    } catch (RateLimitExceededException $e) {
        expect($e->cluster)->toBe('Pest Tests')
            ->and($e->availableInSeconds)->toBeGreaterThan(0);

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

    $fake->assertDeferred('k8s:app:Pest Tests');
});

it('keeps a separate budget per cluster', function () {
    config()->set('kubernetes.rate_limits.max_attempts', 1);
    $fake = RateLimits::fake();
    okListing();

    clusterResource('Alpha', 'https://alpha.test')->get();
    clusterResource('Beta', 'https://beta.test')->get();

    $fake->assertAllowed('k8s:app:Alpha')
        ->assertAllowed('k8s:app:Beta')
        ->assertNothingDeferred();
});

it('falls back to the request host when no manager name is set', function () {
    $fake = RateLimits::fake();
    okListing();

    Deployment::make()
        ->setNamespace('production')
        ->setCluster(Kubernetes::make()->url('https://cluster.example.com')->withToken('secret'))
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
    $fake->assertAllowed('k8s:app:Pest Tests')
        ->assertNothingDeferred();
});
