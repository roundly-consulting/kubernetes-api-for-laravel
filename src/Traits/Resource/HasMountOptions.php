<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasMountOptions
{
    use HasSpec;

    /** @param array<int, string> $mountOptions */
    public function setMountOptions(array $mountOptions): static
    {
        return $this->setSpec('mountOptions', $mountOptions);
    }

    /** @return array<int, string> */
    public function getMountOptions(): array
    {
        return (array) $this->getSpec('mountOptions', []);
    }
}
