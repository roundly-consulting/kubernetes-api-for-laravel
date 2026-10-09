<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Conditionable;
use RoundlyConsulting\KubernetesApi\Traits\Makeable;
use RoundlyConsulting\KubernetesApi\Traits\Resource\ExecutesClusterOperations;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAnnotations;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAttributes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasCluster;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasClusterPaths;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasExistence;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasKind;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasLabels;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasListing;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasName;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasNamespace;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasVersion;

/**
 * @phpstan-consistent-constructor
 *
 * @implements Arrayable<string, mixed>
 */
class Resource implements Arrayable, Jsonable
{
    use Conditionable;
    use ExecutesClusterOperations;
    use HasAnnotations;
    use HasAttributes;
    use HasCluster;
    use HasClusterPaths;
    use HasExistence;
    use HasKind;
    use HasLabels;
    use HasListing;
    use HasName;
    use HasNamespace;
    use HasVersion;
    use Makeable;

    /**
     * @codeCoverageIgnore
     */
    public function dump(): void
    {
        dump($this->toArray());
    }

    /**
     * @codeCoverageIgnore
     */
    public function dd(): void
    {
        dd($this->toArray());
    }

    /**
     * The manifest, always with `kind` and `apiVersion` — filled in on the copy, so
     * serialising never changes the resource (or makes it dirty).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $attributes = $this->attributes;
        $attributes['kind'] = $this->getKind();
        $attributes['apiVersion'] = $this->getVersion();

        return collect($attributes)
            ->sortKeys()
            ->all();
    }

    public function toJson($options = JSON_THROW_ON_ERROR): string
    {
        $payload = $this->toArray();

        // PHP turns numeric-string keys ("0", "1") into a list, which would encode as a
        // JSON list where the apiserver expects a `map[string]…` (400): these maps go
        // out as objects whatever their keys.
        foreach ($this->objectAttributes() as $path) {
            $value = Arr::get($payload, $path);

            if (is_array($value) && $value !== [] && array_is_list($value)) {
                Arr::set($payload, $path, (object) $value);
            }
        }

        return (string) json_encode($payload, $options | JSON_THROW_ON_ERROR);
    }

    /**
     * The attribute paths that hold string-keyed maps, encoded as JSON objects even when
     * their keys are numeric strings.
     *
     * @return list<string>
     */
    protected function objectAttributes(): array
    {
        return ['metadata.labels', 'metadata.annotations'];
    }
}
