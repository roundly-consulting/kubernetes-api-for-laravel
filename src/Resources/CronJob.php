<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;

class CronJob extends Resource
{
    use HasSpec;
    use HasStatus;

    protected string $kind = 'CronJob';

    protected string $version = 'batch/v1';

    protected bool $usesNamespaces = true;

    public function setSchedule(string $schedule): static
    {
        return $this->setSpec('schedule', $schedule);
    }

    public function getSchedule(): ?string
    {
        return $this->getSpec('schedule');
    }

    public function suspend(bool $suspend = true): static
    {
        return $this->setSpec('suspend', $suspend);
    }

    public function isSuspended(): bool
    {
        return (bool) $this->getSpec('suspend', false);
    }

    public function setConcurrencyPolicy(string $policy): static
    {
        return $this->setSpec('concurrencyPolicy', $policy);
    }

    public function getConcurrencyPolicy(): string
    {
        return $this->getSpec('concurrencyPolicy', 'Allow');
    }

    public function setSuccessfulJobsHistoryLimit(int $limit): static
    {
        return $this->setSpec('successfulJobsHistoryLimit', $limit);
    }

    public function getSuccessfulJobsHistoryLimit(): int
    {
        return (int) $this->getSpec('successfulJobsHistoryLimit', 3);
    }

    public function setFailedJobsHistoryLimit(int $limit): static
    {
        return $this->setSpec('failedJobsHistoryLimit', $limit);
    }

    public function getFailedJobsHistoryLimit(): int
    {
        return (int) $this->getSpec('failedJobsHistoryLimit', 1);
    }

    /**
     * Use the job's `metadata` and `spec` as the job template. A JobTemplateSpec has
     * no `apiVersion`/`kind`, and a strict apiserver refuses them, so they are left out.
     */
    public function setJobTemplate(Job $job): static
    {
        return $this->setSpec('jobTemplate', Arr::only($job->toArray(), ['metadata', 'spec']));
    }

    public function getJobTemplate(): Job
    {
        return Job::make((array) $this->getSpec('jobTemplate', []));
    }

    public function getLastScheduleTime(): ?Carbon
    {
        $time = $this->getStatus('lastScheduleTime');

        return is_string($time) ? Carbon::parse($time) : null;
    }

    public function getLastSuccessfulTime(): ?Carbon
    {
        $time = $this->getStatus('lastSuccessfulTime');

        return is_string($time) ? Carbon::parse($time) : null;
    }

    /**
     * The job template is copied as a plain array, so the job's string-keyed maps (and
     * its pod template's) are listed under `spec.jobTemplate` to stay JSON objects.
     *
     * @return list<string>
     */
    protected function objectAttributes(): array
    {
        return [
            ...parent::objectAttributes(),
            ...array_map(static fn (string $path): string => 'spec.jobTemplate.'.$path, Job::make()->objectAttributes()),
        ];
    }
}
