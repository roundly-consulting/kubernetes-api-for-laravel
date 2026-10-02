<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Exceptions\WebSocketException;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Tests\Fixtures\LocalServer;

/*
 * `exec()` against a real socket server speaking the apiserver's exec WebSocket. The
 * scenarios are the ones a mocked transport cannot show: bytes that ride in with the
 * handshake, a stream that ends without a status, the URL's scheme and path prefix.
 */

beforeEach(function (): void {
    $this->server = LocalServer::script(__DIR__.'/../Fixtures/servers/exec.php');
    $this->pod = fn (string $url = ''): mixed => Kubernetes::url($this->server->url($url))
        ->withToken('t')
        ->pods()
        ->setNamespace('default')
        ->setName('api');
});

afterEach(function (): void {
    $this->server->stop();
});

it('keeps the frames that arrive together with the handshake', function (): void {
    $result = ($this->pod)()->exec(['fast']);

    expect($result->stdout)->toBe("hi\n")
        ->and($result->exitCode)->toBe(7)
        ->and($result->successful())->toBeFalse();
});

it('reports a completed command and its exit code', function (): void {
    $result = ($this->pod)()->exec(['ok']);

    expect($result->stdout)->toBe("hi\n")
        ->and($result->exitCode)->toBe(0)
        ->and($result->completed())->toBeTrue()
        ->and($result->successful())->toBeTrue();
});

it('reports no exit code, and no success, when the stream ends without a status', function (): void {
    $result = ($this->pod)()->exec(['drop']);

    expect($result->stdout)->toBe("partial output\n")
        ->and($result->exitCode)->toBeNull()
        ->and($result->completed())->toBeFalse()
        ->and($result->successful())->toBeFalse();
});

it('waits through silence by default and gives up only after the configured idle timeout', function (): void {
    expect(($this->pod)()->exec(['quiet'])->exitCode)->toBe(0);

    config()->set('kubernetes.client.stream_timeout', 1);

    $result = ($this->pod)()->exec(['quiet']);

    expect($result->stdout)->toBe('a')
        ->and($result->completed())->toBeFalse();
});

it('reassembles a message fragmented over continuation frames', function (): void {
    expect(($this->pod)()->exec(['big'])->stdout)->toBe(str_repeat('x', 200).str_repeat('y', 200));
});

it('answers a ping with a pong', function (): void {
    expect(($this->pod)()->exec(['ping'])->stdout)->toBe('pong-ok');
});

it('keeps the cluster URL path prefix of a proxied apiserver', function (): void {
    ($this->pod)('/k8s/clusters/c-abc/')->exec(['ok']);

    expect($this->server->requests())->toBe([
        'GET /k8s/clusters/c-abc/api/v1/namespaces/default/pods/api/exec?stdout=true&stderr=true&stdin=false&tty=false&command=ok HTTP/1.1',
        "Host: 127.0.0.1:{$this->server->port}",
    ]);
});

it('throws when the apiserver refuses the upgrade', function (): void {
    ($this->pod)()->exec(['reject']);
})->throws(WebSocketException::class, 'WebSocket upgrade failed: HTTP/1.1 403 Forbidden');

it('throws for a cluster URL it cannot dial', function (string $url): void {
    Kubernetes::url($url)->pods()->setName('api')->exec(['id']);
})->with(['ftp://127.0.0.1:1', 'not a url'])->throws(WebSocketException::class);

it('throws when nothing listens on the port', function (): void {
    $this->server->stop();

    ($this->pod)()->exec(['ok']);
})->throws(WebSocketException::class, 'Unable to connect to 127.0.0.1');

it('speaks TLS for an https cluster', function (): void {
    $this->server->stop();

    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    $certificate = openssl_csr_sign(openssl_csr_new(['commonName' => '127.0.0.1'], $key), null, $key, 1);
    openssl_x509_export($certificate, $pem);
    openssl_pkey_export($key, $keyPem);
    $path = (string) tempnam(sys_get_temp_dir(), 'k8s-exec-tls-');
    file_put_contents($path, $pem.$keyPem);

    putenv("TLS_CERT={$path}");
    $this->server = LocalServer::script(__DIR__.'/../Fixtures/servers/exec.php');
    putenv('TLS_CERT');

    $result = Kubernetes::url('https://127.0.0.1:'.$this->server->port)
        ->withoutSslVerification()
        ->pods()->setName('api')->exec(['ok']);

    unlink($path);

    expect($result->stdout)->toBe("hi\n")->and($result->exitCode)->toBe(0);
});

it('verifies TLS against an IPv6 cluster URL', function (): void {
    $probe = @stream_socket_server('tcp://[::1]:0');

    if ($probe === false) {
        $this->markTestSkipped('No IPv6 loopback on this host.');
    }

    fclose($probe);
    $this->server->stop();

    // A certificate for the IP addresses themselves, as an in-cluster apiserver has.
    $config = (string) tempnam(sys_get_temp_dir(), 'k8s-exec-cnf-');
    file_put_contents($config, "[req]\ndistinguished_name = dn\n[dn]\n[ext]\nsubjectAltName = IP:::1, IP:127.0.0.1\nbasicConstraints = CA:TRUE\n");
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'config' => $config]);
    $csr = openssl_csr_new(['commonName' => 'apiserver'], $key, ['config' => $config]);
    $certificate = openssl_csr_sign($csr, null, $key, 1, ['config' => $config, 'x509_extensions' => 'ext']);
    openssl_x509_export($certificate, $pem);
    openssl_pkey_export($key, $keyPem, null, ['config' => $config]);
    $both = (string) tempnam(sys_get_temp_dir(), 'k8s-exec-tls-');
    $ca = (string) tempnam(sys_get_temp_dir(), 'k8s-exec-ca-');
    file_put_contents($both, $pem.$keyPem);
    file_put_contents($ca, $pem);

    putenv("TLS_CERT={$both}");
    putenv('BIND=[::]');
    $this->server = LocalServer::script(__DIR__.'/../Fixtures/servers/exec.php');
    putenv('TLS_CERT');
    putenv('BIND');

    try {
        $result = Kubernetes::url("https://[::1]:{$this->server->port}")
            ->withCaCertificate($ca)
            ->pods()->setName('api')->exec(['ok']);
    } finally {
        unlink($config);
        unlink($both);
        unlink($ca);
    }

    expect($result->stdout)->toBe("hi\n")->and($result->exitCode)->toBe(0);
});
