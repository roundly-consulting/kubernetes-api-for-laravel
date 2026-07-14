<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Exceptions;

use RoundlyConsulting\PackageToolkit\Concerns\ProvidesRetryAfter;
use RoundlyConsulting\PackageToolkit\Contracts\HasRetryAfter;
use RuntimeException;

/**
 * Thrown when a cluster request would wait longer than the configured
 * `max_wait` ceiling, so callers can fail fast instead of blocking.
 *
 * Unlike {@see KubernetesException}, this carries no HTTP response: the request
 * was never sent because the client-side budget was exhausted. The retry hint
 * rides on the toolkit's HasRetryAfter contract, so a host can turn any
 * rate-limited failure into a `Retry-After` header without knowing about this
 * package.
 */
final class RateLimitExceededException extends RuntimeException implements HasRetryAfter
{
    use ProvidesRetryAfter;

    public function __construct(
        string $message,
        public readonly string $cluster,
    ) {
        parent::__construct($message);
    }

    public static function for(string $cluster, int $retryAfterSeconds): self
    {
        $exception = new self(
            "Rate limit for cluster [{$cluster}] exceeded. Retry in {$retryAfterSeconds} second(s).",
            $cluster,
        );

        return $exception->withRetryAfter($retryAfterSeconds);
    }
}
