<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAccessModes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasMountOptions;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatusPhase;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStorageClass;

class PersistentVolume extends Resource
{
    use HasAccessModes;
    use HasMountOptions;
    use HasSelectors;
    use HasSpec;
    use HasStatus;
    use HasStatusPhase;
    use HasStorageClass;

    protected string $kind = 'PersistentVolume';

    public function setSource(string $name, mixed $parameters): static
    {
        return $this->setSpec("source.$name", $parameters);
    }

    public function getSource(?string $name = null): mixed
    {
        return $this->getSpec($name ? "source.$name" : 'source');
    }

    public function setCapacity(int $size, string $measure = 'Gi'): static
    {
        return $this->setSpec('capacity.storage', $size.$measure);
    }

    public function getCapacity(): ?string
    {
        return $this->getSpec('capacity.storage');
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
