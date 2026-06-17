<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;

class Node extends Resource
{
    use HasStatus;

    protected string $kind = 'Node';

    /** @return array<string, mixed> */
    public function getInfo(): array
    {
        return $this->getStatus('nodeInfo', []);
    }

    /** @return array<int, array<string, mixed>> */
    public function getImages(): array
    {
        return $this->getStatus('images', []);
    }

    /** @return array<string, mixed> */
    public function getCapacity(): array
    {
        return $this->getStatus('capacity', []);
    }

    /** @return array<string, mixed> */
    public function getAllocatableInfo(): array
    {
        return $this->getStatus('allocatable', []);
    }
}
