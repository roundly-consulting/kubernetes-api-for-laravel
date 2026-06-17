<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources\Types;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Traits\Conditionable;
use RoundlyConsulting\KubernetesApi\Traits\Makeable;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAttributes;

/**
 * @method static setPropagationPolicy(string $policy)
 *
 * @phpstan-consistent-constructor
 *
 * @implements Arrayable<string, mixed>
 */
class Type implements Arrayable, Jsonable
{
    use Conditionable;
    use HasAttributes;
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
        return collect($this->attributes)
            ->sortKeys()
            ->all();
    }

    public function toJson($options = JSON_THROW_ON_ERROR): string
    {
        return (string) json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }
}
