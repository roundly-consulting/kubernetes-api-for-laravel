<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \RoundlyConsulting\KubernetesApi\Kubernetes
 *
 * @mixin \RoundlyConsulting\KubernetesApi\Kubernetes
 */
class Kubernetes extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \RoundlyConsulting\KubernetesApi\Kubernetes::class;
    }
}
