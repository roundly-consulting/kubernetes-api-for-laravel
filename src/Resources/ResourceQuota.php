<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;

class ResourceQuota extends Resource
{
    use HasSpec;
    use HasStatus;

    protected string $kind = 'ResourceQuota';

    protected bool $usesNamespaces = true;

    /** @param array<string, string> $hard */
    public function setHard(array $hard): static
    {
        return $this->setSpec('hard', $hard);
    }

    /** @return array<string, string> */
    public function getHard(): array
    {
        return (array) $this->getSpec('hard', []);
    }

    /** @return array<string, string> */
    public function getUsed(): array
    {
        return (array) $this->getStatus('used', []);
    }
}
