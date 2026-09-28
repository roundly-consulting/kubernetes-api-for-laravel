<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\Resources\Pod;

beforeEach(function () {
    $this->pods = Pod::make()->setNamespace('production')->setCluster(
        Cluster::make()->url('https://localhost')->withToken('secret')->withManagerName('Pest Tests')
    );

    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['items' => []])]);
});

it('builds an equality label selector', function () {
    $this->pods->whereLabel('app', 'checkout')->get();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'labelSelector=app%3Dcheckout'));
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

it('reports whether label selectors are present', function () {
    expect($this->pods->hasLabelSelectors())->toBeFalse();

    expect($this->pods->whereLabel('app', 'api')->hasLabelSelectors())->toBeTrue();
});

it('encodes selector values so they cannot inject query parameters', function () {
    $this->pods->whereLabel('tenant', 'acme&watch=true&x')->whereField('spec.nodeName', 'n+1')->get();

    Http::assertSent(function (Request $r): bool {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $query);

        return $query === [
            'pretty' => '1',
            'labelSelector' => 'tenant=acme&watch=true&x',
            'fieldSelector' => 'spec.nodeName=n+1',
        ] && str_contains($r->url(), 'labelSelector=tenant%3Dacme%26watch%3Dtrue%26x')
            && str_contains($r->url(), 'fieldSelector=spec.nodeName%3Dn%2B1');
    });
});

it('encodes raw request query values and keeps repeated keys unindexed', function () {
    $this->pods->getCluster()->request('GET', '/api/v1/pods', ['q' => 'a b#c%', 'command' => ['ls', '-la']]);

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/api/v1/pods?q=a%20b%23c%25&command=ls&command=-la'));
});
