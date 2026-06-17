<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

class NetworkPolicy extends Resource
{
    use HasSpec;

    protected string $kind = 'NetworkPolicy';

    protected string $version = 'networking.k8s.io/v1';

    protected ?string $plural = 'networkpolicies';

    protected bool $usesNamespaces = true;

    /** @param array<string, string> $labels */
    public function setPodSelector(array $labels): static
    {
        return $this->setSpec('podSelector.matchLabels', $labels);
    }

    /** @return array<string, string> */
    public function getPodSelector(): array
    {
        return (array) $this->getSpec('podSelector.matchLabels', []);
    }

    /** @param array<int, string> $types */
    public function setPolicyTypes(array $types): static
    {
        return $this->setSpec('policyTypes', $types);
    }

    /** @return array<int, string> */
    public function getPolicyTypes(): array
    {
        return (array) $this->getSpec('policyTypes', []);
    }

    /** @param array<string, mixed> $rule */
    public function addIngressRule(array $rule): static
    {
        return $this->addToSpec('ingress', $rule);
    }

    /** @param array<string, mixed> $rule */
    public function addEgressRule(array $rule): static
    {
        return $this->addToSpec('egress', $rule);
    }
}
