<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use Illuminate\Support\Arr;
use RoundlyConsulting\KubernetesApi\Resources\Types\EmptyObject;

trait HasLabels
{
    use HasAttributes;

    /** @param array<string, string> $labels */
    public function setLabels(array $labels): static
    {
        // An empty map goes out as `{}`: `[]` is a JSON list, which the apiserver refuses.
        $this->setAttribute('metadata.labels', $labels === [] ? new EmptyObject : $labels);

        return $this;
    }

    /** @return array<string, string> */
    public function getLabels(): array
    {
        return $this->getAttribute('metadata.labels', []);
    }

    public function setLabel(string $label, string $value): static
    {
        $labels = $this->getLabels();
        $labels[$label] = $value;

        return $this->setLabels($labels);
    }

    public function getLabel(string $name, mixed $default = null): mixed
    {
        return $this->getLabels()[$name] ?? $default;
    }

    public function removeLabel(string $name): static
    {
        return $this->setLabels(
            Arr::except($this->getLabels(), $name)
        );
    }
}
