<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasKind
{
    use HasAttributes;

    protected string $kind = '';

    /**
     * The REST plural for this resource. When null, the plural is derived from
     * the kind. Set it explicitly for irregular kinds (e.g. Endpoints,
     * NetworkPolicy) or CRDs whose `spec.names.plural` differs from the naive
     * pluralisation.
     */
    protected ?string $plural = null;

    public function getKind(): string
    {
        return $this->getAttribute('kind', $this->kind);
    }

    public function getPluralKind(): string
    {
        if ($this->plural !== null) {
            return $this->plural;
        }

        return str($this->getKind())
            ->lower()
            ->plural()
            ->toString();
    }

    /**
     * @deprecated Misspelled alias of getPluralKind(); use getPluralKind() instead.
     */
    public function getPlurarKind(): string
    {
        return $this->getPluralKind();
    }

    public function setPlural(?string $plural): static
    {
        $this->plural = $plural;

        return $this;
    }

    public function setKind(string $kind): static
    {
        return $this->setAttribute('kind', $kind);
    }
}
