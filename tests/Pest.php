<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Tests\Fixtures\SwappedResourceTestCase;
use RoundlyConsulting\KubernetesApi\Tests\TestCase;

// Explicit paths, not `->in(__DIR__)`: the ResourceSwap directory below needs a
// different base case (config swapped BEFORE boot), and a blanket bind would claim it
// first. ArchTest.php is listed because `finalByDefault` and the registry presets need
// the app booted — an arch file is not automatically test-cased.
uses(TestCase::class)->in(
    'ArchTest.php',
    'Contract',
    'DataTransferObjects',
    'Enums',
    'Feature',
    'Integration',
    'Resources',
    'Support',
    'Traits',
    'WebSocket',
    'KubernetesTest.php',
);

// The resource-registry swap proof needs `kubernetes.resources.pods` pointed at the host
// subclass BEFORE the providers boot, so it runs on its own base case in its own
// directory — Pest binds a test case per directory, not per file.
uses(SwappedResourceTestCase::class)->in('ResourceSwap');

expect()->extend('protected', function (string $method, array $args = []) {
    $class = new ReflectionClass($this->value);
    $method = $class->getMethod($method);
    $method->setAccessible(true);

    $this->value = $method->invokeArgs($this->value, $args);

    return $this;
});
