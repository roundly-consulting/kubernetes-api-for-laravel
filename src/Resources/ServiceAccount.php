<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

class ServiceAccount extends Resource
{
    protected string $kind = 'ServiceAccount';

    protected bool $usesNamespaces = true;

    public function addSecret(string $name): static
    {
        return $this->addToAttribute('secrets', ['name' => $name]);
    }

    /** @return array<int, array<string, string>> */
    public function getSecrets(): array
    {
        return (array) $this->getAttribute('secrets', []);
    }

    public function addImagePullSecret(string $name): static
    {
        return $this->addToAttribute('imagePullSecrets', ['name' => $name]);
    }

    /** @return array<int, array<string, string>> */
    public function getImagePullSecrets(): array
    {
        return (array) $this->getAttribute('imagePullSecrets', []);
    }

    public function setAutomountServiceAccountToken(bool $automount): static
    {
        return $this->setAttribute('automountServiceAccountToken', $automount);
    }

    public function getAutomountServiceAccountToken(): ?bool
    {
        $value = $this->getAttribute('automountServiceAccountToken');

        return is_bool($value) ? $value : null;
    }
}
