<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\CanRolloutRestart;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasTemplate;

class DaemonSet extends Resource
{
    use CanRolloutRestart;
    use HasSelectors;
    use HasSpec;
    use HasStatus;
    use HasTemplate;

    protected string $kind = 'DaemonSet';

    protected string $version = 'apps/v1';

    protected bool $usesNamespaces = true;

    public function setUpdateStrategy(string $strategy, string $maxUnavailable = '1'): static
    {
        $this->removeAttribute('spec.updateStrategy');

        if ($strategy === 'RollingUpdate') {
            $this->setSpec('updateStrategy.rollingUpdate.maxUnavailable', $maxUnavailable);
        }

        return $this->setSpec('updateStrategy.type', $strategy);
    }

    /** @return array<string, mixed> */
    public function getUpdateStrategy(): array
    {
        return $this->getSpec('updateStrategy', []);
    }

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

    public function getDesiredNumberScheduled(): int
    {
        return (int) $this->getStatus('desiredNumberScheduled', 0);
    }

    public function getCurrentNumberScheduled(): int
    {
        return (int) $this->getStatus('currentNumberScheduled', 0);
    }

    public function getNumberReady(): int
    {
        return (int) $this->getStatus('numberReady', 0);
    }

    public function getNumberAvailable(): int
    {
        return (int) $this->getStatus('numberAvailable', 0);
    }
}
