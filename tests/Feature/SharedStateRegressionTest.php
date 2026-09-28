<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;

it('never leaks an ad-hoc cluster credential into the facade root', function (): void {
    Kubernetes::url('https://attacker.example')->withToken('stolen-token');

    $cluster = Kubernetes::pods()->getCluster();

    expect($cluster->getToken())->toBeNull()
        ->and($cluster->getUrl())->not->toBe('https://attacker.example');
});

it('returns a new client from every mutator', function (): void {
    $base = Kubernetes::url('https://one.example');
    $authenticated = $base->withToken('secret');

    expect($authenticated)->not->toBe($base)
        ->and($base->getToken())->toBeNull()
        ->and($authenticated->getToken())->toBe('secret');
});
