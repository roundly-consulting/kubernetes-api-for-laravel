<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use RoundlyConsulting\KubernetesApi\Kubernetes;

trait HasCluster
{
    protected ?Kubernetes $cluster = null;

    public function getCluster(): ?Kubernetes
    {
        return $this->cluster;
    }

    public function setCluster(?Kubernetes $cluster): static
    {
        $this->cluster = $cluster;

        return $this;
    }
}
