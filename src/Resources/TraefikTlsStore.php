<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Support\TraefikGroup;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

class TraefikTlsStore extends Resource
{
    use HasSpec;

    protected string $version = 'traefik.io/v1alpha1';

    protected string $kind = 'TLSStore';

    /**
     * The CRD REST plural is `tlsstores` (verified against
     * `tlsstores.traefik.io`); set it explicitly so it never drifts from the
     * naive pluraliser.
     */
    protected ?string $plural = 'tlsstores';

    protected bool $usesNamespaces = true;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->version = TraefikGroup::resolve($this->version);
    }

    /**
     * Point the store's default certificate at a Kubernetes TLS Secret by name
     * (`spec.defaultCertificate.secretName`).
     */
    public function setDefaultCertificate(string $secretName): static
    {
        return $this->setSpec('defaultCertificate.secretName', $secretName);
    }

    public function getDefaultCertificate(): ?string
    {
        $secretName = $this->getSpec('defaultCertificate.secretName');

        return is_string($secretName) ? $secretName : null;
    }
}
