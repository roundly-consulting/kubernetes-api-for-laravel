<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasClusterPaths
{
    use HasKind;
    use HasName;
    use HasNamespace;
    use HasVersion;

    protected function getResourceListingPath(bool $acrossNamespaces = false): string
    {
        return $this->getPathTo($this->getPluralKind(), $acrossNamespaces);
    }

    protected function getResourcePath(): string
    {
        return $this->getPathTo("{$this->getPluralKind()}/{$this->getName()}");
    }

    protected function getSubresourcePath(string $subresource): string
    {
        return $this->getPathTo("{$this->getPluralKind()}/{$this->getName()}/{$subresource}");
    }

    protected function getPathTo(string $path, bool $acrossNamespaces = false): string
    {
        $version = $this->getVersion();

        $basePath = $version === 'v1' ? '/api/v1' : "/apis/{$version}";

        if ($this->usesNamespaces() && ! $acrossNamespaces) {
            $basePath .= "/namespaces/{$this->getNamespace()}";
        }

        return "{$basePath}/{$path}";
    }
}
