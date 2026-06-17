<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;

class HorizontalPodAutoscaler extends Resource
{
    use HasSpec;
    use HasStatus;

    protected string $kind = 'HorizontalPodAutoscaler';

    protected string $version = 'autoscaling/v2';

    protected bool $usesNamespaces = true;

    public function setScaleTargetRef(string $kind, string $name, string $apiVersion = 'apps/v1'): static
    {
        return $this->setSpec('scaleTargetRef', [
            'apiVersion' => $apiVersion,
            'kind' => $kind,
            'name' => $name,
        ]);
    }

    /** @return array<string, mixed> */
    public function getScaleTargetRef(): array
    {
        return (array) $this->getSpec('scaleTargetRef', []);
    }

    public function setMinReplicas(int $min): static
    {
        return $this->setSpec('minReplicas', $min);
    }

    public function getMinReplicas(): int
    {
        return (int) $this->getSpec('minReplicas', 1);
    }

    public function setMaxReplicas(int $max): static
    {
        return $this->setSpec('maxReplicas', $max);
    }

    public function getMaxReplicas(): int
    {
        return (int) $this->getSpec('maxReplicas', 0);
    }

    public function getCurrentReplicas(): int
    {
        return (int) $this->getStatus('currentReplicas', 0);
    }

    public function getDesiredReplicas(): int
    {
        return (int) $this->getStatus('desiredReplicas', 0);
    }
}
