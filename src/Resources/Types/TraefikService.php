<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources\Types;

class TraefikService extends Type
{
    /**
     * A route target: the service and its port, by number (`80`, `'80'`) or by name
     * (`'http'`). Traefik reads a quoted port as a port name, so a numeric port is
     * always sent as an integer.
     */
    public static function to(string $service, int|string $port): static
    {
        return static::make()
            ->setAttribute('kind', 'Service')
            ->setAttribute('name', $service)
            ->setAttribute('port', is_string($port) && ctype_digit($port) ? (int) $port : $port);
    }
}
