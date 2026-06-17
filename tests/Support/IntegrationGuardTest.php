<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Exceptions\IntegrationGuardException;
use RoundlyConsulting\KubernetesApi\Support\IntegrationGuard;

it('is disabled unless the opt-in flag is set', function () {
    expect((new IntegrationGuard(null, 'orbstack', 'https://127.0.0.1:26443'))->enabled())->toBeFalse()
        ->and((new IntegrationGuard('0', 'orbstack', 'https://127.0.0.1:26443'))->enabled())->toBeFalse()
        ->and((new IntegrationGuard('1', 'orbstack', 'https://127.0.0.1:26443'))->enabled())->toBeTrue()
        ->and((new IntegrationGuard('true', 'orbstack', 'https://127.0.0.1:26443'))->enabled())->toBeTrue();
});

it('permits a local orbstack loopback apiserver', function () {
    (new IntegrationGuard('1', 'orbstack', 'https://127.0.0.1:26443'))->assertSafe();
    (new IntegrationGuard('1', 'orbstack', 'https://localhost:26443'))->assertSafe();
    (new IntegrationGuard('1', 'orbstack', 'https://k8s.orb.local:443'))->assertSafe();

    expect(true)->toBeTrue();
});

it('refuses the production cm-aks context', function () {
    (new IntegrationGuard('1', 'cm-aks', 'https://cm-aks.hcp.westeurope.azmk8s.io:443'))->assertSafe();
})->throws(IntegrationGuardException::class, "context 'cm-aks'");

it('refuses any non-orbstack aks context', function () {
    (new IntegrationGuard('1', 'wonder-aks', 'https://127.0.0.1:26443'))->assertSafe();
})->throws(IntegrationGuardException::class, "context 'wonder-aks'");

it('refuses an orbstack context pointed at a non-local apiserver', function () {
    (new IntegrationGuard('1', 'orbstack', 'https://prod.example.com:6443'))->assertSafe();
})->throws(IntegrationGuardException::class, 'apiserver host must be loopback');

it('treats a malformed server url as non-local', function () {
    expect((new IntegrationGuard('1', 'orbstack', 'not a url'))->hostIsLocal())->toBeFalse();
});
