<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Support;

use RoundlyConsulting\KubernetesApi\Exceptions\IntegrationGuardException;

/**
 * Hard safety guard for the live integration suite. It refuses to run against
 * anything other than a local OrbStack cluster, so a production context (e.g.
 * the user's default `cm-aks` AKS cluster, or any `*-aks` context) can never be
 * targeted by a test workload.
 *
 * A run is permitted only when ALL hold:
 *  - the opt-in flag `K8S_INTEGRATION=1` is set;
 *  - the active context is exactly `orbstack`;
 *  - the apiserver host is loopback (127.0.0.1 / ::1 / localhost) or `*.orb.local`.
 */
final class IntegrationGuard
{
    public const ALLOWED_CONTEXT = 'orbstack';

    public function __construct(
        private readonly ?string $flag,
        private readonly string $context,
        private readonly string $server,
    ) {}

    public function enabled(): bool
    {
        return $this->flag === '1' || $this->flag === 'true';
    }

    /**
     * @throws IntegrationGuardException when the flag is set but the target is
     *                                   not a local OrbStack cluster.
     */
    public function assertSafe(): void
    {
        if ($this->context !== self::ALLOWED_CONTEXT) {
            throw new IntegrationGuardException(
                "Refusing to run integration tests against context '{$this->context}'. ".
                "Only the '".self::ALLOWED_CONTEXT."' context is allowed."
            );
        }

        if (! $this->hostIsLocal()) {
            throw new IntegrationGuardException(
                "Refusing to run integration tests against apiserver '{$this->server}'. ".
                'The apiserver host must be loopback or *.orb.local.'
            );
        }
    }

    public function hostIsLocal(): bool
    {
        $host = (string) parse_url($this->server, PHP_URL_HOST);

        if ($host === '') {
            return false;
        }

        if (in_array($host, ['127.0.0.1', '::1', 'localhost'], true)) {
            return true;
        }

        return str_ends_with($host, '.orb.local');
    }
}
