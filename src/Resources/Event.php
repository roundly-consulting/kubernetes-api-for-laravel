<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use Illuminate\Support\Carbon;

class Event extends Resource
{
    protected string $kind = 'Event';

    protected bool $usesNamespaces = true;

    public function getReason(): ?string
    {
        return $this->getAttribute('reason');
    }

    public function getMessage(): ?string
    {
        return $this->getAttribute('message');
    }

    public function getType(): ?string
    {
        return $this->getAttribute('type');
    }

    public function getCount(): int
    {
        return (int) $this->getAttribute('count', 0);
    }

    /** @return array<string, mixed> */
    public function getInvolvedObject(): array
    {
        return (array) $this->getAttribute('involvedObject', []);
    }

    public function isWarning(): bool
    {
        return $this->getType() === 'Warning';
    }

    public function isNormal(): bool
    {
        return $this->getType() === 'Normal';
    }

    public function getFirstTimestamp(): ?Carbon
    {
        $time = $this->getAttribute('firstTimestamp');

        return is_string($time) ? Carbon::parse($time) : null;
    }

    public function getLastTimestamp(): ?Carbon
    {
        $time = $this->getAttribute('lastTimestamp');

        return is_string($time) ? Carbon::parse($time) : null;
    }
}
