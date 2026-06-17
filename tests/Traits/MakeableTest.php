<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Makeable;

class MakeableTest
{
    use Makeable;

    public function __construct(public string $string) {}
}

it('creates instance with make static method', function () {
    $instance = MakeableTest::make('Hello');

    expect($instance)
        ->toBeInstanceOf(MakeableTest::class)
        ->string->toBe('Hello');
});
