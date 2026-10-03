<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Exceptions;

use InvalidArgumentException;
use RoundlyConsulting\KubernetesApi\Resources\Resource;

/**
 * Thrown by `Kubernetes::registerResource()` and the fake's `seed()` for a class that
 * is not a resource, or a name no cluster could ever answer — and before any request
 * whose name, namespace, plural or apiVersion cannot be a single URL path segment.
 */
class InvalidResourceException extends InvalidArgumentException
{
    public static function notAResource(string $class): self
    {
        return new self(sprintf('%s is not a %s.', $class, Resource::class));
    }

    /**
     * `kubernetes.resources` is not a map of accessor names to resource classes.
     */
    public static function invalidRegistry(mixed $value): self
    {
        $given = is_scalar($value) ? var_export($value, true) : get_debug_type($value);

        return new self("Configuration value [kubernetes.resources] must be an array of resource classes, [{$given}] given.");
    }

    public static function unusableName(string $name): self
    {
        return new self("'{$name}' cannot be used as a resource name: it is not a valid method name or a Cluster method already answers it.");
    }

    public static function unknown(string $resource): self
    {
        return new self("'{$resource}' is neither a resource class nor a registered resource name.");
    }

    public static function missingName(): self
    {
        return new self('A resource name is required for this request: call setName() first.');
    }

    /**
     * A value that cannot be one segment of an apiserver URL path — refused before any
     * request is sent, so it can never be routed somewhere else.
     */
    public static function invalidSegment(string $what, mixed $value): self
    {
        $shown = is_string($value) ? var_export($value, true) : get_debug_type($value);

        return new self("{$shown} is not a valid {$what}.");
    }
}
