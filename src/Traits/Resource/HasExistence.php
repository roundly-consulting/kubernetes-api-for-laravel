<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasExistence
{
    protected bool $exists = false;

    protected bool $wasRecentlyCreated = false;

    public function markAsExisting(bool $exists = true): static
    {
        $this->exists = $exists;

        return $this;
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function markAsRecentlyCreated(bool $recentlyCreated = true): static
    {
        $this->wasRecentlyCreated = $recentlyCreated;

        return $this;
    }

    public function wasRecentlyCreated(): bool
    {
        return $this->wasRecentlyCreated;
    }
}
