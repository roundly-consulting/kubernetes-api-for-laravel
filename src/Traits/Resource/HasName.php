<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasName
{
    use HasAttributes;

    public function setName(string $name): static
    {
        return $this->setAttribute('metadata.name', $name);
    }

    public function getName(): string
    {
        return $this->getAttribute('metadata.name');
    }
}
