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

        if (! $key) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    /** @param array<string, string> $data */
    public function setData(array $data): static
    {
        return $this->setAttribute('data', $data);
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

        return $this->setAttribute('data', $data);
    }
}
