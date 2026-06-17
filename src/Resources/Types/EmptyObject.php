<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources\Types;

use JsonSerializable;
use stdClass;

/**
 * Marker for a Kubernetes payload node that must serialise to an empty JSON
 * object (`{}`) rather than an empty array (`[]`). Kubernetes rejects `[]`
 * where an object is expected (e.g. `spec: {}`), so use this instead of an
 * empty PHP array for those nodes.
 */
final class EmptyObject implements JsonSerializable
{
    public function jsonSerialize(): stdClass
    {
        return new stdClass;
    }
}
