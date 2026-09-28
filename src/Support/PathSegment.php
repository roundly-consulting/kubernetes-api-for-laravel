<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Support;

use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;

/**
 * @internal Validates every value that becomes a segment of an apiserver URL path.
 *
 * Names are the one segment that routinely comes from outside the code (a form, a
 * webhook, a queue payload), and curl collapses `..` segments, so an unchecked
 * `../../kube-system/secrets/x` would walk a namespace-scoped client into another
 * namespace. A name must therefore be a single path segment by the apiserver's own
 * rule (not `.`/`..`, no `/` or `%`), and is percent-encoded on top. Namespaces,
 * plurals and apiVersions have fixed DNS-style grammars and are checked against them.
 */
final class PathSegment
{
    private const DNS_LABEL = '[a-z0-9](?:[-a-z0-9]*[a-z0-9])?';

    /**
     * An object name as one encoded path segment. RBAC names such as
     * `system:controller:job` are path-segment names rather than DNS names, so the
     * check is the apiserver's routing rule, not a DNS grammar.
     *
     * @throws InvalidResourceException
     */
    public static function name(mixed $name): string
    {
        if ($name === null || $name === '') {
            throw InvalidResourceException::missingName();
        }

        if (! is_string($name)
            || strlen($name) > 253
            || $name === '.'
            || $name === '..'
            || preg_match('/[\/%\s\x00-\x1F\x7F]/', $name) === 1) {
            throw InvalidResourceException::invalidSegment('resource name', $name);
        }

        return str_replace(['%3A', '%40'], [':', '@'], rawurlencode($name));
    }

    /**
     * A namespace: a DNS-1123 label of at most 63 characters.
     *
     * @throws InvalidResourceException
     */
    public static function namespace(string $namespace): string
    {
        if (strlen($namespace) > 63 || preg_match('/^'.self::DNS_LABEL.'$/', $namespace) !== 1) {
            throw InvalidResourceException::invalidSegment('namespace', $namespace);
        }

        return $namespace;
    }

    /**
     * A REST plural (`deployments`, `ingressroutes`) or subresource (`scale`, `log`).
     *
     * @throws InvalidResourceException
     */
    public static function plural(string $plural): string
    {
        if (strlen($plural) > 63 || preg_match('/^'.self::DNS_LABEL.'$/', $plural) !== 1) {
            throw InvalidResourceException::invalidSegment('resource plural', $plural);
        }

        return $plural;
    }

    /**
     * `v1` or `<group>/<version>`, the group being a DNS subdomain.
     *
     * @throws InvalidResourceException
     */
    public static function apiVersion(string $apiVersion): string
    {
        $label = self::DNS_LABEL;

        if (strlen($apiVersion) > 317 || preg_match("/^(?:{$label}(?:\\.{$label})*\\/)?{$label}$/", $apiVersion) !== 1) {
            throw InvalidResourceException::invalidSegment('apiVersion', $apiVersion);
        }

        return $apiVersion;
    }
}
