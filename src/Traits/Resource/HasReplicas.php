<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasReplicas
{
    use HasSpec;

    public function setReplicas(int $replicas = 1): static
    {
        return $this->setSpec('replicas', $replicas);
    }

    public function getReplicas(): int
    {
        return $this->getSpec('replicas', 1);
    }
}
