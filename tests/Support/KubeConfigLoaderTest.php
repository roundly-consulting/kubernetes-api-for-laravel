<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Exceptions\KubeConfigException;
use RoundlyConsulting\KubernetesApi\Support\KubeConfigLoader;
use RoundlyConsulting\KubernetesApi\Support\TemporaryPemFiles;

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

it('resolves relative certificate paths against the kubeconfig directory, like kubectl', function () {
    $dir = sys_get_temp_dir().'/kubecfg-dir-'.bin2hex(random_bytes(4));
    mkdir($dir.'/certs', 0777, true);
    $path = $dir.'/config';

    file_put_contents($path, <<<'YAML'
        apiVersion: v1
        current-context: a
        clusters:
          - name: c
            cluster:
              server: https://x
              certificate-authority: certs/ca.crt
        users:
          - name: u
            user:
              client-certificate: ./certs/client.crt
              client-key: /etc/k8s/client.key
        contexts:
          - name: a
            context:
              cluster: c
              user: u
        YAML);

    try {
        $config = (new KubeConfigLoader)->load($path);

        expect($config->certificateAuthorityPath)->toBe($dir.'/certs/ca.crt')
            ->and($config->clientCertificatePath)->toBe($dir.'/certs/client.crt')
            ->and($config->clientKeyPath)->toBe('/etc/k8s/client.key');
    } finally {
        @unlink($path);
        @rmdir($dir.'/certs');
        @rmdir($dir);
    }
});

it('resolves relative certificate paths for a kubeconfig given by a relative path', function () {
    $dir = sys_get_temp_dir().'/kubecfg-rel-'.bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/config', <<<'YAML'
        apiVersion: v1
        current-context: a
        clusters:
          - name: c
            cluster:
              server: https://x
              certificate-authority: ca.crt
        users:
          - name: u
            user:
              client-key: C:\keys\client.key
        contexts:
          - name: a
            context:
              cluster: c
              user: u
        YAML);

    $cwd = (string) getcwd();
    chdir(dirname($dir));

    try {
        $config = (new KubeConfigLoader)->load(basename($dir).'/config');

        expect($config->certificateAuthorityPath)->toBe(getcwd().'/'.basename($dir).'/ca.crt')
            ->and($config->clientKeyPath)->toBe('C:\keys\client.key');
    } finally {
        chdir($cwd);
        @unlink($dir.'/config');
        @rmdir($dir);
    }
});

function inlineKeyKubeConfig(string $key = 'CLIENT-KEY'): string
{
    $key = base64_encode($key);

    return writeKubeConfig(<<<YAML
        apiVersion: v1
        current-context: a
        clusters:
          - name: c
            cluster:
              server: https://x
        users:
          - name: u
            user:
              client-certificate-data: Q0VSVA==
              client-key-data: {$key}
        contexts:
          - name: a
            context:
              cluster: c
              user: u
        YAML);
}

it('reuses one private temp file per inline PEM instead of a new one per load', function () {
    $path = inlineKeyKubeConfig();

    try {
        $first = (new KubeConfigLoader)->load($path);
        $second = (new KubeConfigLoader)->load($path);
        $other = (new KubeConfigLoader)->load($otherPath = inlineKeyKubeConfig('OTHER-KEY'));

        expect($second->clientKeyPath)->toBe($first->clientKeyPath)
            ->and($other->clientKeyPath)->not->toBe($first->clientKeyPath)
            ->and(file_get_contents((string) $other->clientKeyPath))->toBe('OTHER-KEY')
            ->and(fileperms((string) $first->clientKeyPath) & 0777)->toBe(0600);
    } finally {
        @unlink($path);
        @unlink($otherPath ?? '');
    }
});

it('removes the inline PEM temp files when the process exits', function () {
    $path = inlineKeyKubeConfig('EXIT-KEY');
    $script = 'require '.var_export(dirname(__DIR__, 2).'/vendor/autoload.php', true).';'
        .'$c = (new RoundlyConsulting\KubernetesApi\Support\KubeConfigLoader)->load($argv[1]);'
        .'echo $c->clientKeyPath, "\n", is_file($c->clientKeyPath) ? "present" : "missing";';

    try {
        exec(implode(' ', array_map('escapeshellarg', [PHP_BINARY, '-r', $script, $path])), $output, $exit);

        expect($exit)->toBe(0)
            ->and($output[1] ?? null)->toBe('present')
            ->and(is_file($output[0]))->toBeFalse();
    } finally {
        @unlink($path);
    }
});

it('rewrites a temp PEM file that was removed and removes them all on demand', function () {
    $path = inlineKeyKubeConfig('REMOVED-KEY');

    try {
        $key = (string) (new KubeConfigLoader)->load($path)->clientKeyPath;
        unlink($key);

        $again = (string) (new KubeConfigLoader)->load($path)->clientKeyPath;

        expect(file_get_contents($again))->toBe('REMOVED-KEY');

        TemporaryPemFiles::removeAll();

        expect(is_file($again))->toBeFalse();
    } finally {
        @unlink($path);
    }
});

function kubeConfigWithInsecureSkip(string $value): string
{
    return writeKubeConfig(<<<YAML
        apiVersion: v1
        current-context: local
        clusters:
          - name: local
            cluster:
              server: https://localhost:6443
              insecure-skip-tls-verify: {$value}
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
}

it('reads insecure-skip-tls-verify as a boolean, never by truthiness (strict config)', function (string $value, bool $verifies) {
    // `(bool) "false"` is true: a quoted YAML "false" used to switch TLS verification OFF.
    $path = kubeConfigWithInsecureSkip($value);

    expect((new KubeConfigLoader)->load($path)->verify)->toBe($verifies);

    @unlink($path);
})->with([
    'quoted false' => ['"false"', true],
    'quoted no' => ["'no'", true],
    'quoted zero' => ['"0"', true],
    'bare false' => ['false', true],
    'null' => ['null', true],
    'quoted true' => ['"true"', false],
    'bare true' => ['true', false],
    'yes' => ['yes', false],
]);

it('refuses an unreadable insecure-skip-tls-verify instead of guessing (strict config)', function (string $value) {
    $path = kubeConfigWithInsecureSkip($value);

    try {
        expect(fn () => (new KubeConfigLoader)->load($path))->toThrow(
            KubeConfigException::class,
            "Cluster 'local' has an invalid insecure-skip-tls-verify value",
        );
    } finally {
        @unlink($path);
    }
})->with([
    'word' => ['"disabled"'],
    'typo' => ['ture'],
    'number' => ['2'],
    'list' => ['[true]'],
]);
