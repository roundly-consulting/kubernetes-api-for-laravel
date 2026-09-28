<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use RoundlyConsulting\KubernetesApi\Exceptions\NamespaceScopeException;

trait HasNamespace
{
    use HasAttributes;

    protected bool $usesNamespaces = false;

    protected string $defaultNamespace = 'default';

    /**
     * The namespace a scoped cluster pinned this resource to. When set, every request
     * goes to this namespace and every attempt to leave it throws.
     */
    protected ?string $namespaceScope = null;

    public function setDefaultNamespace(string $namespace): static
    {
        $this->guardNamespaceScope($namespace);

        $this->defaultNamespace = $namespace;

        return $this;
    }

    public function setNamespace(string $namespace): static
    {
        $this->guardNamespaceScope($namespace);

        if (! $this->usesNamespaces) {
            return $this;
        }

        $this->setAttribute('metadata.namespace', $namespace);

        return $this;
    }

    public function getNamespace(): string
    {
        return $this->namespaceScope ?? $this->getAttribute('metadata.namespace', $this->defaultNamespace);
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
        if (! $usingNamespaces && $this->usesNamespaces && $this->namespaceScope !== null) {
            throw NamespaceScopeException::ignoringNamespaces($this->namespaceScope);
        }

        $this->usesNamespaces = $usingNamespaces;

        return $this;
    }

    /**
     * Pin the resource to a namespace — done for you by a scoped cluster
     * (`Kubernetes::namespace('prod')->pods()`), and carried into every instance the
     * resource returns.
     *
     * @throws NamespaceScopeException when already pinned to another namespace
     */
    public function scopeToNamespace(string $namespace): static
    {
        $this->guardNamespaceScope($namespace);

        $this->namespaceScope = $namespace;
        $this->defaultNamespace = $namespace;

        if ($this->usesNamespaces) {
            $this->setAttribute('metadata.namespace', $namespace);
        }

        return $this;
    }

    public function namespaceScope(): ?string
    {
        return $this->namespaceScope;
    }

    protected function guardNamespaceScope(string $namespace): void
    {
        if ($this->namespaceScope !== null && $this->namespaceScope !== $namespace) {
            throw NamespaceScopeException::rescope($this->namespaceScope, $namespace);
        }
    }
}
