<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits;

trait HasManagerName
{
    protected ?string $managerName = null;

    public function getManagerName(): ?string
    {
        return $this->managerName;
    }

    /**
     * A copy carrying the field-manager name — sent as the user agent and used to key
     * the cluster's rate-limit budget.
     */
    public function withManagerName(?string $managerName): static
    {
        $clone = clone $this;
        $clone->managerName = $managerName;

        return $clone;
    }
}
