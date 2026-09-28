<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use Generator;
use Illuminate\Http\Client\Response;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubernetesPatch;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\ResourcePage;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\WatchEvent;
use RoundlyConsulting\KubernetesApi\Enums\PatchType;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Http\StreamLines;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\ResourcesCollection;
use RoundlyConsulting\KubernetesApi\Resources\Types\Type;

trait ExecutesClusterOperations
{
    use HasCluster;
    use HasClusterPaths;
    use HasListing;

    /**
     * The field manager a server-side apply falls back to when the cluster has no
     * manager name (`withManagerName()` / `clusters.<name>.manager`).
     */
    public const string DEFAULT_FIELD_MANAGER = 'kubernetes-api-for-laravel';

    protected bool $dryRun = false;

    /** @param array<string, mixed> $query */
    public function get(array $query = ['pretty' => 1]): ResourcesCollection
    {
        return $this->getPage($query)->items;
    }

    /** @param array<string, mixed> $query */
    public function getPage(array $query = ['pretty' => 1]): ResourcePage
    {
        $response = $this->request(
            method: 'GET',
            path: $this->getResourceListingPath($this->listsAllNamespaces()),
            query: $this->listingQuery($query),
            payload: $this->payload(),
        );

        $items = (array) $response->json('items', []);

        $collection = ResourcesCollection::make(
            items: collect($items)->map(
                /** @param array<string, mixed> $item */
                fn (array $item): Resource => $this->newInstance($item)->markAsExisting(),
            )
        );

        $continue = $response->json('metadata.continue');
        $remaining = $response->json('metadata.remainingItemCount');

        return new ResourcePage(
            items: $collection,
            continue: is_string($continue) && $continue !== '' ? $continue : null,
            remainingItemCount: is_numeric($remaining) ? (int) $remaining : null,
        );
    }

    /**
     * Lazily iterate every matching resource, transparently following the
     * apiserver's `continue` tokens to load further pages on demand.
     *
     * @param  array<string, mixed>  $query
     * @return Generator<int, resource>
     */
    public function lazy(array $query = ['pretty' => 1]): Generator
    {
        $token = $this->continueToken;

        do {
            $page = $this->continueFrom($token)->getPage($query);

            foreach ($page->items as $item) {
                yield $item;
            }

            $token = $page->continue;
        } while ($token !== null && $token !== '');
    }

    /**
     * @param  callable(resource): mixed  $callback
     * @param  array<string, mixed>  $query
     */
    public function each(callable $callback, array $query = ['pretty' => 1]): void
    {
        foreach ($this->lazy($query) as $item) {
            $callback($item);
        }
    }

    /** @param array<string, mixed> $query */
    public function find(array $query = ['pretty' => 1]): static
    {
        $response = $this->request(
            method: 'GET',
            path: $this->getResourcePath(),
            query: $query,
            payload: $this->payload(),
        );

        return $this->newInstance((array) $response->json())->markAsExisting();
    }

    /** @param array<string, mixed> $query */
    public function create(array $query = ['pretty' => 1]): static
    {
        $response = $this->request(
            method: 'POST',
            path: $this->getResourceListingPath(),
            query: $this->withFieldManager($this->withDryRun($query)),
            payload: $this->payload(),
        );

        return $this
            ->newInstance((array) $response->json())
            ->markAsRecentlyCreated()
            ->markAsExisting();
    }

    /** @param array<string, mixed> $query */
    public function update(array $query = ['pretty' => 1]): static
    {
        $response = $this->request(
            method: 'PUT',
            path: $this->getResourcePath(),
            query: $this->withFieldManager($this->withDryRun($query)),
            payload: $this->payload(),
        );

        return $this
            ->newInstance((array) $response->json())
            ->markAsExisting();
    }

    /**
     * Update the resource if it exists, otherwise create it. The current
     * server `resourceVersion` is carried into the update so the `PUT` does
     * optimistic concurrency (surfacing a 409 conflict) rather than silently
     * clobbering changes made between the existence check and the write.
     *
     * @param  array<string, mixed>  $query
     */
    public function updateOrCreate(array $query = ['pretty' => 1]): static
    {
        try {
            $existing = $this->find($query);
        } catch (KubernetesException $e) {
            if ($e->response->notFound()) {
                return $this->create($query);
            }

            throw $e;
        }

        $this->carryResourceVersion($existing);

        return $this->update($query);
    }

    /**
     * Patch the resource. A server-side apply (`KubernetesPatch::apply()`) always
     * carries a `fieldManager`, and `force=true` when the patch asks for it.
     *
     * @param  array<string, mixed>  $query
     */
    public function patch(KubernetesPatch $patch, array $query = ['pretty' => 1]): static
    {
        $query = $this->withFieldManager($this->withDryRun($query), required: $patch->type === PatchType::Apply);

        if ($patch->forces()) {
            $query['force'] = 'true';
        }

        $response = $this->request(
            method: 'PATCH',
            path: $this->getResourcePath(),
            query: $query,
            payload: $patch->encode(),
            contentType: $patch->type->contentType(),
        );

        return $this
            ->newInstance((array) $response->json())
            ->markAsExisting();
    }

