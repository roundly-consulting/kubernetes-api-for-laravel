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
