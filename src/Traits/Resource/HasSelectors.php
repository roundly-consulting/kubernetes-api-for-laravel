<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasSelectors
{
    use HasSpec;

    /** @param array<string, string> $selectors */
    public function setSelectors(array $selectors): static
    {
        return $this->setSpec('selector', $selectors);
    }

    public function addSelector(string $selector, string $value): static
    {
        return $this->addToSpec('selector', [$selector => $value], false);
    }

    /** @return array<string, string> */
    public function getSelectors(): array
    {
        return $this->getSpec('selector', []);
    }
}
