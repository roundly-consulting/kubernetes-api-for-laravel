<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Exceptions;

use RuntimeException;

/**
 * Thrown when a cluster request would wait longer than the configured
 * `max_wait` ceiling, so callers can fail fast instead of blocking.
 *
 * Unlike {@see KubernetesException}, this carries no HTTP response: the request
 * was never sent because the client-side budget was exhausted.
 */
final class RateLimitExceededException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $cluster,
        public readonly int $availableInSeconds,
    ) {
        parent::__construct($message);
    }

    public static function for(string $cluster, int $availableInSeconds): self
    {
        return new self(
            "Rate limit for cluster [{$cluster}] exceeded. Retry in {$availableInSeconds} second(s).",
            $cluster,
            $availableInSeconds,
        );
    }
}
