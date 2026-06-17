<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

class Endpoints extends Resource
{
    protected string $kind = 'Endpoints';

    protected ?string $plural = 'endpoints';

    protected bool $usesNamespaces = true;

    /** @return array<int, array<string, mixed>> */
    public function getSubsets(): array
    {
        return (array) $this->getAttribute('subsets', []);
    }

    /** @return array<string, mixed> */
    public function getSubset(int $index): array
    {
        return (array) ($this->getSubsets()[$index] ?? []);
    }

    /** @return array<int, string> */
    public function getReadyAddresses(): array
    {
        return collect($this->getSubsets())
            ->flatMap(fn (array $subset): array => (array) ($subset['addresses'] ?? []))
            ->pluck('ip')
            ->filter(fn (mixed $ip): bool => is_string($ip))
            ->values()
            ->all();
    }

    /** @return array<int, int> */
    public function getPorts(): array
    {
        return collect($this->getSubsets())
            ->flatMap(fn (array $subset): array => (array) ($subset['ports'] ?? []))
            ->pluck('port')
            ->filter(fn (mixed $port): bool => is_int($port))
            ->values()
            ->all();
    }
}
