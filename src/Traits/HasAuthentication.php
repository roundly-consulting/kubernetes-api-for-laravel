<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits;

use SensitiveParameter;

/**
 * Cluster credentials. Every `with*()` / `without*()` returns a new instance and leaves
 * the receiver untouched, so a shared client can never be re-credentialed.
 */
trait HasAuthentication
{
    protected ?string $token = null;

    protected ?string $tokenFile = null;

    protected ?string $pathToCertificate = null;

    protected ?string $pathToPrivateKey = null;

    protected ?string $pathToCaCertificate = null;

    protected bool $verify = true;

    public function hasToken(): bool
    {
        return ! is_null($this->getToken());
    }

    /**
     * The bearer token: the token file's current content when one is set (read on
     * every call, so a rotated token is picked up), otherwise — or while the file
     * cannot be read — the token itself.
     */
    public function getToken(): ?string
    {
        if ($this->tokenFile !== null && is_file($this->tokenFile) && is_readable($this->tokenFile)) {
            $token = trim((string) file_get_contents($this->tokenFile));

            if ($token !== '') {
                return $token;
            }
        }

        return $this->token;
    }

    public function getTokenFile(): ?string
    {
        return $this->tokenFile;
    }

    public function hasPathToCertificate(): bool
    {
        return ! is_null($this->pathToCertificate);
    }

    public function getPathToCertificate(): ?string
    {
        return $this->pathToCertificate;
    }

    public function hasPathToPrivateKey(): bool
    {
        return ! is_null($this->getPathToPrivateKey());
    }

    public function getPathToPrivateKey(): ?string
    {
        return $this->pathToPrivateKey;
    }

    public function hasPathToCaCertificate(): bool
    {
        return ! is_null($this->getPathToCaCertificate());
    }

    public function getPathToCaCertificate(): ?string
    {
        return $this->pathToCaCertificate;
    }

    public function shouldVerify(): bool
    {
        return $this->verify;
    }

    /**
     * A fixed bearer token. It replaces a token file, so the token given is the one sent.
     */
    public function withToken(#[SensitiveParameter] ?string $token): static
    {
        $clone = clone $this;
        $clone->token = $token;
        $clone->tokenFile = null;

        return $clone;
    }

    /**
     * A bearer-token file, read again for every request — a projected service-account
     * token the kubelet rotates, or a kubeconfig `tokenFile`. Any token already set stays
     * as the fallback while the file cannot be read.
     */
    public function withTokenFile(?string $path): static
    {
        $clone = clone $this;
        $clone->tokenFile = $path === '' ? null : $path;

        return $clone;
    }

    public function withCertificate(?string $pathToCertificate): static
    {
        $clone = clone $this;
        $clone->pathToCertificate = $pathToCertificate;

        return $clone;
    }

    public function withPrivateKey(?string $pathToPrivateKey): static
    {
        $clone = clone $this;
        $clone->pathToPrivateKey = $pathToPrivateKey;

        return $clone;
    }

    public function withCaCertificate(?string $pathToCaCertificate): static
    {
        $clone = clone $this;
        $clone->pathToCaCertificate = $pathToCaCertificate;

        return $clone;
    }

    public function withoutSslVerification(): static
    {
        $clone = clone $this;
        $clone->verify = false;

        return $clone;
    }

    public function withSslVerification(): static
    {
        $clone = clone $this;
        $clone->verify = true;

        return $clone;
    }
}
