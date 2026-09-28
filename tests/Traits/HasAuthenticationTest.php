<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\HasAuthentication;

it('stores authentication details on a copy', function () {
    $original = new class
    {
        use HasAuthentication;
    };

    $instance = $original->withToken('ABCD')
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

    expect($instance->withSslVerification())->shouldVerify()->toBeTrue()
        ->and($instance->shouldVerify())->toBeFalse();
});

it('never mutates the receiver', function () {
    $original = new class
    {
        use HasAuthentication;
    };

    $original->withToken('ABCD');
    $original->withCertificate('certificate.pem');
    $original->withPrivateKey('private.key');
    $original->withCaCertificate('ca.pem');
    $original->withoutSslVerification();

    expect($original)
        ->hasToken()->toBeFalse()
        ->hasPathToCertificate()->toBeFalse()
        ->hasPathToPrivateKey()->toBeFalse()
        ->hasPathToCaCertificate()->toBeFalse()
        ->shouldVerify()->toBeTrue();
});
