<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

class TraefikMiddleware extends Resource
{
    use HasSpec;

    protected string $version = 'traefik.containo.us/v1alpha1';

    protected string $kind = 'Middleware';

    protected bool $usesNamespaces = true;

    public function redirectToScheme(string $scheme = 'https', bool $permanent = true): static
    {
        return $this->setSpec('redirectScheme', [
            'scheme' => $scheme,
            'permanent' => $permanent,
        ]);
    }
}
