<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources\Types;

/**
 * A `volumeClaimTemplates` entry for a StatefulSet, describing the
 * PersistentVolumeClaim that is created per replica.
 */
class PersistentVolumeClaimTemplate extends Type
{
    public function setName(string $name): static
    {
        return $this->setAttribute('metadata.name', $name);
    }

    public function getName(): ?string
    {
        return $this->getAttribute('metadata.name');
    }

    /** @param array<int, string> $modes */
    public function setAccessModes(array $modes): static
    {
        return $this->setAttribute('spec.accessModes', $modes);
    }

    /** @return array<int, string> */
    public function getAccessModes(): array
    {
        return (array) $this->getAttribute('spec.accessModes', []);
    }

    public function setStorageClassName(string $name): static
    {
        return $this->setAttribute('spec.storageClassName', $name);
    }

    public function getStorageClassName(): ?string
    {
        return $this->getAttribute('spec.storageClassName');
    }

    public function setStorageRequest(string $request): static
    {
        return $this->setAttribute('spec.resources.requests.storage', $request);
    }

    public function getStorageRequest(): ?string
    {
        return $this->getAttribute('spec.resources.requests.storage');
    }
}
