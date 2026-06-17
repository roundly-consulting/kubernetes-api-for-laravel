<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasClusterPaths
{
    use HasKind;
    use HasName;
    use HasNamespace;
    use HasVersion;

    protected function getResourceListingPath(): string
    {
        return $this->getPathTo($this->getPlurarKind());
    }

    protected function getResourcePath(): string
    {
        return $this->getPathTo("{$this->getPlurarKind()}/{$this->getName()}");
    }

    protected function getPathTo(string $path): string
    {
        $version = $this->getVersion();

        $basePath = $version === 'v1' ? '/api/v1' : "/apis/{$version}";

        if ($this->usesNamespaces()) {
            $basePath .= "/namespaces/{$this->getNamespace()}";
        }

        return "{$basePath}/{$path}";
    }
}
