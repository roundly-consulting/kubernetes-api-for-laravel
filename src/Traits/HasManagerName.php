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

    public function setManagerName(?string $managerName): self
    {
        $this->managerName = $managerName;

        return $this;
    }
}
