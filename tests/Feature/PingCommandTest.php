<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use RoundlyConsulting\HttpClientRateLimits\Facades\RateLimits;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('reports a successful connection to a registered cluster', function () {
    Kubernetes::registerCluster('orbstack', fn (Cluster $cluster): Cluster => $cluster->url('https://127.0.0.1:26443')->withoutSslVerification());

    Http::fake(['*' => Http::response(['gitVersion' => 'v1.34.0'])]);

    $this->artisan('kubernetes:ping orbstack')
        ->expectsOutputToContain('Connected to orbstack — server v1.34.0')
        ->assertSuccessful();
});

it('pings the default cluster from config when no name is given', function () {
    config()->set('kubernetes.clusters.default.url', 'https://10.0.0.1:6443');

    Http::fake(['https://10.0.0.1:6443/version' => Http::response(['gitVersion' => 'v1.33.2'])]);

    $this->artisan('kubernetes:ping')
        ->expectsOutputToContain('Connected to default — server v1.33.2')
        ->assertSuccessful();
});

it('reports an unknown server version', function () {
    Kubernetes::registerCluster('orbstack', fn (Cluster $cluster): Cluster => $cluster->url('https://127.0.0.1:26443'));

    Http::fake(['*' => Http::response([])]);

    $this->artisan('kubernetes:ping orbstack')
        ->expectsOutputToContain('server unknown')
        ->assertSuccessful();
});

it('fails when the cluster is not registered', function () {
    $this->artisan('kubernetes:ping missing')
        ->expectsOutputToContain("No cluster 'missing' definition found.")
        ->assertFailed();
});

it('fails when a cluster definition returns no cluster', function () {
    Kubernetes::registerCluster('broken', fn (Cluster $cluster) => null);

    $this->artisan('kubernetes:ping broken')
        ->expectsOutputToContain("The definition of cluster 'broken' must return")
        ->assertFailed();
});

it('fails when no cluster url is configured', function () {
    $this->artisan('kubernetes:ping')
        ->expectsOutputToContain('No cluster URL configured')
        ->assertFailed();
});

it('fails when the apiserver is unreachable', function () {
    Kubernetes::registerCluster('broken', fn (Cluster $cluster): Cluster => $cluster->url('https://127.0.0.1:1')->withoutSslVerification());

    Http::fake(['*' => Http::response('boom', 500)]);

    $this->artisan('kubernetes:ping broken')
        ->expectsOutputToContain('Could not reach broken')
        ->assertFailed();
});

it('routes the ping probe through the cluster rate limiter', function () {
    Kubernetes::registerCluster('orbstack', fn (Cluster $cluster): Cluster => $cluster->url('https://127.0.0.1:26443')->withoutSslVerification());

    $fake = RateLimits::fake();
    Http::fake(['*' => Http::response(['gitVersion' => 'v1.34.0'])]);

    $this->artisan('kubernetes:ping orbstack')->assertSuccessful();

    $fake->assertAllowed('k8s:app:127.0.0.1');
});

it('does not throttle the ping when rate limiting is disabled', function () {
    config()->set('kubernetes.rate_limits.enabled', false);

    Kubernetes::registerCluster('orbstack', fn (Cluster $cluster): Cluster => $cluster->url('https://127.0.0.1:26443')->withoutSslVerification());

    $fake = RateLimits::fake();
    Http::fake(['*' => Http::response(['gitVersion' => 'v1.34.0'])]);

    $this->artisan('kubernetes:ping orbstack')->assertSuccessful();

    $fake->assertNothingDeferred();
});
