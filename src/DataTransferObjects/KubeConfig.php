<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\DataTransferObjects;

use SensitiveParameter;

/**
 * A resolved kubeconfig context: the apiserver URL plus the credentials and
 * trust material needed to reach it. PEM data is materialised to temp files by
 * the loader, since the HTTP client expects file paths.
 *
 * `tokenFile` is a bearer-token file read again for every request (a rotated
 * service-account token, a kubeconfig `tokenFile`); `token` is its content at load
 * time, used while the file cannot be read. `namespace` is the kubeconfig context's
 * namespace, if it names one.
 */
final readonly class KubeConfig
{
    public function __construct(
        public string $server,
        #[SensitiveParameter]
        public ?string $token = null,
        public ?string $clientCertificatePath = null,
        public ?string $clientKeyPath = null,
        public ?string $certificateAuthorityPath = null,
        public bool $verify = true,
        public ?string $tokenFile = null,
        public ?string $namespace = null,
    ) {}

    public function hasClientCertificate(): bool
    {
        return $this->clientCertificatePath !== null && $this->clientKeyPath !== null;
    }
}
