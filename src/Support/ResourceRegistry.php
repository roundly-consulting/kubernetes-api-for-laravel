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
    /** @return class-string<resource>|null */
    public static function classFor(string $name): ?string
    {
        return self::all()[$name] ?? null;
    }

    /** @return array<string, class-string<resource>> */
    public static function all(): array
    {
        /** @var array<string, class-string<resource>> $resources */
        $resources = (array) config('kubernetes.resources', []);

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
