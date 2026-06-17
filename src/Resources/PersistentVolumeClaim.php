<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAccessModes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatusPhase;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStorageClass;

class PersistentVolumeClaim extends Resource
{
    use HasAccessModes;
    use HasSelectors;
    use HasSpec;
    use HasStatus;
    use HasStatusPhase;
    use HasStorageClass;

    protected string $kind = 'PersistentVolumeClaim';

    protected bool $usesNamespaces = true;

    public function setCapacity(int $size, string $measure = 'Gi'): static
    {
        return $this->setSpec('resources.requests.storage', $size.$measure);
    }

    public function getCapacity(): ?string
    {
        return $this->getSpec('resources.requests.storage');
    }

    public function isAvailable(): bool
    {
        return $this->statusPhaseIs('Available');
    }

    public function isBound(): bool
    {
        return $this->statusPhaseIs('Bound');
    }
}
