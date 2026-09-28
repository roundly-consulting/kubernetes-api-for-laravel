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

    protected ?string $pathToCertificate = null;

    protected ?string $pathToPrivateKey = null;

    protected ?string $pathToCaCertificate = null;

    protected bool $verify = true;

    public function hasToken(): bool
    {
        return ! is_null($this->getToken());
    }

    public function getToken(): ?string
    {
        return $this->token;
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

    public function withToken(#[SensitiveParameter] ?string $token): static
    {
        $clone = clone $this;
        $clone->token = $token;

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
