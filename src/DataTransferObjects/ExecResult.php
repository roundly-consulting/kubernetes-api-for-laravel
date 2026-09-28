<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\DataTransferObjects;

/**
 * The outcome of a pod `exec` call: the captured stdout/stderr and the
 * command's exit code, read from the status the apiserver sends when the
 * command finishes (0 on success). The exit code is null when the stream ended
 * before that status arrived — a dropped connection or the idle
 * `kubernetes.client.stream_timeout` — so the output may be truncated and the
 * command's outcome is unknown.
 */
final readonly class ExecResult
{
    public function __construct(
        public string $stdout,
        public string $stderr,
        public ?int $exitCode,
    ) {}

    /**
     * The command finished with exit code 0.
     */
    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * The command finished and the apiserver reported its exit code; false when the
     * stream ended early.
     */
    public function completed(): bool
    {
        return $this->exitCode !== null;
    }
}
