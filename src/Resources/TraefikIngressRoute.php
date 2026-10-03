<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Resources\Types\TraefikRoute;
use RoundlyConsulting\KubernetesApi\Support\TraefikGroup;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

class TraefikIngressRoute extends Resource
{
    use HasSpec;

    protected string $version = 'traefik.io/v1alpha1';

    protected string $kind = 'IngressRoute';

    protected bool $usesNamespaces = true;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->version = TraefikGroup::resolve($this->version);
    }

    /** @param array<int, string> $entrypoints */
    public function setEntryPoints(array $entrypoints): static
    {
        return $this->setSpec('entryPoints', $entrypoints);
    }

    /** @return array<int, string> */
    public function getEntryPoints(): array
    {
        return $this->getSpec('entryPoints', []);
    }

    public function addRoute(TraefikRoute $route): static
    {
        return $this->addToSpec('routes', $route->toArray());
    }

    /** @return array<int, TraefikRoute> */
    public function getRoutes(): array
    {
        $routes = (array) $this->getSpec('routes', []);

        return collect($routes)->mapInto(TraefikRoute::class)->values()->all();
    }
}
