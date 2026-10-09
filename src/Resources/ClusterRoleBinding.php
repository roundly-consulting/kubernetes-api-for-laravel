<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;

class ClusterRoleBinding extends Resource
{
    protected string $kind = 'ClusterRoleBinding';

    protected string $version = 'rbac.authorization.k8s.io/v1';

    public function setRoleRef(string $name): static
    {
        return $this->setAttribute('roleRef', [
            'apiGroup' => 'rbac.authorization.k8s.io',
            'kind' => 'ClusterRole',
            'name' => $name,
        ]);
    }

    /** @return array<string, mixed> */
    public function getRoleRef(): array
    {
        return (array) $this->getAttribute('roleRef', []);
    }

    /**
     * A `User` / `Group` subject (rbac api group), or a `ServiceAccount` subject, which
     * the apiserver requires to have no api group and — on a cluster-scoped binding — a
     * namespace.
     *
     * @throws InvalidResourceException for a ServiceAccount subject without a namespace
     */
    public function addSubject(string $kind, string $name, ?string $namespace = null): static
    {
        $subject = ['kind' => $kind, 'name' => $name];

        if ($kind === 'ServiceAccount') {
            if ($namespace === null || $namespace === '') {
                throw new InvalidResourceException("The ServiceAccount subject '{$name}' needs a namespace on a ClusterRoleBinding.");
            }

            $subject['namespace'] = $namespace;
        } else {
            $subject['apiGroup'] = 'rbac.authorization.k8s.io';
        }

        return $this->addToAttribute('subjects', $subject);
    }

    /** @return array<int, array<string, mixed>> */
    public function getSubjects(): array
    {
        return (array) $this->getAttribute('subjects', []);
    }
}
