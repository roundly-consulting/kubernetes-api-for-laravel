<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Testing;

use Closure;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\ExecResult;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\VersionInfo;
use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;
use RoundlyConsulting\KubernetesApi\KubernetesManager;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Support\ResourceRegistry;

/**
 * What `Kubernetes::fake()` installs: the real manager API over an in-memory apiserver.
 *
 * Every cluster the manager hands out — named, default, ad-hoc, namespace-scoped — and
 * every resource bound to one sends its requests here instead of the network. Objects
 * you `seed()` can be listed, found, updated, patched, scaled and deleted; creates
 * persist; a missing object is a real 404 and a stale `resourceVersion` a real 409.
 * Cluster definitions still run (so their names, namespaces and manager names apply),
 * but `fromKubeConfig()` / `inCluster()` never read credentials.
 */
final class KubernetesFake extends KubernetesManager
{
    private readonly FakeTransport $server;

    public function __construct(Container $container, ?KubernetesManager $from = null)
    {
        parent::__construct($container);

        $this->server = new FakeTransport($this->defaultClusterName(...));
        $this->transport = $this->server;

        if ($from !== null) {
            $this->definitions = $from->definitions;
        }
    }

    /**
     * Put objects on the fake cluster, as though they had been created there. A
     * namespaced item without `metadata.namespace` lands in the target cluster's default
     * namespace — where `create()` through that cluster would put it.
     *
     * @param  string  $resource  a resource class or a registered name (`'pods'`)
     * @param  list<array<string, mixed>|resource>  $items  manifests or resource objects
     * @param  string|null  $cluster  the cluster name; the default cluster when null
     */
    public function seed(string $resource, array $items, ?string $cluster = null): self
    {
        $class = $this->resourceClass($resource);
        $target = $this->cluster($cluster);

        foreach ($items as $item) {
            $object = $item instanceof Resource ? clone $item : $target->resource($class)->setAttributes($item);

            if ($object->namespaceScope() === null) {
                $object->setDefaultNamespace($target->defaultNamespace());
            }

            $name = $object->getAttribute('metadata.name');

            if (! is_string($name) || $name === '') {
                throw new InvalidResourceException("Every {$class} seeded into the fake needs metadata.name.");
            }

            $namespace = $object->usesNamespaces() ? $object->getNamespace() : null;
            $manifest = $object->toArray();

            if ($namespace !== null) {
                $manifest['metadata']['namespace'] = $namespace;
            }

            $this->server->put($cluster, $object->getVersion(), $object->getPluralKind(), $namespace, $name, $manifest);
        }

        return $this;
    }

    /**
     * What `$pod->logs()` / `streamLogs()` return for a pod.
     */
    public function seedLogs(string $pod, string $logs, string $namespace = 'default', ?string $cluster = null): self
    {
        $this->server->putLogs($cluster, $namespace, $pod, $logs);

        return $this;
    }

    /**
     * What `$pod->exec()` returns — a fixed result, or a closure given the recorded request.
     *
     * @param  ExecResult|Closure(RecordedRequest): ExecResult  $result
     */
    public function stubExec(ExecResult|Closure $result): self
    {
        $this->server->answerExec($result);

        return $this;
    }

    /**
     * What `/version` (and so `version()` / `ping()`) reports. Defaults to v1.34.0.
     */
    public function stubVersion(VersionInfo|string $version): self
    {
        $this->server->answerVersion(is_string($version) ? VersionInfo::fromGitVersion($version) : $version);

        return $this;
    }

    /**
     * Make every request fail with a connection error, as an unreachable apiserver
     * would: `ping()` reports false, everything else throws `ConnectionException`.
     */
    public function unreachable(bool $unreachable = true): self
    {
        $this->server->setUnreachable($unreachable);

        return $this;
    }

    /**
     * Every request received, optionally filtered.
     *
     * @param  (Closure(RecordedRequest): bool)|null  $filter
     * @return list<RecordedRequest>
     */
    public function recorded(?Closure $filter = null): array
    {
        $recorded = $this->server->recorded();

        return $filter === null ? $recorded : array_values(array_filter($recorded, $filter));
    }

    /** @param Closure(RecordedRequest): bool $callback */
    public function assertSent(Closure $callback): void
    {
        Assert::assertNotEmpty($this->recorded($callback), 'No matching Kubernetes request was sent.');
    }

    public function assertNothingSent(): void
    {
        $count = count($this->recorded());

        Assert::assertSame(0, $count, "Expected no Kubernetes request, but {$count} were sent.");
    }

    /**
     * @param  string  $resource  a resource class or a registered name
     * @param  string|(Closure(RecordedRequest): bool)|null  $constraint  the object name, or a closure
     */
    public function assertCreated(string $resource, string|Closure|null $constraint = null): void
    {
        $this->assertVerb(RequestVerb::Create, 'created', $resource, $constraint);
    }

    public function assertNothingCreated(): void
    {
        $this->assertNoVerb(RequestVerb::Create, 'created');
    }

    /**
     * @param  string|(Closure(RecordedRequest): bool)|null  $constraint
     */
    public function assertUpdated(string $resource, string|Closure|null $constraint = null): void
    {
        $this->assertVerb(RequestVerb::Update, 'updated', $resource, $constraint);
    }

