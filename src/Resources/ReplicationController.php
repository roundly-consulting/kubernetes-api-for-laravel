<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\CanScale;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasReplicas;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasTemplate;

class ReplicationController extends Resource
{
    use CanScale;
    use HasReplicas;
    use HasSpec;
    use HasStatus;
    use HasTemplate;

    protected string $kind = 'ReplicationController';

    protected bool $usesNamespaces = true;

    /** @param array<string, string> $selector */
    public function setPodsSelectors(array $selector): static
    {
        return $this->setSpec('selector', $selector);
    }

    /** @return array<string, string> */
    public function getPodsSelectors(): array
    {
        return (array) $this->getSpec('selector', []);
    }

    public function getReadyReplicasCount(): int
    {
        return (int) $this->getStatus('readyReplicas', 0);
    }

    public function getAvailableReplicasCount(): int
    {
        return (int) $this->getStatus('availableReplicas', 0);
    }
}
