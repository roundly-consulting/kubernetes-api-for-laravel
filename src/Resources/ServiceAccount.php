<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\DataTransferObjects\ServiceAccountToken;
use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;

class ServiceAccount extends Resource
{
    /**
     * The shortest lifetime the apiserver accepts for a TokenRequest.
     */
    public const int MIN_TOKEN_EXPIRATION_SECONDS = 600;

    /**
     * The kinds a TokenRequest can bind a token to.
     */
    public const array TOKEN_BINDABLE_KINDS = ['Pod', 'Secret', 'Node'];

    protected string $kind = 'ServiceAccount';

    protected bool $usesNamespaces = true;

    public function addSecret(string $name): static
    {
        return $this->addToAttribute('secrets', ['name' => $name]);
    }

    /** @return array<int, array<string, string>> */
    public function getSecrets(): array
    {
        return (array) $this->getAttribute('secrets', []);
    }

    public function addImagePullSecret(string $name): static
    {
        return $this->addToAttribute('imagePullSecrets', ['name' => $name]);
    }

    /** @return array<int, array<string, string>> */
    public function getImagePullSecrets(): array
    {
        return (array) $this->getAttribute('imagePullSecrets', []);
    }

    public function setAutomountServiceAccountToken(bool $automount): static
    {
        return $this->setAttribute('automountServiceAccountToken', $automount);
    }

    public function getAutomountServiceAccountToken(): ?bool
    {
        $value = $this->getAttribute('automountServiceAccountToken');

        return is_bool($value) ? $value : null;
    }

    /**
     * Mint a short-lived token for this service account through its `token`
     * subresource — a TokenRequest, as `kubectl create token` sends. Only the fields
     * given are sent; the apiserver fills in the rest (its default lifetime and
     * audiences) and may shorten the lifetime.
     *
     * A failure is always body-free (`HTTP 403 Forbidden`), whatever the cluster's
     * redaction: this is a credential endpoint. `$e->apiMessage()` still reads the
     * apiserver's Status message.
     *
     * `$boundTo` binds the token to an existing Pod, Secret or Node (it needs
     * `metadata.name` and `metadata.uid`): deleting that object invalidates the token.
     *
     * @param  int|null  $expirationSeconds  the lifetime to ask for, 600 or more
     * @param  list<string>  $audiences  the audiences the token is for; empty = the apiserver's default
     *
     * @throws InvalidResourceException before any request: an unnamed service account, a dry
     *                                  run, a lifetime under 600 seconds, an empty audience, or a binding
     *                                  to anything but an existing Pod, Secret or Node
     * @throws KubernetesException when the apiserver refuses, or answers without a token
     */
    public function requestToken(?int $expirationSeconds = null, array $audiences = [], ?Resource $boundTo = null): ServiceAccountToken
    {
        $name = $this->getName();

        if ($name === null || $name === '') {
            throw new InvalidResourceException('Name the service account before requesting a token: setName().');
        }

        if ($this->dryRun) {
            throw new InvalidResourceException('A dry-run TokenRequest mints no token: call requestToken() without dryRun().');
        }

        $spec = [];

        foreach ($audiences as $audience) {
            if (trim($audience) === '') {
                throw new InvalidResourceException('Every token audience must be a non-empty string.');
            }

            $spec['audiences'][] = $audience;
        }

        if ($expirationSeconds !== null) {
            if ($expirationSeconds < self::MIN_TOKEN_EXPIRATION_SECONDS) {
                throw new InvalidResourceException(sprintf(
                    'A token must live at least %d seconds (the apiserver minimum), %d given.',
                    self::MIN_TOKEN_EXPIRATION_SECONDS,
                    $expirationSeconds,
                ));
            }

            $spec['expirationSeconds'] = $expirationSeconds;
        }

        if ($boundTo !== null) {
            $spec['boundObjectRef'] = $this->tokenBinding($boundTo);
        }

        $payload = json_encode(
            ['apiVersion' => 'authentication.k8s.io/v1', 'kind' => 'TokenRequest', 'spec' => (object) $spec],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );

        $response = $this->requireCluster()
            ->withRedactedErrors()
            ->request('POST', $this->getSubresourcePath('token'), body: $payload, contentType: 'application/json');

        return ServiceAccountToken::fromResponse($response);
    }

    /**
     * @return array<string, string>
     *
     * @throws InvalidResourceException
     */
    private function tokenBinding(Resource $object): array
    {
        $kind = $object->getKind();

        if (! in_array($kind, self::TOKEN_BINDABLE_KINDS, true)) {
            throw new InvalidResourceException("A token can be bound to a Pod, Secret or Node, not a {$kind}.");
        }

        $name = $object->getName();
        $uid = $object->getAttribute('metadata.uid');

        if ($name === null || $name === '' || ! is_string($uid) || $uid === '') {
            throw new InvalidResourceException("Bind a token to an existing {$kind}, with metadata.name and metadata.uid: find() it first.");
        }

        return ['kind' => $kind, 'apiVersion' => $object->getVersion(), 'name' => $name, 'uid' => $uid];
    }
}
