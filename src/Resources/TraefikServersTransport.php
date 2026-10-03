<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Support\TraefikGroup;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

class TraefikServersTransport extends Resource
{
    use HasSpec;

    protected string $version = 'traefik.io/v1alpha1';

    protected string $kind = 'ServersTransport';

    /**
     * The CRD REST plural is `serverstransports` (verified against
     * `serverstransports.traefik.io`); set it explicitly so it never drifts
     * from the naive pluraliser.
     */
    protected ?string $plural = 'serverstransports';

    protected bool $usesNamespaces = true;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->version = TraefikGroup::resolve($this->version);
    }

    public function setServerName(string $serverName): static
    {
        return $this->setSpec('serverName', $serverName);
    }

    public function getServerName(): ?string
    {
        $serverName = $this->getSpec('serverName');

        return is_string($serverName) ? $serverName : null;
    }

    public function insecureSkipVerify(bool $skip = true): static
    {
        return $this->setSpec('insecureSkipVerify', $skip);
    }

    public function getInsecureSkipVerify(): bool
    {
        return (bool) $this->getSpec('insecureSkipVerify', false);
    }

    /**
     * Validate backend certificates against a list of CA Secrets by name
     * (`spec.rootCAsSecrets`).
     *
     * @param  array<int, string>  $secretNames
     */
    public function setRootCAsSecrets(array $secretNames): static
    {
        return $this->setSpec('rootCAsSecrets', array_values($secretNames));
    }

    /** @return array<int, string> */
    public function getRootCAsSecrets(): array
    {
        return (array) $this->getSpec('rootCAsSecrets', []);
    }

    /**
     * Present client certificates for mTLS from a list of Secrets by name
     * (`spec.certificatesSecrets`).
     *
     * @param  array<int, string>  $secretNames
     */
    public function setCertificatesSecrets(array $secretNames): static
    {
        return $this->setSpec('certificatesSecrets', array_values($secretNames));
    }

    /** @return array<int, string> */
    public function getCertificatesSecrets(): array
    {
        return (array) $this->getSpec('certificatesSecrets', []);
    }
}
