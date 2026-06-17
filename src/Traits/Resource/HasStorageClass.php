<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasStorageClass
{
    use HasSpec;

    public function setStorageClassName(string $storageClass): static
    {
        return $this->setSpec('storageClassName', $storageClass);
    }

    public function getStorageClassName(): ?string
    {
        return $this->getSpec('storageClassName');
    }
}
