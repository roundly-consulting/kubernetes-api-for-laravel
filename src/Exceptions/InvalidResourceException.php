<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Exceptions;

use InvalidArgumentException;
use RoundlyConsulting\KubernetesApi\Resources\Resource;

/**
 * Thrown by `Kubernetes::registerResource()` and the fake's `seed()` for a class that
 * is not a resource, or a name no cluster could ever answer.
 */
class InvalidResourceException extends InvalidArgumentException
{
    public static function notAResource(string $class): self
    {
        return new self(sprintf('%s is not a %s.', $class, Resource::class));
    }

    public static function unusableName(string $name): self
    {
        return new self("'{$name}' cannot be used as a resource name: it is not a valid method name or a Cluster method already answers it.");
    }

    public static function unknown(string $resource): self
    {
        return new self("'{$resource}' is neither a resource class nor a registered resource name.");
    }
}
