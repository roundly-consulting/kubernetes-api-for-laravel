<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\CanRolloutRestart;
use RoundlyConsulting\KubernetesApi\Traits\Resource\CanScale;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasReplicas;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasTemplate;

class Deployment extends Resource
{
    use CanRolloutRestart;
    use CanScale;
    use HasReplicas;
    use HasSelectors;
    use HasSpec;
    use HasStatus;
    use HasTemplate;

    protected string $kind = 'Deployment';

    protected string $version = 'apps/v1';

    protected bool $usesNamespaces = true;

    public function setUpdateStrategy(string $strategy, string $maxUnavailable = '25%', string $maxSurge = '25%'): static
    {
        $this->removeAttribute('spec.updateStrategy');

        if ($strategy === 'RollingUpdate') {
            $this->setSpec('updateStrategy.rollingUpdate.maxUnavailable', $maxUnavailable);
            $this->setSpec('updateStrategy.rollingUpdate.maxSurge', $maxSurge);
        }

        return $this->setSpec('updateStrategy.type', $strategy);
    }

    /** @return array<string, mixed> */
    public function getUpdateStrategy(): array
    {
        return $this->getSpec('updateStrategy', []);
    }

    /** @return array<int, array<string, mixed>> */
    public function getConditions(): array
    {
        return $this->getStatus('conditions', []);
    }

    public function setMinReadySeconds(int $seconds): static
    {
        return $this->setSpec('minReadySeconds', $seconds);
    }

    public function getMinReadySeconds(): int
    {
        return (int) $this->getSpec('minReadySeconds', 0);
    }

    /** @return array<string, string> */
    public function getPodsSelectors(): array
    {
        return $this->getSpec('selector.matchLabels', []);
    }

    /** @param array<string, string> $selectors */
    public function setPodsSelectors(array $selectors): static
    {
        return $this->setSpec('selector.matchLabels', $selectors);
    }

    public function getAvailableReplicasCount(): int
    {
        return (int) $this->getStatus('availableReplicas', 0);
    }

    public function getReadyReplicasCount(): int
    {
        return (int) $this->getStatus('readyReplicas', 0);
    }

    public function getDesiredReplicasCount(): int
    {
        return (int) $this->getStatus('replicas', 0);
    }

    public function getUnavailableReplicasCount(): int
    {
        return (int) $this->getStatus('unavailableReplicas', 0);
    }
}
