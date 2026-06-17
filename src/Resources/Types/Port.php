<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources\Types;

class Port extends Type
{
    public static function http(int $containerPort = 80): static
    {
        return static::make()
            ->setAttribute('protocol', 'TCP')
            ->setAttribute('port', 80)
            ->setAttribute('targetPort', $containerPort);
    }
}
