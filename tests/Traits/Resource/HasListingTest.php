<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Pod;

beforeEach(function () {
    $this->pods = Pod::make()->setNamespace('production')->setCluster(
        Kubernetes::make()->url('https://localhost')->withToken('secret')->setManagerName('Pest Tests')
    );

    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['items' => []])]);
});

it('builds an equality label selector', function () {
    $this->pods->whereLabel('app', 'checkout')->get();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'labelSelector=app=checkout'));
});

it('builds in and notin and existence label selectors', function () {
    $this->pods
        ->whereLabelIn('tier', ['web', 'api'])
        ->whereLabelNotIn('env', ['dev'])
        ->whereLabelExists('team')
        ->whereLabelMissing('legacy')
        ->whereLabelNot('canary', 'true')
        ->get();

    Http::assertSent(function (Request $r): bool {
        $selector = urldecode($r->url());

        return str_contains($selector, 'tier in (web,api)')
            && str_contains($selector, 'env notin (dev)')
            && str_contains($selector, 'team')
            && str_contains($selector, '!legacy')
            && str_contains($selector, 'canary!=true');
    });
});

it('builds field selectors', function () {
    $this->pods->whereField('status.phase', 'Running')->whereFieldNot('spec.nodeName', 'node-1')->get();

    Http::assertSent(function (Request $r): bool {
        $url = urldecode($r->url());

        return str_contains($url, 'status.phase=Running') && str_contains($url, 'spec.nodeName!=node-1');
    });
});

it('applies limit and continue pagination params', function () {
    $this->pods->limit(50)->continueFrom('token-abc')->get();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'limit=50') && str_contains($r->url(), 'continue=token-abc'));
});

it('lists across all namespaces by dropping the namespace path segment', function () {
    $this->pods->allNamespaces()->get();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/v1/pods') && ! str_contains($r->url(), '/namespaces/'));
});

it('ignores empty continue tokens', function () {
    expect($this->pods->continueFrom('')->get())->toBeEmpty();

    Http::assertSent(fn (Request $r) => ! str_contains($r->url(), 'continue='));
});
