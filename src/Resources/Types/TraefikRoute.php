<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources\Types;

class TraefikRoute extends Type
{
    public static function hostRule(string $host): static
    {
        return static::matchRule('Host(`'.$host.'`)');
    }

    public static function pathRule(string $path): static
    {
        return static::matchRule('PathPrefix(`'.$path.'`)');
    }

    public static function matchRule(string $rule): static
    {
        return static::make()
            ->setAttribute('kind', 'Rule')
            ->setAttribute('match', $rule);
    }

    public function addMiddleware(string $name, ?string $namespace = null): static
    {
        $middleware = ['name' => $name];

        if (! is_null($namespace)) {
            $middleware['namespace'] = $namespace;
        }

        return $this->addToAttribute('middlewares', $middleware);
    }

    /** @return array<int, array<string, string>> */
    public function getMiddlewares(): array
    {
        return (array) $this->getAttribute('middlewares', []);
    }

    public function addService(TraefikService $service): static
    {
        return $this->addToAttribute('services', $service->toArray());
    }

    /** @return array<int, TraefikService> */
    public function getServices(): array
    {
        $services = (array) $this->getAttribute('services', []);

        return collect($services)->mapInto(TraefikService::class)->values()->all();
    }
}
