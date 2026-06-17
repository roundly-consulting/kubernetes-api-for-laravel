<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasSpec
{
    use HasAttributes;

    public function setSpec(string $name, mixed $value): static
    {
        return $this->setAttribute("spec.{$name}", $value);
    }

    public function addToSpec(string $name, mixed $value, bool $wrap = true): static
    {
        return $this->addToAttribute("spec.{$name}", $value, $wrap);
    }

    public function getSpec(string $name, mixed $default = null): mixed
    {
        return $this->getAttribute("spec.{$name}", $default);
    }

    public function removeSpec(string $name): static
    {
        return $this->removeAttribute("spec.{$name}");
    }
}
