<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use Illuminate\Support\Arr;
use RoundlyConsulting\KubernetesApi\Resources\Types\EmptyObject;

trait HasAnnotations
{
    use HasAttributes;

    /** @param array<string, string> $annotations */
    public function setAnnotations(array $annotations): static
    {
        // An empty map goes out as `{}`: `[]` is a JSON list, which the apiserver refuses.
        $this->setAttribute('metadata.annotations', $annotations === [] ? new EmptyObject : $annotations);

        return $this;
    }

    /** @return array<string, string> */
    public function getAnnotations(): array
    {
        return $this->getAttribute('metadata.annotations', []);
    }

    public function setAnnotation(string $annotation, string $value): static
    {
        $annotations = $this->getAnnotations();
        $annotations[$annotation] = $value;

        return $this->setAnnotations($annotations);
    }

    public function getAnnotation(string $name, mixed $default = null): mixed
    {
        return $this->getAnnotations()[$name] ?? $default;
    }

    public function removeAnnotation(string $name): static
    {
        return $this->setAnnotations(
            Arr::except($this->getAnnotations(), $name)
        );
    }
}
