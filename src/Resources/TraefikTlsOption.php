<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Support\TraefikGroup;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

class TraefikTlsOption extends Resource
{
    use HasSpec;

    protected string $version = 'traefik.io/v1alpha1';

    protected string $kind = 'TLSOption';

    /**
     * The CRD REST plural is `tlsoptions` (verified against
     * `tlsoptions.traefik.io`); set it explicitly so it never drifts from the
     * naive pluraliser.
     */
    protected ?string $plural = 'tlsoptions';

    protected bool $usesNamespaces = true;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->version = TraefikGroup::resolve($this->version);
    }

    public function setMinVersion(string $version): static
    {
        return $this->setSpec('minVersion', $version);
    }

    public function getMinVersion(): ?string
    {
        $version = $this->getSpec('minVersion');

        return is_string($version) ? $version : null;
    }

    public function setMaxVersion(string $version): static
    {
        return $this->setSpec('maxVersion', $version);
    }

    public function getMaxVersion(): ?string
    {
        $version = $this->getSpec('maxVersion');

        return is_string($version) ? $version : null;
    }

    /** @param array<int, string> $cipherSuites */
    public function setCipherSuites(array $cipherSuites): static
    {
        return $this->setSpec('cipherSuites', array_values($cipherSuites));
    }

    /** @return array<int, string> */
    public function getCipherSuites(): array
    {
        return (array) $this->getSpec('cipherSuites', []);
    }
}
