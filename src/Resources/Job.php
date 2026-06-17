<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use Illuminate\Support\Carbon;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasTemplate;

class Job extends Resource
{
    use HasSelectors;
    use HasSpec;
    use HasStatus;
    use HasTemplate;

    protected string $kind = 'Job';

    protected string $version = 'batch/v1';

    protected bool $usesNamespaces = true;

    public function setTtl(int $ttl = 100): static
    {
        return $this->setSpec('ttlSecondsAfterFinished', $ttl);
    }

    public function getTtl(): ?int
    {
        return $this->getSpec('ttlSecondsAfterFinished');
    }

    /** @return array<string, string> */
    public function podsSelectors(): array
    {
        return [
            'job-name' => $this->getName(),
        ];
    }

    public function getActivePodsCount(): int
    {
        return $this->getStatus('active', 0);
    }

    public function getFailedPodsCount(): int
    {
        return $this->getStatus('failed', 0);
    }

    public function getSuccededPodsCount(): int
    {
        return $this->getStatus('succeeded', 0);
    }

    public function getStartTime(): ?Carbon
    {
        $time = $this->getStatus('startTime');

        return $time ? Carbon::parse($time) : null;
    }

    public function getCompletionTime(): ?Carbon
    {
        $time = $this->getStatus('completionTime');

        return $time ? Carbon::parse($time) : null;
    }

    public function getDurationInSeconds(): int
    {
        $startTime = $this->getStartTime();
        $completionTime = $this->getCompletionTime();

        return (int) ($startTime?->diffInSeconds($completionTime) ?: 0);
    }

    public function getStatusMessage(): ?string
    {
        return $this->getStatus('message');
    }

    public function hasCompleted(): bool
    {
        return ! is_null($this->getCompletionTime());
    }
}
