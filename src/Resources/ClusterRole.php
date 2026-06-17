<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

class ClusterRole extends Resource
{
    protected string $kind = 'ClusterRole';

    protected string $version = 'rbac.authorization.k8s.io/v1';

    /**
     * @param  array<int, string>  $apiGroups
     * @param  array<int, string>  $resources
     * @param  array<int, string>  $verbs
     */
    public function addRule(array $apiGroups, array $resources, array $verbs): static
    {
        return $this->addToAttribute('rules', [
            'apiGroups' => $apiGroups,
            'resources' => $resources,
            'verbs' => $verbs,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function getRules(): array
    {
        return (array) $this->getAttribute('rules', []);
    }
}
