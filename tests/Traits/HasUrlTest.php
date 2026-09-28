<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\HasUrl;

it('returns a copy pointed at the url', function () {
    $instance = new class
    {
        use HasUrl;
    };

    $pointed = $instance->url('https://domain.tld');

    expect($pointed->getUrl())->toBe('https://domain.tld')
        ->and($pointed)->not->toBe($instance)
        ->and($instance->getUrl())->toBe('');
});
