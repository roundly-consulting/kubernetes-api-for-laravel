<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasStatus
{
    use HasAttributes;

    public function getStatus(string $name, mixed $default = null): mixed
    {
        return $this->getAttribute("status.{$name}", $default);
    }
}
