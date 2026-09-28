<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by `Kubernetes::cluster($name)` for a name that is neither in
 * `config('kubernetes.clusters')` nor registered with `Kubernetes::registerCluster()`.
 */
class ClusterNotFoundException extends InvalidArgumentException
{
    public static function named(string $name): self
    {
        return new self("No cluster '{$name}' definition found.");
    }
}
