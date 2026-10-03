<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Support;

use ReflectionClass;
use ReflectionMethod;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\Concerns\ResolvesResources;
use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;
use RoundlyConsulting\KubernetesApi\Resources\Resource;

/**
 * @internal The `kubernetes.resources` map is the one registry: config entries and
 *           `Kubernetes::registerResource()` both land in it, so every cluster — built
 *           before or after a registration — resolves the same class for a name.
 *
 * The map is read wholesale and indexed, never through a
 * `config("kubernetes.resources.{$name}")` interpolation, so every read stays a literal
 * the config contract can see.
 */
final class ResourceRegistry
{
    /**
     * The resource class registered under `$name`, or null when none is. A
     * registered class that is not a {@see Resource} throws rather than failing
     * obscurely once instantiated.
     *
     * @return class-string<resource>|null
     *
     * @throws InvalidResourceException
     */
    public static function classFor(string $name): ?string
    {
        $class = self::configured()[$name] ?? null;

        if ($class === null) {
            return null;
        }

        if (! is_string($class) || ! is_a($class, Resource::class, true)) {
            throw InvalidResourceException::notAResource(is_string($class) ? $class : get_debug_type($class));
        }

        return $class;
    }

    /**
     * @return array<string, class-string<resource>>
     *
     * @throws InvalidResourceException
     */
    public static function all(): array
    {
        /** @var array<string, class-string<resource>> $resources */
        $resources = self::configured();

        return $resources;
    }

    /**
     * The raw `kubernetes.resources` map (absent = none); anything but an array
     * throws instead of being cast into a list no accessor can reach.
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidResourceException
     */
    private static function configured(): array
    {
        $resources = config('kubernetes.resources');

        if ($resources === null) {
            return [];
        }

        if (! is_array($resources)) {
            throw InvalidResourceException::invalidRegistry($resources);
        }

        return $resources;
    }

    /**
     * @param  class-string  $resource
     *
     * @throws InvalidResourceException
     */
    public static function register(string $name, string $resource): void
    {
        if (! is_a($resource, Resource::class, true)) {
            throw InvalidResourceException::notAResource($resource);
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1 || self::shadowedByClusterMethod($name)) {
            throw InvalidResourceException::unusableName($name);
        }

        config()->set('kubernetes.resources', [...self::all(), $name => $resource]);
    }

    /**
     * A name a real `Cluster` method already answers (`url`, `namespace`, …) could
     * never be reached as a resource — `__call` only fires for unknown methods. The
     * typed accessors (`pods`, `deployments`, …) are the exception: they read this map,
     * so re-pointing them is exactly the swap the registry exists for.
     */
    private static function shadowedByClusterMethod(string $name): bool
    {
        if (! method_exists(Cluster::class, $name)) {
            return false;
        }

        $accessors = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(ResolvesResources::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        return ! in_array($name, $accessors, true);
    }
}
