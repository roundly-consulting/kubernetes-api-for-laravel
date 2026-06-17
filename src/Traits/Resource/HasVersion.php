<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasVersion
{
    use HasAttributes;

    protected string $version = 'v1';

    public function setVersion(string $version): static
    {
        return $this->setAttribute('apiVersion', $version);
    }

    public function getVersion(): string
    {
        return $this->getAttribute('apiVersion', $this->version);
    }
}
