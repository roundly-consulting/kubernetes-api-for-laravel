<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\TraefikServersTransport;

it('extends resource class', function () {
    expect(TraefikServersTransport::class)->toUse(Resource::class);
});

it('has correct kind', function () {
    expect(TraefikServersTransport::make()->getKind())->toBe('ServersTransport');
});

it('has correct version', function () {
    expect(TraefikServersTransport::make()->getVersion())->toBe('traefik.io/v1alpha1');
});

it('uses namespaces', function () {
    expect(TraefikServersTransport::make()->usesNamespaces())->toBeTrue();
});

it('locks the rest plural to serverstransports', function () {
    // Verified against the real CRD `serverstransports.traefik.io`.
    expect(TraefikServersTransport::make()->getPluralKind())->toBe('serverstransports');
});

it('honours the configured traefik api group for back-compat', function () {
    config()->set('kubernetes.traefik.group', 'traefik.containo.us/v1alpha1');

    expect(TraefikServersTransport::make()->getVersion())->toBe('traefik.containo.us/v1alpha1');
});

it('configures server name, insecure skip verify and secret refs', function () {
    $transport = TraefikServersTransport::make()->setName('backend')
        ->setServerName('backend.internal')
        ->insecureSkipVerify()
        ->setRootCAsSecrets(['ca-secret'])
        ->setCertificatesSecrets(['client-cert']);

    expect($transport)
        ->getServerName()->toBe('backend.internal')
        ->getInsecureSkipVerify()->toBeTrue()
        ->getRootCAsSecrets()->toBe(['ca-secret'])
        ->getCertificatesSecrets()->toBe(['client-cert'])
        ->toArray()->toBe([
            'apiVersion' => 'traefik.io/v1alpha1',
            'kind' => 'ServersTransport',
            'metadata' => [
                'name' => 'backend',
            ],
            'spec' => [
                'serverName' => 'backend.internal',
                'insecureSkipVerify' => true,
                'rootCAsSecrets' => ['ca-secret'],
                'certificatesSecrets' => ['client-cert'],
            ],
        ]);
});

it('defaults insecure skip verify to false and secret refs to empty', function () {
    $transport = TraefikServersTransport::make();

    expect($transport)
        ->getServerName()->toBeNull()
        ->getInsecureSkipVerify()->toBeFalse()
        ->getRootCAsSecrets()->toBe([])
        ->getCertificatesSecrets()->toBe([]);
});

it('can disable insecure skip verify explicitly', function () {
    $transport = TraefikServersTransport::make()->insecureSkipVerify(false);

    expect($transport->getInsecureSkipVerify())->toBeFalse();
});
