<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Testing;

/**
 * What a request recorded by `Kubernetes::fake()` did.
 */
enum RequestVerb: string
{
    case Version = 'version';
    case List = 'list';
    case Watch = 'watch';
    case Get = 'get';
    case Logs = 'logs';
    case Create = 'create';
    case Update = 'update';
    case Patch = 'patch';
    case Delete = 'delete';
    case Exec = 'exec';
    case Unknown = 'unknown';

    /**
     * Whether the verb changes cluster state (or runs code in it).
     */
    public function mutates(): bool
    {
        return in_array($this, [self::Create, self::Update, self::Patch, self::Delete, self::Exec], true);
    }
}
