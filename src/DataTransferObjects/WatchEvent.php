<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\DataTransferObjects;

use RoundlyConsulting\KubernetesApi\Resources\Resource;

/**
 * A single event emitted by a watch stream: the event type plus the resource
 * object it concerns.
 */
final readonly class WatchEvent
{
    public function __construct(
        public string $type,
        public Resource $object,
    ) {}

    public function isAdded(): bool
    {
        return $this->type === 'ADDED';
    }

    public function isModified(): bool
    {
        return $this->type === 'MODIFIED';
    }

    public function isDeleted(): bool
    {
        return $this->type === 'DELETED';
    }
}
