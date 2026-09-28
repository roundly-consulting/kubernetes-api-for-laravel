<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\DataTransferObjects;

/**
 * The apiserver's build information, as served by `/version`.
 */
final readonly class VersionInfo
{
    public function __construct(
        public string $major,
        public string $minor,
        public string $gitVersion,
        public string $gitCommit = '',
        public string $gitTreeState = '',
        public string $buildDate = '',
        public string $goVersion = '',
        public string $compiler = '',
        public string $platform = '',
    ) {}

    /**
     * Map the `/version` payload. Missing fields become empty strings rather than
     * failing — distributions differ in what they report.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload): self
    {
        $field = static fn (string $key): string => is_scalar($payload[$key] ?? null) ? (string) $payload[$key] : '';

        return new self(
            major: $field('major'),
            minor: $field('minor'),
            gitVersion: $field('gitVersion'),
            gitCommit: $field('gitCommit'),
            gitTreeState: $field('gitTreeState'),
            buildDate: $field('buildDate'),
            goVersion: $field('goVersion'),
            compiler: $field('compiler'),
            platform: $field('platform'),
        );
    }

    /**
     * Build the info a fake apiserver reports from a `vMAJOR.MINOR.PATCH` string.
     */
    public static function fromGitVersion(string $gitVersion): self
    {
        preg_match('/^v?(\d+)\.(\d+)/', $gitVersion, $matches);

        return new self(
            major: $matches[1] ?? '',
            minor: $matches[2] ?? '',
            gitVersion: $gitVersion,
        );
    }
}
