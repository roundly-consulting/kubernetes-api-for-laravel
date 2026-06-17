<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\DataTransferObjects;

/**
 * Options for fetching or streaming a pod's logs, mapped to the query
 * parameters of the `/log` subresource.
 */
final readonly class PodLogOptions
{
    public function __construct(
        public ?string $container = null,
        public bool $follow = false,
        public ?int $tailLines = null,
        public ?int $sinceSeconds = null,
        public bool $timestamps = false,
        public bool $previous = false,
        public ?int $limitBytes = null,
    ) {}

    /** @return array<string, int|string> */
    public function toQuery(): array
    {
        $query = [];

        if ($this->container !== null) {
            $query['container'] = $this->container;
        }

        if ($this->follow) {
            $query['follow'] = 'true';
        }

        if ($this->tailLines !== null) {
            $query['tailLines'] = $this->tailLines;
        }

        if ($this->sinceSeconds !== null) {
            $query['sinceSeconds'] = $this->sinceSeconds;
        }

        if ($this->timestamps) {
            $query['timestamps'] = 'true';
        }

        if ($this->previous) {
            $query['previous'] = 'true';
        }

        if ($this->limitBytes !== null) {
            $query['limitBytes'] = $this->limitBytes;
        }

        return $query;
    }
}
