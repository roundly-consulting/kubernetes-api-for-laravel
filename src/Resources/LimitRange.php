<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;

class LimitRange extends Resource
{
    use HasSpec;

    protected string $kind = 'LimitRange';

    protected bool $usesNamespaces = true;

    /** @param array<string, mixed> $limit */
    public function addLimit(array $limit): static
    {
        return $this->addToSpec('limits', $limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function getLimits(): array
    {
        return (array) $this->getSpec('limits', []);
    }
}
