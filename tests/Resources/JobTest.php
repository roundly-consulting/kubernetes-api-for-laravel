<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Job;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasTemplate;

it('extends resource class', function () {
    expect(Job::class)->toUse([
        Resource::class,
        HasSpec::class,
        HasStatus::class,
        HasSelectors::class,
        HasTemplate::class,
    ]);
});

it('has correct kind', function () {
    expect(Job::make()->getKind())->toBe('Job');
});

it('has correct version', function () {
    expect(Job::make()->getVersion())->toBe('batch/v1');
});

it('uses namespaces', function () {
    expect(Job::make()->usesNamespaces())->toBeTrue();
});

it('sets and gets ttl seconds after finished', function () {
    $job = Job::make();

    expect($job->getTtl())->toBeNull();

    $job->setTtl(250);

    expect($job)
        ->getTtl()->toBe(250)
        ->getSpec('ttlSecondsAfterFinished')->toBe(250);
});

it('builds pods selectors from job name', function () {
    $job = Job::make()->setName('import-users');

    expect($job->podsSelectors())->toBe([
        'job-name' => 'import-users',
    ]);
});

it('returns pod counts from status with defaults', function () {
    $job = Job::make([
        'status' => [
            'active' => 2,
            'failed' => 1,
            'succeeded' => 5,
        ],
    ]);

    expect($job)
        ->getActivePodsCount()->toBe(2)
        ->getFailedPodsCount()->toBe(1)
        ->getSucceededPodsCount()->toBe(5);

    expect(Job::make())
        ->getActivePodsCount()->toBe(0)
        ->getFailedPodsCount()->toBe(0)
        ->getSucceededPodsCount()->toBe(0);
});

it('keeps the misspelled getSuccededPodsCount alias for back-compat', function () {
    $job = Job::make(['status' => ['succeeded' => 7]]);

    expect($job->getSuccededPodsCount())->toBe(7)
        ->and($job->getSucceededPodsCount())->toBe(7);
});

it('returns start and completion times as carbon instances', function () {
    $job = Job::make([
        'status' => [
            'startTime' => '2023-03-28 11:39:23',
            'completionTime' => '2023-03-28 11:41:23',
        ],
    ]);

    expect($job->getStartTime()->format('d.m.Y H:i:s'))->toBe('28.03.2023 11:39:23');
    expect($job->getCompletionTime()->format('d.m.Y H:i:s'))->toBe('28.03.2023 11:41:23');
});

it('returns null times when status is missing', function () {
    $job = Job::make();

    expect($job)
        ->getStartTime()->toBeNull()
        ->getCompletionTime()->toBeNull();
});

it('computes duration in seconds between start and completion', function () {
    $job = Job::make([
        'status' => [
            'startTime' => '2023-03-28 11:39:23',
            'completionTime' => '2023-03-28 11:41:23',
        ],
    ]);

    expect($job->getDurationInSeconds())->toBe(120);
});

it('returns zero duration when timestamps are absent', function () {
    expect(Job::make()->getDurationInSeconds())->toBe(0);
});

it('returns status message', function () {
    $job = Job::make([
        'status' => [
            'message' => 'Job completed successfully',
        ],
    ]);

    expect($job->getStatusMessage())->toBe('Job completed successfully');
    expect(Job::make()->getStatusMessage())->toBeNull();
});

it('reports whether the job has completed', function () {
    expect(Job::make()->hasCompleted())->toBeFalse();

    $completed = Job::make([
        'status' => [
            'completionTime' => '2023-03-28 11:41:23',
        ],
    ]);

    expect($completed->hasCompleted())->toBeTrue();
});
