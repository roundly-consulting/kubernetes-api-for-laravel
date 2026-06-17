<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\DataTransferObjects;

/**
 * A resolved kubeconfig context: the apiserver URL plus the credentials and
 * trust material needed to reach it. PEM data is materialised to temp files by
 * the loader, since the HTTP client expects file paths.
 */
final readonly class KubeConfig
{
    public function __construct(
        public string $server,
        public ?string $token = null,
        public ?string $clientCertificatePath = null,
        public ?string $clientKeyPath = null,
        public ?string $certificateAuthorityPath = null,
        public bool $verify = true,
    ) {}

    public function hasClientCertificate(): bool
    {
        return $this->clientCertificatePath !== null && $this->clientKeyPath !== null;
    }
}
