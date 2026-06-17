<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources\Types;

class TraefikService extends Type
{
    public static function to(string $service, string $port): static
    {
        return static::make()
            ->setAttribute('kind', 'Service')
            ->setAttribute('name', $service)
            ->setAttribute('port', $port);
    }
}
