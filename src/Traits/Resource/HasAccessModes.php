<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasAccessModes
{
    use HasSpec;

    /** @param array<int, string> $accessModes */
    public function setAccessModes(array $accessModes): static
    {
        return $this->setSpec('accessModes', $accessModes);
    }

    /** @return array<int, string> */
    public function getAccessModes(): array
    {
        return (array) $this->getSpec('accessModes', []);
    }
}
