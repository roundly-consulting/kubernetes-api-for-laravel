<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits;

trait HasUrl
{
    protected string $url = '';

    /**
     * A copy pointed at the given apiserver URL; the receiver is left untouched.
     */
    public function url(string $url): static
    {
        $clone = clone $this;
        $clone->url = $url;

        return $clone;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
