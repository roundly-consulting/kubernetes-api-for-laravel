<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\HasAuthentication;

it('stores authentication details', function () {
    $instance = new class
    {
        use HasAuthentication;
    };

    $instance->withToken('ABCD')
        ->withoutSslVerification()
        ->withCertificate('certificate.pem')
        ->withCaCertificate('ca.pem')
        ->withPrivateKey('private.key');

    expect($instance)
        ->hasToken()->toBeTrue()
        ->hasPathToCertificate()->toBeTrue()
        ->hasPathToPrivateKey()->toBeTrue()
        ->hasPathToCaCertificate()->toBeTrue()
        ->getToken()->toBe('ABCD')
        ->getPathToCertificate()->toBe('certificate.pem')
        ->getPathToCaCertificate()->toBe('ca.pem')
        ->getPathToPrivateKey()->toBe('private.key')
        ->shouldVerify()->toBeFalse();

    $instance->withSslVerification();

    expect($instance)->shouldVerify()->toBeTrue();
});
