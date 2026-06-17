<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use RoundlyConsulting\KubernetesApi\Resources\Types\EmptyObject;

trait HasSelectors
{
    use HasSpec;

    /** @param array<string, string> $selectors */
    public function setSelectors(array $selectors): static
    {
        // An empty selector map must serialise to the empty object `{}` (which
        // Kubernetes treats as "no selector"), never to `[]`, which the
        // apiserver rejects where a map is expected.
        if ($selectors === []) {
            return $this->setSpec('selector', new EmptyObject);
        }

        return $this->setSpec('selector', $selectors);
    }

    public function addSelector(string $selector, string $value): static
    {
        return $this->addToSpec('selector', [$selector => $value], false);
    }

    /** @return array<string, string> */
    public function getSelectors(): array
    {
        $selector = $this->getSpec('selector', []);

        // An empty selector is stored as an EmptyObject marker so it serialises
        // to `{}`; surface it to callers as the empty array they set.
        if ($selector instanceof EmptyObject) {
            return [];
        }

        return $selector;
    }

    /**
     * Set a `LabelSelector.matchLabels` map at the given spec path. An empty map
     * is written as the empty object `{}` so it serialises correctly (an empty
     * `matchLabels` matches every pod); a non-empty map is written as-is.
     *
     * @param  array<string, string>  $labels
     */
    protected function setMatchLabelsSelector(string $path, array $labels): static
    {
        if ($labels === []) {
            return $this->setSpec($path, new EmptyObject);
        }

        return $this->setSpec("{$path}.matchLabels", $labels);
    }
}
