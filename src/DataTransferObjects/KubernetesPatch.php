<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\DataTransferObjects;

use JsonException;
use RoundlyConsulting\KubernetesApi\Enums\PatchType;

/**
 * A patch request to apply to a Kubernetes resource. The body is encoded as
 * JSON for every strategy (server-side apply accepts a JSON body under the
 * `apply-patch+yaml` content type, since JSON is valid YAML).
 */
final readonly class KubernetesPatch
{
    /** @param array<string, mixed>|list<array<string, mixed>> $body */
    public function __construct(
        public PatchType $type,
        public array $body,
    ) {}

    /** @param array<string, mixed> $body */
    public static function strategicMerge(array $body): self
    {
        return new self(PatchType::StrategicMerge, $body);
    }

    /** @param array<string, mixed> $body */
    public static function merge(array $body): self
    {
        return new self(PatchType::Merge, $body);
    }

    /** @param list<array<string, mixed>> $operations */
    public static function json(array $operations): self
    {
        return new self(PatchType::Json, $operations);
    }

    /** @param array<string, mixed> $body */
    public static function apply(array $body): self
    {
        return new self(PatchType::Apply, $body);
    }

    /** @throws JsonException */
    public function encode(): string
    {
        return json_encode($this->body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
