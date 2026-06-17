<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Enums;

/**
 * The patch strategies supported by the Kubernetes API, keyed by the
 * `Content-Type` header each one requires.
 */
enum PatchType: string
{
    case StrategicMerge = 'application/strategic-merge-patch+json';

    case Merge = 'application/merge-patch+json';

    case Json = 'application/json-patch+json';

    case Apply = 'application/apply-patch+yaml';

    public function contentType(): string
    {
        return $this->value;
    }
}
