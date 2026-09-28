<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\Exceptions\ClusterConfigurationException;
use RoundlyConsulting\KubernetesApi\Exceptions\NamespaceScopeException;

trait HasCluster
{
    use HasNamespace;

    protected ?Cluster $cluster = null;

    public function getCluster(): ?Cluster
    {
        return $this->cluster;
    }

    /**
     * Bind the resource to a cluster. A namespace-scoped cluster
     * (`Kubernetes::namespace('prod')`) pins the resource to its namespace.
     *
     * @throws NamespaceScopeException when the resource is already pinned to another namespace
     */
    public function setCluster(?Cluster $cluster): static
    {
        $scope = $cluster?->namespaceScope();

        if ($scope !== null) {
            $this->scopeToNamespace($scope);
        }

        $this->cluster = $cluster;

        return $this;
    }

    /**
     * @throws ClusterConfigurationException when no cluster is bound
     */
    protected function requireCluster(): Cluster
    {
        return $this->cluster ?? throw ClusterConfigurationException::unbound(static::class);
    }
}
