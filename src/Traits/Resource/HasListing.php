<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;
use RoundlyConsulting\KubernetesApi\Exceptions\NamespaceScopeException;

trait HasListing
{
    use HasNamespace;

    /** @var list<string> */
    protected array $labelSelectors = [];

    /** @var list<string> */
    protected array $fieldSelectors = [];

    protected ?int $limit = null;

    protected ?string $continueToken = null;

    protected bool $allNamespaces = false;

    /**
     * @throws InvalidResourceException for a key or value outside the label grammar
     */
    public function whereLabel(string $key, string $value): static
    {
        $this->labelSelectors[] = self::labelKey($key).'='.self::labelValue($value);

        return $this;
    }

    /**
     * @throws InvalidResourceException for a key or value outside the label grammar
     */
    public function whereLabelNot(string $key, string $value): static
    {
        $this->labelSelectors[] = self::labelKey($key).'!='.self::labelValue($value);

        return $this;
    }

    /**
     * @param  list<string>  $values
     *
     * @throws InvalidResourceException for a key or value outside the label grammar
     */
    public function whereLabelIn(string $key, array $values): static
    {
        $this->labelSelectors[] = self::labelKey($key).' in ('.implode(',', array_map(self::labelValue(...), $values)).')';

        return $this;
    }

    /**
     * @param  list<string>  $values
     *
     * @throws InvalidResourceException for a key or value outside the label grammar
     */
    public function whereLabelNotIn(string $key, array $values): static
    {
        $this->labelSelectors[] = self::labelKey($key).' notin ('.implode(',', array_map(self::labelValue(...), $values)).')';

        return $this;
    }

    /**
     * @throws InvalidResourceException for a key outside the label grammar
     */
    public function whereLabelExists(string $key): static
    {
        $this->labelSelectors[] = self::labelKey($key);

        return $this;
    }

    /**
     * @throws InvalidResourceException for a key outside the label grammar
     */
    public function whereLabelMissing(string $key): static
    {
        $this->labelSelectors[] = '!'.self::labelKey($key);

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

    /**
     * @throws NamespaceScopeException on a resource pinned by a namespace-scoped cluster
     */
    public function allNamespaces(bool $all = true): static
    {
        if ($all && $this->namespaceScope !== null) {
            throw NamespaceScopeException::allNamespaces($this->namespaceScope);
        }

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
     * A label key: an optional DNS-subdomain prefix and `/`, then a name of at most 63
     * characters. Validated so no key can carry an operator, a comma or a space into the
     * selector and change what it matches.
     *
     * @throws InvalidResourceException
     */
    private static function labelKey(string $key): string
    {
        $prefix = null;
        $name = $key;

        if (str_contains($key, '/')) {
            [$prefix, $name] = explode('/', $key, 2);
        }

        $subdomain = '/^[a-z0-9](?:[-a-z0-9]*[a-z0-9])?(?:\.[a-z0-9](?:[-a-z0-9]*[a-z0-9])?)*\z/';

        if (($prefix !== null && (strlen($prefix) > 253 || preg_match($subdomain, $prefix) !== 1)) || ! self::isLabelName($name)) {
            throw InvalidResourceException::invalidSegment('label key', $key);
        }

        return $key;
    }

    /**
     * A label value: empty, or a name of at most 63 characters — never a comma, `=`,
     * parenthesis or space, any of which would widen or rewrite the selector.
     *
     * @throws InvalidResourceException
     */
    private static function labelValue(string $value): string
    {
        if ($value !== '' && ! self::isLabelName($value)) {
            throw InvalidResourceException::invalidSegment('label value', $value);
        }

        return $value;
    }

    private static function isLabelName(string $name): bool
    {
        return strlen($name) <= 63 && preg_match('/^[A-Za-z0-9](?:[-A-Za-z0-9_.]*[A-Za-z0-9])?\z/', $name) === 1;
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
