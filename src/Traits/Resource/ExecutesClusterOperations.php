<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\ResourcesCollection;
use RoundlyConsulting\KubernetesApi\Resources\Types\Type;

trait ExecutesClusterOperations
{
    use HasCluster;
    use HasClusterPaths;

    /** @param array<string, mixed> $query */
    public function get(array $query = ['pretty' => 1]): ResourcesCollection
    {
        $response = $this->request(
            method: 'GET',
            path: $this->getResourceListingPath(),
            query: $query,
            payload: $this->payload(),
        );

        $items = (array) $response->json('items', []);

        return ResourcesCollection::make(
            items: collect($items)->map(
                /** @param array<string, mixed> $item */
                fn (array $item): Resource => $this->newInstance($item)->markAsExisting(),
            )
        );
    }

    /** @param array<string, mixed> $query */
    public function find(array $query = ['pretty' => 1]): Resource
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
    public function create(array $query = ['pretty' => 1]): Resource
    {
        $response = $this->request(
            method: 'POST',
            path: $this->getResourceListingPath(),
            query: $query,
            payload: $this->payload(),
        );

        return $this
            ->newInstance((array) $response->json())
            ->markAsRecentlyCreated()
            ->markAsExisting();
    }

    /** @param array<string, mixed> $query */
    public function update(array $query = ['pretty' => 1]): Resource
    {
        $response = $this->request(
            method: 'PUT',
            path: $this->getResourcePath(),
            query: $query,
            payload: $this->payload(),
        );

        return $this
            ->newInstance((array) $response->json())
            ->markAsExisting();
    }

    /** @param array<string, mixed> $query */
    public function updateOrCreate(array $query = ['pretty' => 1]): Resource
    {
        if ($this->existsOnCluster($query)) {
            return $this->update($query);
        }

        return $this->create($query);
    }

    /** @param array<string, mixed> $query */
    public function delete(array $query = ['pretty' => 1], ?Type $options = null): Resource
    {
        if (! $options) {
            $options = Type::make()->setPropagationPolicy('Foreground');
        }

        $options->setAttribute('kind', 'DeleteOptions')
            ->setAttribute('apiVersion', 'v1');

        $response = $this->request(
            method: 'DELETE',
            path: $this->getResourcePath(),
            query: $query,
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
    protected function request(string $method, string $path, array $query, string $payload): Response
    {
        try {
            $cluster = $this->getCluster();

            $request = Http::baseUrl($cluster->getUrl())
                ->throw()
                ->withUserAgent($cluster->getManagerName())
                ->withHeaders(['Accept-Encoding' => 'gzip, deflate']);

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

            return $request
                ->withOptions((array) config('kubernetes.client.options', []))
                ->withBody($payload)
                ->send($method, "{$path}?{$this->getQueryString($query)}");
        } catch (RequestException $e) {
            $message = $e->response->json('message');

            throw new KubernetesException(
                $e->response,
                is_string($message) ? $message : null,
            );
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

        return str($value->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
            ->replace(': []', ': {}')
            ->toString();
    }
}
