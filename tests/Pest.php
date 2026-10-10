<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Test;
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

/**
 * Whether `$needle` occurs anywhere inside `$value`: a string, a scalar's export, an
 * array's keys and items, an object's properties (private ones included). PHP's own
 * redaction wrapper is opaque, and so is the test harness — the test case and the
 * container hold the config the test wrote, by design.
 *
 * @param  array<int, true>  $seen
 */
function k8sHolds(mixed $value, string $needle, array &$seen = []): bool
{
    if ($value instanceof SensitiveParameterValue || $value instanceof Test || $value instanceof Container) {
        return false;
    }

    if (is_string($value) || is_int($value) || is_float($value)) {
        return str_contains(is_string($value) ? $value : var_export($value, true), $needle);
    }

    if (is_object($value)) {
        if (isset($seen[spl_object_id($value)])) {
            return false;
        }

        $seen[spl_object_id($value)] = true;

        if ($value instanceof Stringable && str_contains((string) $value, $needle)) {
            return true;
        }

        $value = (array) $value;
    }

    if (is_array($value)) {
        foreach ($value as $key => $item) {
            if (str_contains((string) $key, $needle) || k8sHolds($item, $needle, $seen)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Every place one of the secrets shows in `$e` or a previous exception: the message,
 * `getTraceAsString()`, a frame's arguments. All of them, so a red run lists every leak.
 *
 * @param  array<string, string>  $secrets  label => secret
 * @return list<string>
 */
function k8sLeaks(Throwable $e, array $secrets): array
{
    $leaks = [];

    for ($depth = 0, $current = $e; $current !== null; $depth++, $current = $current->getPrevious()) {
        $exception = sprintf('%s%s', $depth === 0 ? '' : "previous #{$depth} ", $current::class);

        foreach ($secrets as $label => $secret) {
            if (str_contains($current->getMessage(), $secret)) {
                $leaks[] = "{$label} in {$exception}: message";
            }

            if (str_contains($current->getTraceAsString(), $secret)) {
                $leaks[] = "{$label} in {$exception}: getTraceAsString()";
            }

            foreach ($current->getTrace() as $i => $frame) {
                if (k8sHolds($frame['args'] ?? [], $secret)) {
                    $leaks[] = sprintf('%s in %s: frame #%d %s%s%s() args', $label, $exception, $i, $frame['class'] ?? '', $frame['type'] ?? '', $frame['function']);
                }
            }
        }
    }

    return $leaks;
}
