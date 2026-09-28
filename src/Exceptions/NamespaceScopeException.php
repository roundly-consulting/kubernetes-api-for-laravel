<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Exceptions;

use RuntimeException;

/**
 * Thrown when a client or resource scoped with `Kubernetes::namespace($name)` is asked
 * to reach outside that namespace. The scope is a security boundary: it refuses
 * rather than silently re-pointing.
 */
class NamespaceScopeException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $scope,
    ) {
        parent::__construct($message);
    }

    public static function rescope(string $scope, string $requested): self
    {
        return new self("This client is scoped to namespace '{$scope}' and refuses namespace '{$requested}'.", $scope);
    }

    public static function allNamespaces(string $scope): self
    {
        return new self("This client is scoped to namespace '{$scope}' and refuses to list across all namespaces.", $scope);
    }

    public static function ignoringNamespaces(string $scope): self
    {
        return new self("This client is scoped to namespace '{$scope}' and refuses to drop the namespace from a namespaced resource.", $scope);
    }
}
