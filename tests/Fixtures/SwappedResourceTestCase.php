<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Tests\Fixtures;

use RoundlyConsulting\KubernetesApi\Tests\TestCase;

/**
 * The suite's base case with `kubernetes.resources.pods` already pointed at
 * {@see CustomPod} BEFORE the providers boot.
 *
 * Boot order is the whole point, and it is not decoration here: the provider reads
 * `config('kubernetes.resources')` in `boot()` and registers a MACRO per name. A
 * `config()->set()` inside a test body runs after that macro is already bound to the
 * packaged class, so it would read back correctly and change nothing — a swap test
 * structurally incapable of catching the bug it is named for.
 *
 * Note the `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently
 * discard whatever the parent wires, with no error and no red.
 */
abstract class SwappedResourceTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'kubernetes.resources.pods' => CustomPod::class,
        ]);
    }
}
