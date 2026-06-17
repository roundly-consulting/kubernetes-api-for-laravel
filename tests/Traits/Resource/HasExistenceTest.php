<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasExistence;

it('marks resource as existing and gets value of existence', function () {
    $instance = new class
    {
        use HasExistence;
    };

    expect($instance->exists())->toBeFalse();

    $instance->markAsExisting();
    expect($instance->exists())->toBeTrue();

    $instance->markAsExisting(false);
    expect($instance->exists())->toBeFalse();
});

it('marks resource as recently created and gets that value', function () {
    $instance = new class
    {
        use HasExistence;
    };

    expect($instance->wasRecentlyCreated())->toBeFalse();

    $instance->markAsRecentlyCreated();
    expect($instance->wasRecentlyCreated())->toBeTrue();

    $instance->markAsRecentlyCreated(false);
    expect($instance->wasRecentlyCreated())->toBeFalse();
});
