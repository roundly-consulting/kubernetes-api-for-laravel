<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatusPhase;

it('uses required traits', function () {
    expect(HasStatusPhase::class)->toUse([
        HasStatus::class,
    ]);
});

it('retrieves status phase from phase status attribute', function () {
    $instance = new class
    {
        use HasStatusPhase;
    };

    expect($instance->getStatusPhase('default'))->toBe('default');

    $instance->setAttribute('status.phase', 'Running');

    expect($instance->getStatusPhase('default'))->toBe('Running');
});

it('checks whether resource phase status equals to specified string', function () {
    $instance = new class
    {
        use HasStatusPhase;
    };

    expect($instance->statusPhaseIs('Running', 'Default'))
        ->toBeFalse()
        ->and($instance->statusPhaseIs('Running', 'Running'))
        ->toBeTrue();
});
