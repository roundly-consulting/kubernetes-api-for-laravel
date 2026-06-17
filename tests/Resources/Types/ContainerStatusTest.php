<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\KubernetesApi\Resources\Types\ContainerStatus;

it('returns state from attribute', function () {
    $instance = ContainerStatus::make();

    expect($instance->getState())->toBe('unknown');

    $instance->setAttribute('state', [
        'running' => true,
    ]);

    expect($instance->getState())->toBe('running');
});

it('returns start datetime instance as carbon', function () {
    $instance = ContainerStatus::make();

    $instance->setAttribute('state', [
        'running' => [
            'startedAt' => '2023-03-28 11:39:23',
        ],
        'starting' => [
            'startedAt' => null,
        ],
    ]);

    expect($instance->getStartedAt())
        ->toBeInstanceOf(Carbon::class)
        ->format('d.m.Y H:i:s')
        ->toBe('28.03.2023 11:39:23');
});

it('returns start datetime for a terminated container', function () {
    $instance = ContainerStatus::make();

    $instance->setAttribute('state', [
        'terminated' => [
            'startedAt' => '2023-03-28 11:39:23',
        ],
    ]);

    expect($instance->getStartedAt())
        ->toBeInstanceOf(Carbon::class)
        ->format('d.m.Y H:i:s')
        ->toBe('28.03.2023 11:39:23');
});

it('returns null as start time when container is in unknown state', function () {
    $instance = ContainerStatus::make();

    expect($instance->getStartedAt())
        ->toBeNull();
});

it('returns null as start time when container does not provide timestamp', function () {
    $instance = ContainerStatus::make();

    $instance->setAttribute('state', [
        'running' => [],
    ]);

    expect($instance->getStartedAt())
        ->toBeNull();
});

it('returns state reason', function () {
    $instance = ContainerStatus::make();

    $instance->setAttribute('state', [
        'running' => [
            'reason' => 'Everything is ok',
        ],
    ]);

    expect($instance->getStateReason())->toBe('Everything is ok');
});

it('returns null state reason when reason is not provided', function () {
    $instance = ContainerStatus::make();

    $instance->setAttribute('state', [
        'running' => [],
    ]);

    expect($instance->getStateReason())->toBeNull();
});

it('checks whether container is ready', function () {
    $instance = ContainerStatus::make();

    expect($instance->isReady())->toBeFalse();

    $instance->setAttribute('ready', true);

    expect($instance->isReady())->toBeTrue();
});

it('checks whether container is started', function () {
    $instance = ContainerStatus::make();

    expect($instance->isStarted())->toBeFalse();

    $instance->setAttribute('started', true);

    expect($instance->isStarted())->toBeTrue();
});

it('returns count of restarts', function () {
    $instance = ContainerStatus::make();

    expect($instance->restarts())->toBe(0);

    $instance->setAttribute('restartCount', 4);

    expect($instance->restarts())->toBe(4);
});
