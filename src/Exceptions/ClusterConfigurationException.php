<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Exceptions;

use RoundlyConsulting\KubernetesApi\Cluster;
use RuntimeException;

/**
 * A cluster that cannot be used as defined: no URL to send to, a definition closure
 * that returned something other than a `Cluster`, an unknown `source`, a cluster or
 * client setting of the wrong shape, or a resource that is not bound to any cluster.
 */
class ClusterConfigurationException extends RuntimeException
{
    public static function missingUrl(?string $cluster): self
    {
        $name = $cluster === null ? 'this ad-hoc cluster' : "cluster '{$cluster}'";

        return new self("No cluster URL configured for {$name}.");
    }

    public static function invalidDefinition(string $cluster, mixed $returned): self
    {
        return new self(sprintf(
            "The definition of cluster '%s' must return a %s, got %s. Return the configured cluster from the closure.",
            $cluster,
            Cluster::class,
            get_debug_type($returned),
        ));
    }

    public static function unknownSource(string $cluster, string $source): self
    {
        return new self("Cluster '{$cluster}' has an unknown source '{$source}'. Use 'url', 'kubeconfig' or 'in-cluster'.");
    }

    /**
     * A present config value of the wrong shape, worded like package-toolkit's
     * strict readers: `Configuration value [key] must be …, [given] given.`
     */
    public static function invalidSetting(string $key, string $expectation, mixed $value): self
    {
        $given = match (true) {
            $value === '' => "''",
            is_string($value) => $value,
            is_int($value), is_float($value), is_bool($value) => var_export($value, true),
            default => get_debug_type($value),
        };

        return new self("Configuration value [{$key}] must be {$expectation}, [{$given}] given.");
    }

    public static function unbound(string $resource): self
    {
        return new self("{$resource} is not bound to a cluster. Resolve it through the Kubernetes facade or call setCluster().");
    }
}
