<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\WebSocket;

use RoundlyConsulting\KubernetesApi\DataTransferObjects\ExecResult;

/**
 * Demultiplexes the Kubernetes `v4.channel.k8s.io` exec stream. Each message
 * is prefixed with a single channel byte: 0 = stdin, 1 = stdout, 2 = stderr,
 * 3 = error/status. The error channel carries a JSON `Status` object from
 * which the command's exit code is read.
 */
final class ExecStreamParser
{
    public const CHANNEL_STDOUT = 1;

    public const CHANNEL_STDERR = 2;

    public const CHANNEL_ERROR = 3;

    private string $stdout = '';

    private string $stderr = '';

    private string $error = '';

    /**
     * Feed one demultiplexed message (channel byte + payload) into the parser.
     */
    public function feed(string $message): void
    {
        if ($message === '') {
            return;
        }

        $channel = ord($message[0]);
        $payload = substr($message, 1);

        match ($channel) {
            self::CHANNEL_STDOUT => $this->stdout .= $payload,
            self::CHANNEL_STDERR => $this->stderr .= $payload,
            self::CHANNEL_ERROR => $this->error .= $payload,
            default => null,
        };
    }

    public function result(): ExecResult
    {
        return new ExecResult(
            stdout: $this->stdout,
            stderr: $this->stderr,
            exitCode: $this->exitCode(),
        );
    }

    /**
     * Resolve the command exit code from the accumulated error-channel status.
     * `Success` (or an empty channel) maps to 0; a non-zero exit code is read
     * from the `ExitCode` cause in the status details.
     */
    public function exitCode(): int
    {
        if (trim($this->error) === '') {
            return 0;
        }

        /** @var array<string, mixed> $status */
        $status = (array) json_decode($this->error, true);

        if (($status['status'] ?? null) === 'Success') {
            return 0;
        }

        $details = $status['details'] ?? [];
        $causes = is_array($details) ? ($details['causes'] ?? []) : [];

        if (is_array($causes)) {
            foreach ($causes as $cause) {
                if (is_array($cause) && ($cause['reason'] ?? null) === 'ExitCode') {
                    return (int) ($cause['message'] ?? 1);
                }
            }
        }

        return 1;
    }
}
