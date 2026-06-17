<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Exceptions\KubeConfigException;
use RoundlyConsulting\KubernetesApi\Support\InClusterConfigLoader;

afterEach(function () {
    putenv('KUBERNETES_SERVICE_HOST');
    putenv('KUBERNETES_SERVICE_PORT');
});

it('builds config from mounted service account credentials', function () {
    putenv('KUBERNETES_SERVICE_HOST=10.0.0.1');
    putenv('KUBERNETES_SERVICE_PORT=443');

    $tokenPath = tempnam(sys_get_temp_dir(), 'sa-token-');
    file_put_contents($tokenPath, "sa-token\n");

    $caPath = tempnam(sys_get_temp_dir(), 'sa-ca-');
    file_put_contents($caPath, 'CA');

    $config = (new InClusterConfigLoader($tokenPath, $caPath))->load();

    expect($config->server)->toBe('https://10.0.0.1:443')
        ->and($config->token)->toBe('sa-token')
        ->and($config->certificateAuthorityPath)->toBe($caPath)
        ->and($config->verify)->toBeTrue();

    @unlink($tokenPath);
    @unlink($caPath);
});

it('throws when not running in-cluster', function () {
    (new InClusterConfigLoader)->load();
})->throws(KubeConfigException::class, 'Not running in-cluster');

it('throws when the service account token file is missing', function () {
    putenv('KUBERNETES_SERVICE_HOST=10.0.0.1');
    putenv('KUBERNETES_SERVICE_PORT=443');

    (new InClusterConfigLoader('/no/such/token'))->load();
})->throws(KubeConfigException::class, 'token not found');

it('throws when the token is empty', function () {
    putenv('KUBERNETES_SERVICE_HOST=10.0.0.1');
    putenv('KUBERNETES_SERVICE_PORT=443');

    $tokenPath = tempnam(sys_get_temp_dir(), 'sa-token-');
    file_put_contents($tokenPath, '  ');

    try {
        (new InClusterConfigLoader($tokenPath))->load();
    } finally {
        @unlink($tokenPath);
    }
})->throws(KubeConfigException::class, 'token is empty');
