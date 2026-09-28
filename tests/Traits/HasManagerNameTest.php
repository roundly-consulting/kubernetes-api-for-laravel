<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\HasManagerName;

it('returns a copy carrying the manager name', function () {
    $instance = new class
    {
        use HasManagerName;
    };

    $named = $instance->withManagerName('My Manager');

    expect($named->getManagerName())->toBe('My Manager')
        ->and($named)->not->toBe($instance)
        ->and($instance->getManagerName())->toBeNull();
});
