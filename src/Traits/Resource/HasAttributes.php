<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;

trait HasAttributes
{
    use Macroable {
        Macroable::__call as macroableCall;
    }

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, mixed> */
    protected array $original = [];

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->original = $attributes;
    }

    public function setAttribute(string $name, mixed $value): static
    {
        Arr::set($this->attributes, $name, $value);

        return $this;
    }

    public function addToAttribute(string $name, mixed $value, bool $wrap = true): static
    {
        return $this->setAttribute(
            name: $name,
            value: array_merge(
                (array) $this->getAttribute($name, []),
                $wrap ? [$value] : $value,
            ),
        );
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return Arr::get($this->attributes, $name, $default);
    }

    /** @param string|array<int, string> $name */
    public function removeAttribute(string|array $name): static
    {
        Arr::forget($this->attributes, $name);

        return $this;
    }

    /** @param array<string, mixed> $attributes */
    public function setAttributes(array $attributes = []): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function removeAttributes(): static
    {
        return $this->setAttributes();
    }

    public function isDirty(): bool
    {
        return $this->attributes !== $this->original;
    }

    public function sync(): static
    {
        $this->original = $this->attributes;

        return $this;
    }

    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        if (! $key) {
            return $this->original;
        }

        return Arr::get($this->original, $key, $default);
    }

    public function discardChanges(): static
    {
        $this->attributes = $this->original;

        return $this;
    }

    /** @param array<int, mixed> $parameters */
    public function __call(string $method, array $parameters): mixed
    {
        $normalizeAttributeName = fn (string $replace): string => Str::camel(
            str_replace($replace, '', $method)
        );

        if (Str::startsWith($method, 'set')) {
            $attribute = $normalizeAttributeName('set');

            return $this->setAttribute($attribute, $parameters[0] ?? null);
        }

        if (Str::startsWith($method, 'with')) {
            $method = str_replace('with', 'set', $method);

            return $this->$method(...$parameters);
        }

        if (Str::startsWith($method, 'addTo')) {
            $attribute = $normalizeAttributeName('addTo');

            return $this->addToAttribute($attribute, $parameters[0] ?? null);
        }

        if (Str::startsWith($method, 'getOriginal')) {
            $attribute = $normalizeAttributeName('getOriginal');

            return $this->getOriginal($attribute, $parameters[0] ?? null);
        }

        if (Str::startsWith($method, 'get')) {
            $attribute = $normalizeAttributeName('get');

            return $this->getAttribute($attribute, $parameters[0] ?? null);
        }

        if (Str::startsWith($method, 'remove')) {
            $attribute = $normalizeAttributeName('remove');

            return $this->removeAttribute($attribute);
        }

        return $this->macroableCall($method, $parameters);
    }
}
