<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Types\Probe;

it('sets failure and success threshold by default', function () {
    $instance = Probe::make();

    expect($instance->toArray())->toBe([
        'failureThreshold' => 1,
        'successThreshold' => 1,
    ]);
});

it('sets command to exec', function () {
    $instance = Probe::make();

    $instance->command(['ping', 'localhost']);

    expect($instance->getCommand())
        ->toBe(['ping', 'localhost'])
        ->and($instance->toArray())->toBe([
            'exec' => [
                'command' => ['ping', 'localhost'],
            ],
            'failureThreshold' => 1,
            'successThreshold' => 1,
        ]);
});

it('sets http attribute with default values', function () {
    $instance = Probe::http();

    expect($instance->getHttp())
        ->toBe([
            'path' => '/healthz',
            'port' => 8080,
            'scheme' => 'HTTP',
        ])
        ->and($instance->toArray())
        ->toBe([
            'failureThreshold' => 1,
            'httpGet' => [
                'path' => '/healthz',
                'port' => 8080,
                'scheme' => 'HTTP',
            ],
            'successThreshold' => 1,
        ]);
});

it('sets http attribute with custom values', function () {
    $instance = Probe::http(
        path: '/api/check',
        port: 80,
        headers: [
            'X-Auth' => 'ABCD',
        ],
        scheme: 'HTTPS',
    );

    expect($instance->getHttp())
        ->toBe([
            'path' => '/api/check',
            'port' => 80,
            'scheme' => 'HTTPS',
            'httpHeaders' => [
                [
                    'name' => 'X-Auth',
                    'value' => 'ABCD',
                ],
            ],
        ])
        ->and($instance->toArray())
        ->toBe([
            'failureThreshold' => 1,
            'httpGet' => [
                'path' => '/api/check',
                'port' => 80,
                'scheme' => 'HTTPS',
                'httpHeaders' => [
                    [
                        'name' => 'X-Auth',
                        'value' => 'ABCD',
                    ],
                ],
            ],
            'successThreshold' => 1,
        ]);
});

it('sets tcp check', function () {
    $instance = Probe::tcp(8080, 'localhost');

    expect($instance->getTcp())
        ->toBe([
            'host' => 'localhost',
            'port' => 8080,
        ])
        ->and($instance->toArray())
        ->toBe([
            'failureThreshold' => 1,
            'successThreshold' => 1,
            'tcpSocket' => [
                'host' => 'localhost',
                'port' => 8080,
            ],
        ]);
});
