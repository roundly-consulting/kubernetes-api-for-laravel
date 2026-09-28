<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Testing;

/**
 * @internal A request path split into the parts the fake apiserver routes on:
 *           `/apis/apps/v1/namespaces/prod/deployments/web/scale`.
 */
final readonly class ApiPath
{
    public function __construct(
        public ?string $apiVersion = null,
        public ?string $namespace = null,
        public ?string $plural = null,
        public ?string $name = null,
        public ?string $subresource = null,
        public bool $version = false,
    ) {}

    public static function parse(string $path): self
    {
        $path = (string) parse_url($path, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));

        if ($segments === ['version']) {
            return new self(version: true);
        }

        if (($segments[0] ?? null) === 'api' && isset($segments[1])) {
            $apiVersion = $segments[1];
            $rest = array_slice($segments, 2);
        } elseif (($segments[0] ?? null) === 'apis' && isset($segments[1], $segments[2])) {
            $apiVersion = "{$segments[1]}/{$segments[2]}";
            $rest = array_slice($segments, 3);
        } else {
            return new self;
        }

        $namespace = null;

        // `/namespaces/{ns}/{plural}…` is a namespaced path; `/namespaces[/{name}]` is
        // the Namespace resource itself.
        if (($rest[0] ?? null) === 'namespaces' && count($rest) >= 3) {
            $namespace = rawurldecode($rest[1]);
            $rest = array_slice($rest, 2);
        }

        return new self(
            apiVersion: $apiVersion,
            namespace: $namespace,
            plural: $rest[0] ?? null,
            name: isset($rest[1]) ? rawurldecode($rest[1]) : null,
            subresource: $rest[2] ?? null,
        );
    }

    public function isResource(): bool
    {
        return $this->apiVersion !== null && $this->plural !== null;
    }

    /**
     * `pods` for the core group, `deployments.apps` otherwise — how the apiserver
     * names a resource in its error messages.
     */
    public function label(): string
    {
        $group = str_contains((string) $this->apiVersion, '/') ? strstr((string) $this->apiVersion, '/', true) : '';

        return $group === '' ? (string) $this->plural : "{$this->plural}.{$group}";
    }
}
