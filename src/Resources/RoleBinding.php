<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

class RoleBinding extends Resource
{
    protected string $kind = 'RoleBinding';

    protected string $version = 'rbac.authorization.k8s.io/v1';

    protected bool $usesNamespaces = true;

    public function setRoleRef(string $name, string $kind = 'Role'): static
    {
        return $this->setAttribute('roleRef', [
            'apiGroup' => 'rbac.authorization.k8s.io',
            'kind' => $kind,
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
     * the apiserver requires to have no api group; its namespace defaults to the
     * binding's own when left out.
     */
    public function addSubject(string $kind, string $name, ?string $namespace = null): static
    {
        $subject = ['kind' => $kind, 'name' => $name];

        if ($kind === 'ServiceAccount') {
            if ($namespace !== null) {
                $subject['namespace'] = $namespace;
            }
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
