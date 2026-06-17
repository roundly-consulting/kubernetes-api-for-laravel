<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits;

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

    public function withToken(?string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function withCertificate(?string $pathToCertificate): self
    {
        $this->pathToCertificate = $pathToCertificate;

        return $this;
    }

    public function withPrivateKey(?string $pathToPrivateKey): self
    {
        $this->pathToPrivateKey = $pathToPrivateKey;

        return $this;
    }

    public function withCaCertificate(?string $pathToCaCertificate): self
    {
        $this->pathToCaCertificate = $pathToCaCertificate;

        return $this;
    }

    public function withoutSslVerification(): self
    {
        $this->verify = false;

        return $this;
    }

    public function withSslVerification(): self
    {
        $this->verify = true;

        return $this;
    }
}
