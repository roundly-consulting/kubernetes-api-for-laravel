<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;
use RoundlyConsulting\KubernetesApi\Support\PathSegment;

/**
 * Builds the apiserver path for a resource. Every segment is validated (and the name
 * percent-encoded) here, the one place every request path is assembled, so no value —
 * a user-supplied name above all — can add, drop or climb a path segment.
 */
trait HasClusterPaths
{
    use HasKind;
    use HasName;
    use HasNamespace;
    use HasVersion;

    /**
     * @throws InvalidResourceException
     */
    protected function getResourceListingPath(bool $acrossNamespaces = false): string
    {
        return $this->getPathTo(PathSegment::plural($this->getPluralKind()), $acrossNamespaces);
    }

    /**
     * @throws InvalidResourceException
     */
    protected function getResourcePath(): string
    {
        $name = PathSegment::name($this->getAttribute('metadata.name'));

        return $this->getPathTo(PathSegment::plural($this->getPluralKind())."/{$name}");
    }

    /**
     * @throws InvalidResourceException
     */
    protected function getSubresourcePath(string $subresource): string
    {
        return $this->getResourcePath().'/'.PathSegment::plural($subresource);
    }

    /**
     * @throws InvalidResourceException
     */
    protected function getPathTo(string $path, bool $acrossNamespaces = false): string
    {
        $version = PathSegment::apiVersion($this->getVersion());

        $basePath = $version === 'v1' ? '/api/v1' : "/apis/{$version}";

        if ($this->usesNamespaces() && ! $acrossNamespaces) {
            $basePath .= '/namespaces/'.PathSegment::namespace($this->getNamespace());
        }

        return "{$basePath}/{$path}";
    }
}
