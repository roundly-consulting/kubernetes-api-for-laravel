<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

trait HasListing
{
    /** @var list<string> */
    protected array $labelSelectors = [];

    /** @var list<string> */
    protected array $fieldSelectors = [];

    protected ?int $limit = null;

    protected ?string $continueToken = null;

    protected bool $allNamespaces = false;

    public function whereLabel(string $key, string $value): static
    {
        $this->labelSelectors[] = "{$key}={$value}";

        return $this;
    }

    public function whereLabelNot(string $key, string $value): static
    {
        $this->labelSelectors[] = "{$key}!={$value}";

        return $this;
    }

    /** @param list<string> $values */
    public function whereLabelIn(string $key, array $values): static
    {
        $this->labelSelectors[] = "{$key} in (".implode(',', $values).')';

        return $this;
    }

    /** @param list<string> $values */
    public function whereLabelNotIn(string $key, array $values): static
    {
        $this->labelSelectors[] = "{$key} notin (".implode(',', $values).')';

        return $this;
    }

    public function whereLabelExists(string $key): static
    {
        $this->labelSelectors[] = $key;

        return $this;
    }

    public function whereLabelMissing(string $key): static
    {
        $this->labelSelectors[] = "!{$key}";

        return $this;
    }

    public function whereField(string $key, string $value): static
    {
        $this->fieldSelectors[] = "{$key}={$value}";

        return $this;
    }

    public function whereFieldNot(string $key, string $value): static
    {
        $this->fieldSelectors[] = "{$key}!={$value}";

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function continueFrom(?string $token): static
    {
        $this->continueToken = $token;

        return $this;
    }

    public function allNamespaces(bool $all = true): static
    {
        $this->allNamespaces = $all;

        return $this;
    }

    public function hasLabelSelectors(): bool
    {
        return $this->labelSelectors !== [];
    }

    public function getLabelSelector(): ?string
    {
        return $this->labelSelectors === [] ? null : implode(',', $this->labelSelectors);
    }

    public function getFieldSelector(): ?string
    {
        return $this->fieldSelectors === [] ? null : implode(',', $this->fieldSelectors);
    }

    public function listsAllNamespaces(): bool
    {
        return $this->allNamespaces;
    }

    /**
     * Assemble the listing query parameters from the configured selectors and
     * pagination state, merged on top of any base query.
     *
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    protected function listingQuery(array $base = []): array
    {
        $query = $base;

        if (($label = $this->getLabelSelector()) !== null) {
            $query['labelSelector'] = $label;
        }

        if (($field = $this->getFieldSelector()) !== null) {
            $query['fieldSelector'] = $field;
        }

        if ($this->limit !== null) {
            $query['limit'] = $this->limit;
        }

        if ($this->continueToken !== null && $this->continueToken !== '') {
            $query['continue'] = $this->continueToken;
        }

        return $query;
    }
}
