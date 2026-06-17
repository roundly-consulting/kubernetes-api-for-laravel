<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\HasManagerName;

it('stores manager name', function () {
    $instance = new class
    {
        use HasManagerName;
    };

    $instance->setManagerName('My Manager');

    expect($instance->getManagerName())->toBe('My Manager');
});
