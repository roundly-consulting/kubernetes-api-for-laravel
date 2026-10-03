<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Support;

/**
 * @internal Blank means not set. A host's `KEY=` arrives as `''`, which the toolkit's
 *           readers already treat exactly like an absent key; the optional settings
 *           read here (the default cluster, a cluster's connection strings, the Traefik
 *           group, the request timeout, the rate-limit owner, `max_wait` / `jitter`)
 *           follow the same rule, so a blank value takes its documented default rather
 *           than throwing.
 */
final class ConfigValue
{
    /**
     * Whether a raw config value is set: not null, and not a blank string (empty or
     * whitespace only).
     */
    public static function isSet(mixed $value): bool
    {
        return $value !== null && ! (is_string($value) && trim($value) === '');
    }
}
