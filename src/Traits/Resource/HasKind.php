<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasKind
{
    use HasAttributes;

    protected string $kind = '';

    public function getKind(): string
    {
        return $this->getAttribute('kind', $this->kind);
    }

    public function getPlurarKind(): string
    {
        return str($this->getKind())
            ->plural()
            ->lower()
            ->toString();
    }

    public function setKind(string $kind): static
    {
        return $this->setAttribute('kind', $kind);
    }
}