    public function assertNothingUpdated(): void
    {
        $this->assertNoVerb(RequestVerb::Update, 'updated');
    }

    /**
     * Any PATCH of the object — `patch()`, `scale()` and `rolloutRestart()` included.
     *
     * @param  string|(Closure(RecordedRequest): bool)|null  $constraint
     */
    public function assertPatched(string $resource, string|Closure|null $constraint = null): void
    {
        $this->assertVerb(RequestVerb::Patch, 'patched', $resource, $constraint);
    }

    public function assertNothingPatched(): void
    {
        $this->assertNoVerb(RequestVerb::Patch, 'patched');
    }

    public function assertScaled(string $resource, string $name, ?int $replicas = null): void
    {
        $this->assertVerb(RequestVerb::Patch, $replicas === null ? 'scaled' : "scaled to {$replicas}", $resource, static fn (RecordedRequest $request): bool => $request->name === $name
            && $request->subresource === 'scale'
            && ($replicas === null || $request->input('spec.replicas') === $replicas), $name);
    }

    public function assertNothingScaled(): void
    {
        $scaled = $this->mutations(RequestVerb::Patch, static fn (RecordedRequest $request): bool => $request->subresource === 'scale');

        Assert::assertSame([], $scaled, 'Expected nothing to be scaled, but '.count($scaled).' scale request(s) were sent.');
    }

    public function assertRestarted(string $resource, string $name): void
    {
        $this->assertVerb(RequestVerb::Patch, 'rollout-restarted', $resource, static fn (RecordedRequest $request): bool => $request->name === $name
            && $request->isRolloutRestart(), $name);
    }

    public function assertNothingRestarted(): void
    {
        $restarted = $this->mutations(RequestVerb::Patch, static fn (RecordedRequest $request): bool => $request->isRolloutRestart());

        Assert::assertSame([], $restarted, 'Expected no rollout restart, but '.count($restarted).' were sent.');
    }

    /**
     * @param  string|(Closure(RecordedRequest): bool)|null  $constraint
     */
    public function assertDeleted(string $resource, string|Closure|null $constraint = null): void
    {
        $this->assertVerb(RequestVerb::Delete, 'deleted', $resource, $constraint);
    }

    public function assertNothingDeleted(): void
    {
        $this->assertNoVerb(RequestVerb::Delete, 'deleted');
    }

    /**
     * @param  list<string>|null  $command  the exact command, or null for any
     */
    public function assertExecuted(string $pod, ?array $command = null): void
    {
        $matches = $this->recorded(static fn (RecordedRequest $request): bool => $request->verb === RequestVerb::Exec
            && $request->name === $pod
            && ($command === null || $request->command === $command));

        $expected = $command === null ? '' : ' `'.implode(' ', $command).'`';

        Assert::assertNotEmpty($matches, "Expected{$expected} to be executed in pod '{$pod}', but it was not.");
    }

    public function assertNothingExecuted(): void
    {
        $this->assertNoVerb(RequestVerb::Exec, 'executed');
    }

    protected function loadKubeConfig(?string $path, ?string $context): KubeConfig
    {
        return new KubeConfig(server: '');
    }

    protected function loadInClusterConfig(): KubeConfig
    {
        return new KubeConfig(server: '');
    }

    /**
     * @param  string|(Closure(RecordedRequest): bool)|null  $constraint
     */
    private function assertVerb(RequestVerb $verb, string $past, string $resource, string|Closure|null $constraint, ?string $name = null): void
    {
        $class = $this->resourceClass($resource);
        $target = new $class;
        $apiVersion = $target->getVersion();
        $plural = $target->getPluralKind();

        $matches = $this->mutations($verb, static fn (RecordedRequest $request): bool => $request->apiVersion === $apiVersion
            && $request->plural === $plural
            && match (true) {
                $constraint === null => true,
                is_string($constraint) => $request->name === $constraint,
                default => $constraint($request),
            });

        $name ??= is_string($constraint) ? $constraint : null;
        $described = $name === null ? $class : "{$class} '{$name}'";

        Assert::assertNotEmpty($matches, "Expected {$described} to be {$past}, but it was not.");
    }

    private function assertNoVerb(RequestVerb $verb, string $past): void
    {
        $matches = $this->mutations($verb);

        Assert::assertSame([], $matches, "Expected nothing to be {$past}, but ".count($matches).' request(s) were.');
    }

    /**
     * Real (non-dry-run) requests of one verb.
     *
     * @param  (Closure(RecordedRequest): bool)|null  $filter
     * @return list<RecordedRequest>
     */
    private function mutations(RequestVerb $verb, ?Closure $filter = null): array
    {
        return $this->recorded(static fn (RecordedRequest $request): bool => $request->verb === $verb
            && ! $request->isDryRun()
            && ($filter === null || $filter($request)));
    }

    /**
     * @return class-string<resource>
     *
     * @throws InvalidResourceException
     */
    private function resourceClass(string $resource): string
    {
        if (is_a($resource, Resource::class, true)) {
            return $resource;
        }

        return ResourceRegistry::classFor($resource) ?? throw InvalidResourceException::unknown($resource);
    }
}
