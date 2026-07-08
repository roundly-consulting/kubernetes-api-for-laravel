<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Enums;

use Illuminate\Support\Str;
use RoundlyConsulting\Enums\Helpers;

/**
 * The patch strategies supported by the Kubernetes API, keyed by the
 * `Content-Type` header each one requires.
 */
enum PatchType: string
{
    use Helpers;

    case StrategicMerge = 'application/strategic-merge-patch+json';

    case Merge = 'application/merge-patch+json';

    case Json = 'application/json-patch+json';

    case Apply = 'application/apply-patch+yaml';

    public function contentType(): string
    {
        return $this->value;
    }

    /**
     * Human-friendly label derived from the case NAME rather than the backing
     * value — the value is a MIME `Content-Type`, so the trait's value-based
     * label would read as nonsense. The wire value is never touched.
     */
    public function readable(): string
    {
        return (string) __(Str::headline($this->name));
    }
}
