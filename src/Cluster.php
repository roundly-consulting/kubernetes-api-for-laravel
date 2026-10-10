<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi;

use BadMethodCallException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use RoundlyConsulting\KubernetesApi\Concerns\ResolvesResources;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\ExecResult;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\VersionInfo;
use RoundlyConsulting\KubernetesApi\Exceptions\ClusterConfigurationException;
use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Exceptions\NamespaceScopeException;
use RoundlyConsulting\KubernetesApi\Http\HttpTransport;
use RoundlyConsulting\KubernetesApi\Http\Transport;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Support\PathSegment;
use RoundlyConsulting\KubernetesApi\Support\ResourceRegistry;
use RoundlyConsulting\KubernetesApi\Traits\HasAuthentication;
use RoundlyConsulting\KubernetesApi\Traits\HasManagerName;
use RoundlyConsulting\KubernetesApi\Traits\HasUrl;
use RoundlyConsulting\KubernetesApi\Traits\Makeable;

/**
 * One Kubernetes apiserver: its URL, credentials, default namespace and optional
 * namespace scope, plus a typed accessor per registered resource.
 *
 * A cluster is **immutable**. `url()`, every `with*()` / `without*()` and `namespace()`
 * return a new instance and leave the receiver untouched, so a client handed out by the
 * facade (or held in a singleton) can never be re-pointed or re-credentialed by a later
 * caller. Resolve clusters through {@see KubernetesManager} (the `Kubernetes` facade):
 * those are the ones `Kubernetes::fake()` intercepts.
 */
final class Cluster
{
    use Conditionable;
    use HasAuthentication;
    use HasManagerName;
    use HasUrl;
    use Macroable {
        __call as macroCall;
    }
    use Makeable;
    use ResolvesResources;

    private string $defaultNamespace = 'default';

    private ?string $namespaceScope = null;

    private bool $followRedirects = false;

    private bool $redactErrors = false;

    public function __construct(
        private readonly Transport $transport = new HttpTransport,
        private readonly ?string $name = null,
    ) {}

    /**
     * The name the cluster was registered under, or null for an ad-hoc client
     * (`Kubernetes::url()`, `fromKubeConfig()`, `inCluster()`, `connect()`).
     */
    public function name(): ?string
    {
        return $this->name;
    }

    /**
     * A copy of this client with a kubeconfig/in-cluster connection applied. A
     * kubeconfig context's namespace becomes the default namespace — never over a
     * namespace scope, which stays the boundary it is.
     */
    public function applyConfig(KubeConfig $config): self
    {
        $cluster = $this->url($config->server)
            ->withToken($config->token)
            ->withTokenFile($config->tokenFile)
            ->withCertificate($config->clientCertificatePath)
            ->withPrivateKey($config->clientKeyPath)
            ->withCaCertificate($config->certificateAuthorityPath);

        if ($config->namespace !== null && $this->namespaceScope === null) {
            $cluster = $cluster->withDefaultNamespace($config->namespace);
        }

        return $config->verify ? $cluster->withSslVerification() : $cluster->withoutSslVerification();
    }

    /**
     * A copy of this client scoped to one namespace. Every namespaced resource it hands
     * out is pinned to that namespace and refuses to be pointed anywhere else — the
     * scope is a security boundary, not a default.
     *
     * @throws NamespaceScopeException when this client is already scoped to another namespace
     * @throws InvalidResourceException when the namespace is not a valid DNS-1123 label
     */
    public function namespace(string $namespace): self
    {
        PathSegment::namespace($namespace);

        if ($this->namespaceScope !== null && $this->namespaceScope !== $namespace) {
            throw NamespaceScopeException::rescope($this->namespaceScope, $namespace);
        }

        $cluster = clone $this;
        $cluster->namespaceScope = $namespace;
        $cluster->defaultNamespace = $namespace;

        return $cluster;
    }

    /**
     * A copy of this client that follows HTTP redirects, with Guzzle's defaults (up to
     * five hops; `Authorization` and cookies are dropped on a hop to another origin).
     * Off by default: the apiserver never redirects an API call, and a redirect would
     * replay the bearer token, the client certificate and a write's body to wherever it
     * points. Opt in only for a cluster URL you know redirects (e.g. a proxy). It is
     * transport policy, not a credential, so {@see applyConfig()} keeps it.
     */
    public function withRedirects(): self
    {
        $cluster = clone $this;
        $cluster->followRedirects = true;

        return $cluster;
    }

    /**
     * A copy of this client that does not opt in to redirects (the default). The global
     * `client.options.allow_redirects`, when a host sets it, then applies.
     */
    public function withoutRedirects(): self
    {
        $cluster = clone $this;
        $cluster->followRedirects = false;

        return $cluster;
    }

