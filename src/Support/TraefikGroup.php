<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Support;

use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * @internal The API group/version the bundled Traefik resources target.
 */
final class TraefikGroup
{
    /**
     * `kubernetes.traefik.group`, or the resource's bundled `$default` when it is
     * not set — absent, null or blank. A non-string value throws
     * InvalidConfigurationException instead of being ignored, so a broken override
     * never silently targets the wrong API group.
     */
    public static function resolve(string $default): string
    {
        return ConfigValue::isSet(config('kubernetes.traefik.group'))
            ? Config::requireString('kubernetes.traefik.group')
            : $default;
    }
}
