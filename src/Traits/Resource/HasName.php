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

    /**
     * The name, or null for an unnamed resource (a pod template, a `generateName` create).
     */
    public function getName(): ?string
    {
        $name = $this->getAttribute('metadata.name');

        return is_string($name) ? $name : null;
    }
}