    /**
     * Whether this client opted in to following redirects ({@see withRedirects()}).
     */
    public function followsRedirects(): bool
    {
        return $this->followRedirects;
    }

    /**
     * A copy of this client whose `KubernetesException` messages carry status and reason
     * only (`HTTP 409 AlreadyExists`), not even the apiserver's Status `message`. Messages
     * never carry the raw body either way; `apiMessage()`, `details()` and `$e->response`
     * still read it for code that asks. {@see applyConfig()} keeps it.
     */
    public function withRedactedErrors(): self
    {
        $cluster = clone $this;
        $cluster->redactErrors = true;

        return $cluster;
    }

    /**
     * Whether error messages are reduced to status and reason ({@see withRedactedErrors()}).
     */
    public function redactsErrors(): bool
    {
        return $this->redactErrors;
    }

    /**
     * The namespace this client is scoped to, or null when it is not scoped.
     */
    public function namespaceScope(): ?string
    {
        return $this->namespaceScope;
    }

    /**
     * A copy of this client whose resources default to the given namespace (a soft
     * default — unlike {@see namespace()} it does not refuse other namespaces).
     */
    public function withDefaultNamespace(string $namespace): self
    {
        if ($this->namespaceScope !== null && $this->namespaceScope !== $namespace) {
            throw NamespaceScopeException::rescope($this->namespaceScope, $namespace);
        }

        $cluster = clone $this;
        $cluster->defaultNamespace = $namespace;

        return $cluster;
    }

    public function defaultNamespace(): string
    {
        return $this->defaultNamespace;
    }

    /**
     * Resolve a fresh, cluster-bound instance of the given resource class.
     *
     * @template TResource of Resource
     *
     * @param  class-string<TResource>  $resource
     * @return TResource
     */
    public function resource(string $resource): Resource
    {
        return (new $resource)
            ->setDefaultNamespace($this->defaultNamespace)
            ->setCluster($this);
    }

    /**
     * Whether a resource is registered under the given accessor name, in
     * `config('kubernetes.resources')` or through `Kubernetes::registerResource()`.
     */
    public function hasResource(string $name): bool
    {
        return ResourceRegistry::classFor($name) !== null;
    }

    /**
     * Whether the apiserver answers `/version`. Connection failures, error responses,
     * an unfollowed redirect and a cluster without a URL all report `false`; a client-side rate-limit
     * exhaustion still throws, because it says nothing about the server.
     */
    public function ping(): bool
    {
        try {
            $this->version();
        } catch (KubernetesException|ConnectionException|ClusterConfigurationException) {
            return false;
        }

        return true;
    }

    /**
     * The apiserver's build information from `/version`.
     */
    public function version(): VersionInfo
    {
        /** @var array<string, mixed> $payload */
        $payload = (array) $this->request('GET', '/version')->json();

        return VersionInfo::fromResponse($payload);
    }

    /**
     * Send a raw request to the apiserver through this cluster's transport —
     * authenticated, rate limited and faked exactly like the typed resources.
     *
     * @param  array<string, mixed>  $query
     *
     * @throws KubernetesException when the apiserver answers with an error status, or
     *                             with a redirect (3xx) that was not followed
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        string $body = '',
        ?string $contentType = null,
        bool $stream = false,
    ): Response {
        $response = $this->transport->send($this, $method, $path, $query, $body, $contentType, $stream);

        // A 3xx reaching this point was not followed (redirects are off, or it could not be
        // followed). It is no apiserver answer, so it must not read as an empty success.
        if ($response->redirect()) {
            throw KubernetesException::redirectNotFollowed($response, $this->getUrl());
        }

        if ($response->failed()) {
            throw new KubernetesException($response, redacted: $this->redactErrors);
        }

        return $response;
    }

    /**
     * @internal Opens the exec WebSocket for {@see Resources\Pod::exec()}.
     */
    public function execute(string $path): ExecResult
    {
        return $this->transport->exec($this, $path);
    }

    /**
     * Resolve a custom resource registered by name (`$cluster->traefikIngressRoutes()`
     * style) or a macro.
     *
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (self::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        $resource = ResourceRegistry::classFor($method);

        if ($resource === null) {
            throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', self::class, $method));
        }

        return $this->resource($resource);
    }

    /**
     * @param  class-string<TResource>  $default
     * @return TResource
     *
     * @template TResource of Resource
     */
    protected function configuredResource(string $name, string $default): Resource
    {
        /** @var class-string<TResource> $class */
        $class = ResourceRegistry::classFor($name) ?? $default;

        return $this->resource($class);
    }
}
