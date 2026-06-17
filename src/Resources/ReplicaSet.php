<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\CanScale;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasReplicas;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasTemplate;

class ReplicaSet extends Resource
{
    use CanScale;
    use HasReplicas;
    use HasSelectors;
    use HasSpec;
    use HasStatus;
    use HasTemplate;

    protected string $kind = 'ReplicaSet';

    protected string $version = 'apps/v1';

    protected bool $usesNamespaces = true;

    /** @return array<string, string> */
    public function getPodsSelectors(): array
    {
        return $this->getSpec('selector.matchLabels', []);
    }

    /** @param array<string, string> $selectors */
    public function setPodsSelectors(array $selectors): static
    {
        return $this->setMatchLabelsSelector('selector', $selectors);
    }

    public function getReadyReplicasCount(): int
    {
        return (int) $this->getStatus('readyReplicas', 0);
    }

    public function getAvailableReplicasCount(): int
    {
        return (int) $this->getStatus('availableReplicas', 0);
    }

    public function getFullyLabeledReplicasCount(): int
    {
        return (int) $this->getStatus('fullyLabeledReplicas', 0);
    }
}
