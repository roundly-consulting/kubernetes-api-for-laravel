<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\TraefikTlsOption;

it('extends resource class', function () {
    expect(TraefikTlsOption::class)->toUse(Resource::class);
});

it('has correct kind', function () {
    expect(TraefikTlsOption::make()->getKind())->toBe('TLSOption');
});

it('has correct version', function () {
    expect(TraefikTlsOption::make()->getVersion())->toBe('traefik.io/v1alpha1');
});

it('uses namespaces', function () {
    expect(TraefikTlsOption::make()->usesNamespaces())->toBeTrue();
});

it('locks the rest plural to tlsoptions', function () {
    // Verified against the real CRD `tlsoptions.traefik.io`.
    expect(TraefikTlsOption::make()->getPluralKind())->toBe('tlsoptions');
});

it('honours the configured traefik api group for back-compat', function () {
    config()->set('kubernetes.traefik.group', 'traefik.containo.us/v1alpha1');

    expect(TraefikTlsOption::make()->getVersion())->toBe('traefik.containo.us/v1alpha1');
});

it('configures min/max version and cipher suites', function () {
    $option = TraefikTlsOption::make()->setName('modern')
        ->setMinVersion('VersionTLS12')
        ->setMaxVersion('VersionTLS13')
        ->setCipherSuites(['TLS_AES_256_GCM_SHA384']);

    expect($option)
        ->getMinVersion()->toBe('VersionTLS12')
        ->getMaxVersion()->toBe('VersionTLS13')
        ->getCipherSuites()->toBe(['TLS_AES_256_GCM_SHA384'])
        ->toArray()->toBe([
            'apiVersion' => 'traefik.io/v1alpha1',
            'kind' => 'TLSOption',
            'metadata' => [
                'name' => 'modern',
            ],
            'spec' => [
                'minVersion' => 'VersionTLS12',
                'maxVersion' => 'VersionTLS13',
                'cipherSuites' => ['TLS_AES_256_GCM_SHA384'],
            ],
        ]);
});

it('defaults versions to null and cipher suites to empty', function () {
    $option = TraefikTlsOption::make();

    expect($option)
        ->getMinVersion()->toBeNull()
        ->getMaxVersion()->toBeNull()
        ->getCipherSuites()->toBe([]);
});
