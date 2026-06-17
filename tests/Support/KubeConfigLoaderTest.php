<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Exceptions\KubeConfigException;
use RoundlyConsulting\KubernetesApi\Support\KubeConfigLoader;

function writeKubeConfig(string $yaml): string
{
    $path = tempnam(sys_get_temp_dir(), 'kubecfg-');
    file_put_contents($path, $yaml);

    return $path;
}

it('resolves the current context with inline certificate data', function () {
    $cert = base64_encode('CLIENT-CERT');
    $key = base64_encode('CLIENT-KEY');
    $ca = base64_encode('CA-CERT');

    $path = writeKubeConfig(<<<YAML
        apiVersion: v1
        current-context: orbstack
        clusters:
          - name: orbstack
            cluster:
              server: https://127.0.0.1:26443
              certificate-authority-data: {$ca}
        users:
          - name: orbstack
            user:
              client-certificate-data: {$cert}
              client-key-data: {$key}
        contexts:
          - name: orbstack
            context:
              cluster: orbstack
              user: orbstack
        YAML);

    $config = (new KubeConfigLoader)->load($path);

    expect($config->server)->toBe('https://127.0.0.1:26443')
        ->and($config->verify)->toBeTrue()
        ->and($config->hasClientCertificate())->toBeTrue()
        ->and(file_get_contents((string) $config->clientCertificatePath))->toBe('CLIENT-CERT')
        ->and(file_get_contents((string) $config->clientKeyPath))->toBe('CLIENT-KEY')
        ->and(file_get_contents((string) $config->certificateAuthorityPath))->toBe('CA-CERT');

    @unlink($path);
});

it('resolves a token-based user and file-path references', function () {
    $path = writeKubeConfig(<<<'YAML'
        apiVersion: v1
        current-context: prod
        clusters:
          - name: prod
            cluster:
              server: https://api.prod:6443
              certificate-authority: /etc/ca.crt
        users:
          - name: prod
            user:
              token: secret-token
        contexts:
          - name: prod
            context:
              cluster: prod
              user: prod
        YAML);

    $config = (new KubeConfigLoader)->load($path, 'prod');

    expect($config->token)->toBe('secret-token')
        ->and($config->certificateAuthorityPath)->toBe('/etc/ca.crt')
        ->and($config->hasClientCertificate())->toBeFalse();

    @unlink($path);
});

it('honours insecure-skip-tls-verify', function () {
    $path = writeKubeConfig(<<<'YAML'
        apiVersion: v1
        current-context: local
        clusters:
          - name: local
            cluster:
              server: https://localhost:6443
              insecure-skip-tls-verify: true
        users:
          - name: local
            user:
              token: t
        contexts:
          - name: local
            context:
              cluster: local
              user: local
        YAML);

    expect((new KubeConfigLoader)->load($path)->verify)->toBeFalse();

    @unlink($path);
});

it('throws when the kubeconfig is missing', function () {
    (new KubeConfigLoader)->load('/no/such/kubeconfig');
})->throws(KubeConfigException::class);

it('resolves the default path from the KUBECONFIG env when no path is given', function () {
    $path = writeKubeConfig(<<<'YAML'
        apiVersion: v1
        current-context: env-ctx
        clusters:
          - name: c
            cluster:
              server: https://from-env:6443
        users:
          - name: u
            user:
              token: t
        contexts:
          - name: env-ctx
            context:
              cluster: c
              user: u
        YAML);

    putenv('KUBECONFIG='.$path.PATH_SEPARATOR.'/another/config');

    try {
        expect((new KubeConfigLoader)->load()->server)->toBe('https://from-env:6443');
    } finally {
        putenv('KUBECONFIG');
        @unlink($path);
    }
});

it('throws when no context is resolvable', function () {
    $path = writeKubeConfig("apiVersion: v1\nclusters: []\nusers: []\ncontexts: []\n");

    (new KubeConfigLoader)->load($path);
})->throws(KubeConfigException::class, 'No context specified');

it('throws when the named context is absent', function () {
    $path = writeKubeConfig(<<<'YAML'
        apiVersion: v1
        current-context: a
        clusters: []
        users: []
        contexts: []
        YAML);

    (new KubeConfigLoader)->load($path, 'missing');
})->throws(KubeConfigException::class);

it('throws when the cluster has no server', function () {
    $path = writeKubeConfig(<<<'YAML'
        apiVersion: v1
        current-context: a
        clusters:
          - name: c
            cluster: {}
        users:
          - name: u
            user:
              token: t
        contexts:
          - name: a
            context:
              cluster: c
              user: u
        YAML);

    (new KubeConfigLoader)->load($path);
})->throws(KubeConfigException::class, 'no server URL');

it('throws on invalid base64 certificate data', function () {
    $path = writeKubeConfig(<<<'YAML'
        apiVersion: v1
        current-context: a
        clusters:
          - name: c
            cluster:
              server: https://x
              certificate-authority-data: "!!!not-base64!!!"
        users:
          - name: u
            user:
              token: t
        contexts:
          - name: a
            context:
              cluster: c
              user: u
        YAML);

    (new KubeConfigLoader)->load($path);
})->throws(KubeConfigException::class);
