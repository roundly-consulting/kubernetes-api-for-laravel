<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('reports a successful connection to a registered cluster', function () {
    Kubernetes::registerCluster('orbstack', function ($cluster): void {
        $cluster->url('https://127.0.0.1:26443')->withoutSslVerification();
    });

    Http::fake(['*' => Http::response(['gitVersion' => 'v1.34.0'])]);

    $this->artisan('kubernetes:ping orbstack')
        ->expectsOutputToContain('v1.34.0')
        ->assertSuccessful();
});

it('fails when the cluster is not registered', function () {
    $this->artisan('kubernetes:ping missing')
        ->expectsOutputToContain("No cluster 'missing' definition found.")
        ->assertFailed();
});

it('fails when no cluster url is configured', function () {
    $this->artisan('kubernetes:ping')
        ->expectsOutputToContain('No cluster URL configured')
        ->assertFailed();
});

it('fails when the apiserver is unreachable', function () {
    Kubernetes::registerCluster('broken', function ($cluster): void {
        $cluster->url('https://127.0.0.1:1')->withoutSslVerification();
    });

    Http::fake(['*' => Http::response('boom', 500)]);

    $this->artisan('kubernetes:ping broken')
        ->expectsOutputToContain('Could not reach')
        ->assertFailed();
});
