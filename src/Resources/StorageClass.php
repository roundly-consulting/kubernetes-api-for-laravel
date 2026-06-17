<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

class StorageClass extends Resource
{
    protected string $kind = 'StorageClass';

    protected string $version = 'storage.k8s.io/v1';

    /** @param array<int, string> $mountOptions */
    public function setMountOptions(array $mountOptions): static
    {
        return $this->setAttribute('mountOptions', $mountOptions);
    }

    /** @return array<int, string> */
    public function getMountOptions(): array
    {
        return $this->getAttribute('mountOptions', []);
    }

    /** @param array<string, string> $parameters */
    public function setParameters(array $parameters): static
    {
        return $this->setAttribute('parameters', $parameters);
    }

    /** @return array<string, string> */
    public function getParameters(): array
    {
        return $this->getAttribute('parameters', []);
    }
}
