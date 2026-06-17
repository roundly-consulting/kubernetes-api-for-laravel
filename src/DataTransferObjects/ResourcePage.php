<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\DataTransferObjects;

use RoundlyConsulting\KubernetesApi\Resources\ResourcesCollection;

/**
 * A single page of a list response, carrying the items plus the pagination
 * metadata returned by the apiserver (`metadata.continue` and
 * `metadata.remainingItemCount`).
 */
final readonly class ResourcePage
{
    public function __construct(
        public ResourcesCollection $items,
        public ?string $continue = null,
        public ?int $remainingItemCount = null,
    ) {}

    public function hasMore(): bool
    {
        return $this->continue !== null && $this->continue !== '';
    }
}
