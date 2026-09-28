<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\DataTransferObjects;

/**
 * The `Scale` subresource (`autoscaling/v1`) the apiserver answers a scale with: the
 * desired and the currently observed replica count of the scaled workload. It is not
 * the workload itself — `find()` the Deployment (or StatefulSet, …) for that.
 */
final readonly class Scale
{
    public function __construct(
        public string $name,
        public ?string $namespace,
        public int $replicas,
        public int $currentReplicas,
        public ?string $selector = null,
        public ?string $resourceVersion = null,
    ) {}

    /**
     * Map a `Scale` payload: `spec.replicas` is the desired count, `status.replicas`
     * the observed one, `status.selector` the label selector as a string.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload): self
    {
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $spec = is_array($payload['spec'] ?? null) ? $payload['spec'] : [];
        $status = is_array($payload['status'] ?? null) ? $payload['status'] : [];
        $string = static fn (mixed $value): ?string => is_string($value) && $value !== '' ? $value : null;

        return new self(
            name: (string) $string($metadata['name'] ?? null),
            namespace: $string($metadata['namespace'] ?? null),
            replicas: is_numeric($spec['replicas'] ?? null) ? (int) $spec['replicas'] : 0,
            currentReplicas: is_numeric($status['replicas'] ?? null) ? (int) $status['replicas'] : 0,
            selector: $string($status['selector'] ?? null),
            resourceVersion: $string($metadata['resourceVersion'] ?? null),
        );
    }
}
