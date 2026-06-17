<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources\Types;

use Illuminate\Support\Carbon;

/**
 * @method string getName()
 */
class ContainerStatus extends Type
{
    public function getState(): string
    {
        $state = (array) $this->getAttribute('state', []);

        $keys = array_keys($state);

        return isset($keys[0]) ? (string) $keys[0] : 'unknown';
    }

    public function getStartedAt(): ?Carbon
    {
        $key = match ($this->getState()) {
            'running' => 'state.running.startedAt',
            'terminated' => 'state.terminated.startedAt',
            default => null,
        };

        if (! $key) {
            return null;
        }

        $startedAt = $this->getAttribute($key);

        if (! $startedAt) {
            return null;
        }

        return Carbon::parse($startedAt);
    }

    public function getStateReason(): ?string
    {
        return $this->getAttribute("state.{$this->getState()}.reason");
    }

    public function isReady(): bool
    {
        return $this->getAttribute('ready', false);
    }

    public function isStarted(): bool
    {
        return $this->getAttribute('started', false);
    }

    public function restarts(): int
    {
        return $this->getAttribute('restartCount', 0);
    }
}
