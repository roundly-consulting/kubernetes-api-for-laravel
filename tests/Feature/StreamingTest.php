<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\DataTransferObjects\PodLogOptions;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\WatchEvent;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Tests\Fixtures\LocalServer;

/*
 * Watches and log follows are long-lived and legitimately quiet. Against a real server
 * (the stream handler, not a mock) the request-wide `client.options.timeout` used to cut
 * them off after 5 s of silence with a raw "Unable to read from stream".
 */

beforeEach(function (): void {
    $this->server = LocalServer::http(__DIR__.'/../Fixtures/servers/apiserver.php');
    $this->cluster = Kubernetes::url($this->server->url())->withToken('t');

    config()->set('kubernetes.client.options.timeout', 1);
});

afterEach(function (): void {
    $this->server->stop();
});

it('keeps a quiet watch open past the request timeout', function (): void {
    $events = [];

    $this->cluster->pods()->setNamespace('shop')->watch(function (WatchEvent $event) use (&$events): void {
        $events[] = $event->type;
    });

    expect($events)->toBe(['ADDED', 'MODIFIED'])
        ->and($this->server->requests())->toBe(['GET /api/v1/namespaces/shop/pods?watch=1']);
});

it('keeps a quiet log follow open past the request timeout', function (): void {
    $lines = iterator_to_array($this->cluster->pods()->setNamespace('shop')->setName('api')->streamLogs(), false);

    expect($lines)->toBe(['first', 'second']);
});

it('ends a stream cleanly when the configured idle timeout passes', function (): void {
    config()->set('kubernetes.client.stream_timeout', 1);

    $events = [];
    $started = microtime(true);

    $this->cluster->pods()->setNamespace('shop')->watch(function (WatchEvent $event) use (&$events): void {
        $events[] = $event->type;
    });

    $lines = iterator_to_array($this->cluster->pods()->setNamespace('shop')->setName('api')->streamLogs(new PodLogOptions(follow: true)), false);

    expect($events)->toBe(['ADDED'])
        ->and($lines)->toBe(['first'])
        ->and(microtime(true) - $started)->toBeLessThan(4.0);
});

it('still applies the request timeout to ordinary requests', function (): void {
    expect($this->cluster->pods()->setNamespace('shop')->setName('api')->find()->getName())->toBe('x');
});

it('delivers each event of a chunked tls stream as it arrives, not once 8 KiB have piled up', function (): void {
    $this->server->stop();

    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    $certificate = openssl_csr_sign(openssl_csr_new(['commonName' => '127.0.0.1'], $key), null, $key, 1);
    openssl_x509_export($certificate, $pem);
    openssl_pkey_export($key, $keyPem);
    $path = (string) tempnam(sys_get_temp_dir(), 'k8s-stream-tls-');
    file_put_contents($path, $pem.$keyPem);

    putenv("TLS_CERT={$path}");
    $this->server = LocalServer::script(__DIR__.'/../Fixtures/servers/chunked.php');
    putenv('TLS_CERT');

    $cluster = Kubernetes::url('https://127.0.0.1:'.$this->server->port)->withToken('t')->withoutSslVerification();

    $started = microtime(true);
    $arrivals = [];

    $cluster->pods()->setNamespace('shop')->watch(function (WatchEvent $event) use (&$arrivals, $started): void {
        $arrivals[$event->type] = microtime(true) - $started;
    });

    $lines = [];
    $started = microtime(true);

    foreach ($cluster->pods()->setNamespace('shop')->setName('api')->streamLogs(new PodLogOptions(follow: true)) as $line) {
        $lines[$line] = microtime(true) - $started;
    }

    unlink($path);

    expect(array_keys($arrivals))->toBe(['ADDED', 'MODIFIED'])
        ->and($arrivals['ADDED'])->toBeLessThan(1.0)
        ->and(array_keys($lines))->toBe(['first', 'second'])
        ->and($lines['first'])->toBeLessThan(1.0);
});
