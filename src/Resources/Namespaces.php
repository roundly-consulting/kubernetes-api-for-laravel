<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatusPhase;

class Namespaces extends Resource
{
    use HasStatus;
    use HasStatusPhase;

    protected string $kind = 'Namespace';

    public function isActive(): bool
    {
        return $this->statusPhaseIs('Active');
    }

    public function isTerminating(): bool
    {
        return $this->statusPhaseIs('Terminating');
    }
}
