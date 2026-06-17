<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\HasUrl;

it('stores url value', function () {
    $instance = new class
    {
        use HasUrl;
    };

    $instance->url('https://domain.tld');

    expect($instance->getUrl())->toBe('https://domain.tld');
});
