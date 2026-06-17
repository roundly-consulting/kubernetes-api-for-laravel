<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Resources\Types\PersistentVolumeClaimTemplate;
use RoundlyConsulting\KubernetesApi\Traits\Resource\CanRolloutRestart;
use RoundlyConsulting\KubernetesApi\Traits\Resource\CanScale;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasReplicas;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasTemplate;

class StatefulSet extends Resource
{
    use CanRolloutRestart;
    use CanScale;
    use HasReplicas;
    use HasSelectors;
    use HasSpec;
    use HasStatus;
    use HasTemplate;

    protected string $kind = 'StatefulSet';

    protected string $version = 'apps/v1';

    protected bool $usesNamespaces = true;

    public function setServiceName(string $serviceName): static
    {
        return $this->setSpec('serviceName', $serviceName);
    }

    public function getServiceName(): ?string
    {
        return $this->getSpec('serviceName');
    }

    /** @param array<int, PersistentVolumeClaimTemplate> $templates */
    public function setVolumeClaimTemplates(array $templates): static
    {
        $templates = collect($templates)
            ->map(fn (PersistentVolumeClaimTemplate $template): array => $template->toArray())
            ->all();

        return $this->setSpec('volumeClaimTemplates', $templates);
    }

    /** @return array<int, PersistentVolumeClaimTemplate> */
    public function getVolumeClaimTemplates(): array
    {
        $templates = (array) $this->getSpec('volumeClaimTemplates', []);

        return collect($templates)->mapInto(PersistentVolumeClaimTemplate::class)->values()->all();
    }

    public function getReadyReplicasCount(): int
    {
        return (int) $this->getStatus('readyReplicas', 0);
    }

    public function getCurrentReplicasCount(): int
    {
        return (int) $this->getStatus('currentReplicas', 0);
    }

    public function getUpdatedReplicasCount(): int
    {
        return (int) $this->getStatus('updatedReplicas', 0);
    }
}
