<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

class TraefikMiddleware extends Resource
{
    use HasSpec;

    protected string $version = 'traefik.io/v1alpha1';

    protected string $kind = 'Middleware';

    /**
     * "Middleware" is uncountable, so the naive pluraliser leaves it unchanged;
     * the CRD REST plural is "middlewares", so it must be set explicitly.
     */
    protected ?string $plural = 'middlewares';

    protected bool $usesNamespaces = true;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $group = config('kubernetes.traefik.group');

        if (is_string($group) && $group !== '') {
            $this->version = $group;
        }
    }

    public function redirectToScheme(string $scheme = 'https', bool $permanent = true): static
    {
        return $this->setSpec('redirectScheme', [
            'scheme' => $scheme,
            'permanent' => $permanent,
        ]);
    }
}
