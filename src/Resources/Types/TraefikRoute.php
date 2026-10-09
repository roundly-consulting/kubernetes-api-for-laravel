<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources\Types;

use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;

class TraefikRoute extends Type
{
    /**
     * A `Host()` rule for one RFC 1123 hostname. Anything else — a backtick, a space,
     * a parenthesis — could close the matcher and add rules of its own, so it throws.
     *
     * @throws InvalidResourceException
     */
    public static function hostRule(string $host): static
    {
        $label = '[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?';

        if (strlen($host) > 253 || preg_match("/^{$label}(?:\\.{$label})*\\z/", $host) !== 1) {
            throw InvalidResourceException::invalidSegment('Traefik Host() hostname', $host);
        }

        return static::matchRule('Host(`'.$host.'`)');
    }

    /**
     * A `PathPrefix()` rule. The path starts with `/` and holds no backtick, whitespace
     * or control character, so it cannot close the matcher and add rules of its own.
     *
     * @throws InvalidResourceException
     */
    public static function pathRule(string $path): static
    {
        if (preg_match('/^\/[^\s`\x00-\x1F\x7F]*\z/', $path) !== 1) {
            throw InvalidResourceException::invalidSegment('Traefik PathPrefix() path', $path);
        }

        return static::matchRule('PathPrefix(`'.$path.'`)');
    }

    /**
     * A raw Traefik rule, sent as given — never build one from untrusted input.
     */
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
