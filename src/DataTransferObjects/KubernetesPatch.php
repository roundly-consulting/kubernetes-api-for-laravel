<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\DataTransferObjects;

use JsonException;
use RoundlyConsulting\KubernetesApi\Enums\PatchType;

/**
 * A patch request to apply to a Kubernetes resource. The body is encoded as
 * JSON for every strategy (server-side apply accepts a JSON body under the
 * `apply-patch+yaml` content type, since JSON is valid YAML).
 *
 * `force` only means something to a server-side apply — it takes over fields another
 * manager owns instead of answering 409 — and is never sent with another type (the
 * apiserver forbids it there).
 */
final readonly class KubernetesPatch
{
    /** @param array<string, mixed>|list<array<string, mixed>> $body */
    public function __construct(
        public PatchType $type,
        public array $body,
        public bool $force = false,
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

    /**
     * A server-side apply of a (partial) manifest. The apiserver requires a field
     * manager for it: the cluster's manager name, else `kubernetes-api-for-laravel`.
     *
     * @param  array<string, mixed>  $body
     * @param  bool  $force  take over conflicting fields owned by another manager
     */
    public static function apply(array $body, bool $force = false): self
    {
        return new self(PatchType::Apply, $body, $force);
    }

    /**
     * Whether `force=true` goes on the request — only ever for a server-side apply.
     */
    public function forces(): bool
    {
        return $this->force && $this->type === PatchType::Apply;
    }

    /** @throws JsonException */
    public function encode(): string
    {
        return json_encode($this->body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