    /** @param array<string, mixed> $query */
    public function delete(array $query = ['pretty' => 1], ?Type $options = null): static
    {
        if (! $options) {
            $options = Type::make()->setPropagationPolicy('Foreground');
        }

        $options->setAttribute('kind', 'DeleteOptions')
            ->setAttribute('apiVersion', 'v1');

        $response = $this->request(
            method: 'DELETE',
            path: $this->getResourcePath(),
            query: $this->withDryRun($query),
            payload: $this->payload($options),
        );

        return $this
            ->newInstance((array) $response->json())
            ->markAsExisting(false);
    }

    /** @param array<string, mixed> $query */
    public function existsOnCluster(array $query = ['pretty' => 1]): bool
    {
        try {
            $this->find($query);
        } catch (KubernetesException $e) {
            if ($e->response->notFound()) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /** @param array<string, mixed> $query */
    public function missingOnCluster(array $query = ['pretty' => 1]): bool
    {
        return ! $this->existsOnCluster($query);
    }

    /**
     * Append the `dryRun=All` query parameter to subsequent writes, so the
     * apiserver validates the request without persisting it.
     */
    public function dryRun(bool $dryRun = true): static
    {
        $this->dryRun = $dryRun;

        return $this;
    }

    /**
     * Stream a watch over the listing, invoking the callback with a
     * {@see WatchEvent} for every change until the connection ends — the apiserver
     * closes a watch after its own timeout, or the idle
     * `kubernetes.client.stream_timeout` passes (none by default).
     *
     * @param  callable(WatchEvent): mixed  $onEvent
     * @param  array<string, mixed>  $query
     */
    public function watch(callable $onEvent, array $query = []): void
    {
        $query = array_merge($query, ['watch' => 1]);

        $response = $this->request(
            method: 'GET',
            path: $this->getResourceListingPath($this->listsAllNamespaces()),
            query: $this->listingQuery($query),
            payload: $this->payload(),
            stream: true,
        );

        // Only complete lines are events: a partial one left by an idle timeout is dropped.
        foreach (StreamLines::of($response->toPsrResponse()->getBody(), withTrailing: false) as $line) {
            if (trim($line) === '') {
                continue;
            }

            /** @var array{type?: string, object?: array<string, mixed>} $decoded */
            $decoded = (array) json_decode($line, true);

            $onEvent(new WatchEvent(
                type: is_string($decoded['type'] ?? null) ? $decoded['type'] : 'UNKNOWN',
                object: $this->newInstance((array) ($decoded['object'] ?? []))->markAsExisting(),
            ));
        }
    }

    /**
     * Carry the server's current `resourceVersion` from a freshly-fetched copy
     * into this resource so a subsequent `PUT` does optimistic concurrency.
     * A locally-set version is never overwritten.
     */
    protected function carryResourceVersion(Resource $current): void
    {
        if ($this->getAttribute('metadata.resourceVersion') !== null) {
            return;
        }

        $version = $current->getAttribute('metadata.resourceVersion');

        if (is_string($version)) {
            $this->setAttribute('metadata.resourceVersion', $version);
        }
    }

    /**
     * Name the field manager `managedFields` records for a write: the cluster's manager
     * name. A server-side apply cannot go without one, so there it falls back to the
     * package's own name. A `fieldManager` already in the query wins.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function withFieldManager(array $query, bool $required = false): array
    {
        $manager = $this->requireCluster()->getManagerName();

        if ($manager === null || $manager === '') {
            $manager = $required ? self::DEFAULT_FIELD_MANAGER : null;
        }

        if ($manager !== null) {
            $query['fieldManager'] ??= $manager;
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function withDryRun(array $query): array
    {
        if ($this->dryRun) {
            $query['dryRun'] = 'All';
        }

        return $query;
    }

    /**
     * Send the request through the bound cluster, which authenticates, rate limits
     * and throws a `KubernetesException` for a failed response (or hands the call
     * to `Kubernetes::fake()`).
     *
     * @param  array<string, mixed>  $query
     */
    protected function request(
        string $method,
        string $path,
        array $query,
        string $payload,
        ?string $contentType = null,
        bool $stream = false,
    ): Response {
        return $this->requireCluster()->request($method, $path, $query, $payload, $contentType, $stream);
    }

    /**
     * A sibling instance for a server payload: same cluster, same default namespace
     * and — for a scoped resource — the same namespace pin, so nothing a scoped
     * listing returns can be re-pointed outside the scope.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newInstance(array $attributes = []): static
    {
        $instance = (new static($attributes))
            ->setDefaultNamespace($this->defaultNamespace)
            ->setCluster($this->getCluster());

        if ($this->namespaceScope !== null) {
            $instance->scopeToNamespace($this->namespaceScope);
        }

        return $instance;
    }

    protected function payload(Resource|Type|null $value = null): string
    {
        if (! $value) {
            $value = $this;
        }

        return $value->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
