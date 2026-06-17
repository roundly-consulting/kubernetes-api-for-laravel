<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubernetesPatch;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\ResourcePage;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\WatchEvent;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\ResourcesCollection;
use RoundlyConsulting\KubernetesApi\Resources\Types\Type;

trait ExecutesClusterOperations
{
    use HasCluster;
    use HasClusterPaths;
    use HasListing;

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
            query: $this->withDryRun($query),
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
            query: $this->withDryRun($query),
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

    /** @param array<string, mixed> $query */
    public function patch(KubernetesPatch $patch, array $query = ['pretty' => 1]): static
    {
        $response = $this->request(
            method: 'PATCH',
            path: $this->getResourcePath(),
            query: $this->withDryRun($query),
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
     * {@see WatchEvent} for every change until the connection ends.
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

        $body = $response->toPsrResponse()->getBody();

        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(8192);

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newline);
                $buffer = substr($buffer, $newline + 1);

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
        try {
            $cluster = $this->getCluster();

            $request = Http::baseUrl($cluster->getUrl())
                ->throw()
                ->withUserAgent($cluster->getManagerName())
                ->withHeaders(['Accept-Encoding' => 'gzip, deflate']);

            if ($stream) {
                $request->withOptions(['stream' => true]);
            }

            $this->applyAuthentication($request);

            $request->withOptions((array) config('kubernetes.client.options', []));

            if ($contentType !== null) {
                $request->withBody($payload, $contentType);
            } else {
                $request->withBody($payload);
            }

            return $request->send($method, "{$path}?{$this->getQueryString($query)}");
        } catch (RequestException $e) {
            $message = $e->response->json('message');

            throw new KubernetesException(
                $e->response,
                is_string($message) ? $message : null,
            );
        }
    }

    protected function applyAuthentication(PendingRequest $request): void
    {
        $cluster = $this->getCluster();

        if ($cluster->shouldVerify()) {
            $request->withOptions([
                'verify' => $cluster->hasPathToCaCertificate() ? $cluster->getPathToCaCertificate() : true,
            ]);
        } else {
            $request->withoutVerifying();
        }

        if ($cluster->hasToken()) {
            $request->withToken($cluster->getToken());
        }

        if ($cluster->hasPathToCertificate()) {
            $request->withOptions(['cert' => $cluster->getPathToCertificate()]);
        }

        if ($cluster->hasPathToPrivateKey()) {
            $request->withOptions(['ssl_key' => $cluster->getPathToPrivateKey()]);
        }
    }

    /** @param array<string, mixed> $attributes */
    public function newInstance(array $attributes = []): static
    {
        return (new static($attributes))->setCluster($this->getCluster());
    }

    /** @param array<string, mixed> $query */
    protected function getQueryString(array $query): string
    {
        return urldecode(
            (string) preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', http_build_query($query))
        );
    }

    protected function payload(Resource|Type|null $value = null): string
    {
        if (! $value) {
            $value = $this;
        }

        return $value->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
