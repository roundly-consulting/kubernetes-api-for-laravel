<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use Illuminate\Support\Arr;

class Secret extends Resource
{
    protected string $kind = 'Secret';

    protected bool $usesNamespaces = true;

    /** @return array<string, string>|string|null */
    public function getData(?string $key = null, ?string $default = null): null|string|array
    {
        $data = $this->getAttribute('data', []);

        foreach ($data as $dataKey => &$value) {
            $value = base64_decode($value, true);
        }

        if (! $key) {
            return $data;
        }

        return Arr::get($data, $key, $default);
    }

    /** @param array<string, string> $data */
    public function setData(array $data): static
    {
        foreach ($data as $key => &$value) {
            $value = base64_encode($value);
        }

        return $this->setAttribute('data', $data);
    }

    public function addData(string $name, string $value): static
    {
        return $this->setAttribute("data.{$name}", base64_encode($value));
    }

    public function removeData(string $name): static
    {
        return $this->removeAttribute("data.{$name}");
    }
}
