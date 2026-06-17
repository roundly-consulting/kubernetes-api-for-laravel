<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasStatusPhase
{
    use HasStatus;

    public function getStatusPhase(?string $default = null): ?string
    {
        return $this->getStatus('phase', $default);
    }

    public function statusPhaseIs(string $phase, ?string $defaultPhase = null): bool
    {
        return $this->getStatusPhase($defaultPhase) === $phase;
    }
}
