<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Resources\Types\Port;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

class Service extends Resource
{
    use HasSelectors;
    use HasSpec;

    protected string $kind = 'Service';

    protected bool $usesNamespaces = true;

    public function getClusterDns(): string
    {
        return "{$this->getName()}.{$this->getNamespace()}.svc.cluster.local";
    }

    public function setType(string $type = 'ClusterIP'): static
    {
        return $this->setSpec('type', $type);
    }

    public function getType(): string
    {
        return $this->getSpec('type', 'ClusterIP');
    }

    public function useLocalTrafficPolicy(): static
    {
        return $this->setSpec('externalTrafficPolicy', 'Local');
    }

    public function useClusterTrafficPolicy(): static
    {
        return $this->setSpec('externalTrafficPolicy', 'Cluster');
    }

    public function getTrafficPolicy(): string
    {
        return $this->getSpec('externalTrafficPolicy', 'Cluster');
    }

    /** @return array<int, Port> */
    public function getPorts(): array
    {
        $ports = (array) $this->getSpec('ports', []);

        return collect($ports)->mapInto(Port::class)->values()->all();
    }

    /** @param array<int, Port> $ports */
    public function setPorts(array $ports): static
    {
        $ports = collect($ports)->map(fn (Port $port): array => $port->toArray())->all();

        return $this->setSpec('ports', $ports);
    }

    public function addPort(Port $port): static
    {
        return $this->addToSpec('ports', $port->toArray());
    }

    /** @param array<int, Port> $ports */
    public function addPorts(array $ports): static
    {
        foreach ($ports as $port) {
            $this->addPort($port);
        }

        return $this;
    }
}
