<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Support;

use RoundlyConsulting\KubernetesApi\Resources\Types\EmptyObject;
use stdClass;

/**
 * @internal Decodes an apiserver JSON body into resource attributes without losing
 *           the difference between `{}` and `[]`.
 *
 * The apiserver sends empty structs as `{}` (`resources`, `securityContext`,
 * `emptyDir`, `status`, …). A plain associative decode turns them into `[]`, which
 * encodes back as a JSON list — and a real apiserver refuses the whole object on the
 * next `update()`. Here every empty object becomes an {@see EmptyObject} marker, which
 * encodes back to `{}`; attribute getters still read it as an empty array.
 */
final class JsonPayload
{
    /**
     * @return array<array-key, mixed> the decoded object, or [] for anything else
     */
    public static function decode(string $json): array
    {
        $decoded = self::normalize(json_decode($json, false));

        return is_array($decoded) ? $decoded : [];
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);

            return $properties === [] ? new EmptyObject : array_map(self::normalize(...), $properties);
        }

        if (is_array($value)) {
            return array_map(self::normalize(...), $value);
        }

        return $value;
    }
}
