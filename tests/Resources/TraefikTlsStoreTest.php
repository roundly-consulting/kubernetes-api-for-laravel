<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\TraefikTlsStore;

it('extends resource class', function () {
    expect(TraefikTlsStore::class)->toUse(Resource::class);
});

it('has correct kind', function () {
    expect(TraefikTlsStore::make()->getKind())->toBe('TLSStore');
});

it('has correct version', function () {
    expect(TraefikTlsStore::make()->getVersion())->toBe('traefik.io/v1alpha1');
});

it('uses namespaces', function () {
    expect(TraefikTlsStore::make()->usesNamespaces())->toBeTrue();
});

it('locks the rest plural to tlsstores', function () {
    // Verified against the real CRD `tlsstores.traefik.io`.
    expect(TraefikTlsStore::make()->getPluralKind())->toBe('tlsstores');
});

it('honours the configured traefik api group for back-compat', function () {
    config()->set('kubernetes.traefik.group', 'traefik.containo.us/v1alpha1');

    expect(TraefikTlsStore::make()->getVersion())->toBe('traefik.containo.us/v1alpha1');
});

it('sets the default certificate from a secret name', function () {
    $store = TraefikTlsStore::make()->setName('default')->setDefaultCertificate('wildcard-tls');

    expect($store)
        ->toBeInstanceOf(TraefikTlsStore::class)
        ->getDefaultCertificate()->toBe('wildcard-tls')
        ->toArray()->toBe([
            'apiVersion' => 'traefik.io/v1alpha1',
            'kind' => 'TLSStore',
            'metadata' => [
                'name' => 'default',
            ],
            'spec' => [
                'defaultCertificate' => [
                    'secretName' => 'wildcard-tls',
                ],
            ],
        ]);

    expect(TraefikTlsStore::make()->getDefaultCertificate())->toBeNull();
});
