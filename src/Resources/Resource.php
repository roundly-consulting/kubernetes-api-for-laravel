<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        // Force kind and version to be filled to correctly resolve API endpoint
        $this->setKind($this->getKind());
        $this->setVersion($this->getVersion());

        return collect($this->attributes)
            ->sortKeys()
            ->all();
    }

    public function toJson($options = JSON_THROW_ON_ERROR): string
    {
        return (string) json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }
}
