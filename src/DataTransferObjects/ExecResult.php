<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\DataTransferObjects;

/**
 * The outcome of a pod `exec` call: the captured stdout/stderr and the
 * command's exit code (0 on success). The exit code is derived from the
 * Kubernetes error channel status, defaulting to 0 when the status is
 * `Success`.
 */
final readonly class ExecResult
{
    public function __construct(
        public string $stdout,
        public string $stderr,
        public int $exitCode,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }
}
