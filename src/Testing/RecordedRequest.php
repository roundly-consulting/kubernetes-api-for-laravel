<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Testing;

use Illuminate\Support\Arr;

/**
 * One request `Kubernetes::fake()` received. Handed to every assertion closure.
 */
final readonly class RecordedRequest
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<array-key, mixed>  $body  the decoded JSON body (empty for bodiless requests)
     * @param  list<string>  $command  the exec command, for {@see RequestVerb::Exec}
     */
    public function __construct(
        public ?string $cluster,
        public string $method,
        public string $path,
        public RequestVerb $verb,
        public ?string $apiVersion = null,
        public ?string $plural = null,
        public ?string $namespace = null,
        public ?string $name = null,
        public ?string $subresource = null,
        public array $query = [],
        public array $body = [],
        public ?string $contentType = null,
        public array $command = [],
        public ?string $container = null,
    ) {}

    /**
     * Whether the request carried `dryRun=All` — validated by the apiserver, never
     * persisted. Dry runs never satisfy `assertCreated()` and friends.
     */
    public function isDryRun(): bool
    {
        return ($this->query['dryRun'] ?? null) === 'All';
    }

    /**
     * A dotted read from the decoded body (`spec.replicas`).
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->body, $key, $default);
    }

    /**
     * Whether this is the template-annotation patch `rolloutRestart()` sends.
     */
    public function isRolloutRestart(): bool
    {
        return $this->verb === RequestVerb::Patch
            && $this->subresource === null
            && is_array($this->body['spec']['template']['metadata']['annotations'] ?? null)
            && array_key_exists('kubectl.kubernetes.io/restartedAt', $this->body['spec']['template']['metadata']['annotations']);
    }
}
