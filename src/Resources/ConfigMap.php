<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

/**
 * Data keys are file names (`nginx.conf`, `app.env`), so they are always handled as
 * flat keys — never as dotted attribute paths.
 */
class ConfigMap extends Resource
{
    protected string $kind = 'ConfigMap';

    protected bool $usesNamespaces = true;

    public function getData(?string $key = null, ?string $default = null): mixed
    {
        $data = (array) $this->getAttribute('data', []);

        // Only null means the whole map: "0" is a valid key.
        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    /**
     * Replace the data. An empty map removes the field: `[]` would encode as a JSON
     * list, which the apiserver refuses for `data`.
     *
     * @param  array<string, string>  $data
     */
    public function setData(array $data): static
    {
        return $data === [] ? $this->removeAttribute('data') : $this->setAttribute('data', $data);
    }

    public function addData(string $name, string $value): static
    {
        $data = (array) $this->getAttribute('data', []);
        $data[$name] = $value;

        return $this->setAttribute('data', $data);
    }

    public function removeData(string $name): static
    {
        $data = (array) $this->getAttribute('data', []);
        unset($data[$name]);

        // An empty map would encode as the JSON list `[]`, which the apiserver
        // refuses for `data`; with no keys left the field goes altogether.
        return $data === []
            ? $this->removeAttribute('data')
            : $this->setAttribute('data', $data);
    }

    /** @return list<string> */
    protected function objectAttributes(): array
    {
        return [...parent::objectAttributes(), 'data', 'binaryData'];
    }
}
