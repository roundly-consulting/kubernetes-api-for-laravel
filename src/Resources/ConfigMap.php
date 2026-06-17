<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use Illuminate\Support\Arr;

class ConfigMap extends Resource
{
    protected string $kind = 'ConfigMap';

    protected bool $usesNamespaces = true;

    public function getData(?string $key = null, ?string $default = null): mixed
    {
        $data = $this->getAttribute('data', []);

        if (! $key) {
            return $data;
        }

        return Arr::get($data, $key, $default);
    }

    /** @param array<string, string> $data */
    public function setData(array $data): static
    {
        return $this->setAttribute('data', $data);
    }

    public function addData(string $name, string $value): static
    {
        return $this->setAttribute("data.{$name}", $value);
    }

    public function removeData(string $name): static
    {
        return $this->removeAttribute("data.{$name}");
    }
}
