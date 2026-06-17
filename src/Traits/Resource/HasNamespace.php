<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasNamespace
{
    use HasAttributes;

    protected bool $usesNamespaces = false;

    protected string $defaultNamespace = 'default';

    public function setDefaultNamespace(string $namespace): static
    {
        $this->defaultNamespace = $namespace;

        return $this;
    }

    public function setNamespace(string $namespace): static
    {
        if (! $this->usesNamespaces) {
            return $this;
        }

        $this->setAttribute('metadata.namespace', $namespace);

        return $this;
    }

    public function getNamespace(): string
    {
        return $this->getAttribute('metadata.namespace', $this->defaultNamespace);
    }

    public function usesNamespaces(): bool
    {
        return $this->usesNamespaces;
    }

    public function ignoreNamespace(bool $ignore = true): static
    {
        return $this->usingNamespaces(! $ignore);
    }

    public function usingNamespaces(bool $usingNamespaces = true): static
    {
        $this->usesNamespaces = $usingNamespaces;

        return $this;
    }
}
