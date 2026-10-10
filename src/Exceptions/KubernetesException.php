<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Exceptions;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use RoundlyConsulting\KubernetesApi\Cluster;
use Throwable;

/**
 * The apiserver answered with an error status (or a redirect that was not followed).
 *
 * The message goes to logs and `failed_jobs`, so it never carries the raw response body:
 * an edge HTML page or a proxy that echoes the request headers would log the bearer
 * token. It is the apiserver's Status `message` when the body has one, otherwise
 * `Kubernetes API request failed: HTTP 502` (plus the Status `reason`, when there is
 * one). A cluster with {@see Cluster::withRedactedErrors()}
 * (or `clusters.*.redact_errors`) gets status and reason only: `HTTP 409 AlreadyExists`.
 * Laravel's own body summary — which its handler writes into the message on report — is
 * never used.
 *
 * The body stays reachable for code that asks for it: {@see apiMessage()},
 * {@see details()} and `$e->response` read it on demand.
 */
class KubernetesException extends RequestException
{
    private ?string $explicitMessage = null;

    private bool $redacted = false;

    /**
     * @param  string|null  $message  a message of your own, used as given
     * @param  bool  $redacted  status and reason only, no Status message
     */
    public function __construct(Response $response, ?string $message = null, bool $redacted = false)
    {
        // Set before the parent runs: its constructor builds the message through
        // prepareMessage(), and so does Laravel's report().
        $this->explicitMessage = $message === null || $message === '' ? null : $message;
        $this->redacted = $redacted;

        parent::__construct($response);
    }

    /**
     * A redirect (3xx) the client did not follow. The message names the status and the
     * target's origin only (`scheme://host[:port]`) — never its path, query or the body,
     * which may carry a login URL's state or a token.
     *
     * @param  string  $base  the cluster URL a relative `Location` resolves against
     */
    public static function redirectNotFollowed(Response $response, string $base = ''): self
    {
        $location = $response->header('Location');
        $target = $location === '' ? 'without a Location' : 'to '.self::originOf($location, $base);

        return new self($response, sprintf(
            'Kubernetes API request was redirected (HTTP %d %s) and the redirect was not followed. '
            .'The apiserver never redirects: fix the cluster URL, or opt in with withRedirects() / follow_redirects.',
            $response->status(),
            $target,
        ));
    }

    /**
     * A success status whose body is not what the call needs (a TokenRequest answer
     * without a token, say). Body-free: the body may hold half a credential.
     */
    public static function malformedResponse(Response $response, string $what): self
    {
        return new self($response, sprintf('Malformed %s response from the Kubernetes API (HTTP %d).', $what, $response->status()));
    }

    /**
     * The HTTP status the apiserver answered with.
     */
    public function status(): int
    {
        return $this->response->status();
    }

    /**
     * The Status `reason` (`NotFound`, `AlreadyExists`, `Forbidden` …), or null when the
     * body has none. Only a well-formed reason token counts, so it is safe to log.
     */
    public function reason(): ?string
    {
        return self::reasonOf($this->response);
    }

    /**
     * The apiserver's Status `message`, read from the body on demand — also under
     * redaction, because asking for it is a choice.
     */
    public function apiMessage(): ?string
    {
        return self::messageOf($this->response);
    }

    /**
     * The Status `details` (`name`, `kind`, `causes` …), read from the body on demand;
     * empty when the body has none.
     *
     * @return array<array-key, mixed>
     */
    public function details(): array
    {
        $details = $this->response->json('details');

        return is_array($details) ? $details : [];
    }

    /**
     * Replaces Laravel's message, which embeds a summary of the raw body.
     */
    protected function prepareMessage(Response $response): string
    {
        if ($this->explicitMessage !== null) {
            return $this->explicitMessage;
        }

        $reason = self::reasonOf($response);
        $status = 'HTTP '.$response->status().($reason === null ? '' : " {$reason}");

        if ($this->redacted) {
            return $status;
        }

        return self::messageOf($response) ?? "Kubernetes API request failed: {$status}";
    }

    private static function reasonOf(Response $response): ?string
    {
        $reason = $response->json('reason');

        return is_string($reason) && preg_match('/^[A-Za-z][A-Za-z0-9]{0,63}$/', $reason) === 1 ? $reason : null;
    }

    private static function messageOf(Response $response): ?string
    {
        $message = $response->json('message');

        return is_string($message) && trim($message) !== '' ? $message : null;
    }

    private static function originOf(string $location, string $base): string
    {
        try {
            $uri = UriResolver::resolve(new Uri($base), new Uri($location));
        } catch (Throwable) {
            return 'an unparseable location';
        }

        $scheme = strtolower($uri->getScheme());
        $host = $uri->getHost();

        if (preg_match('/^[a-z][a-z0-9+.-]*$/', $scheme) !== 1
            || preg_match('/^(?:[a-z0-9.-]+|\[[0-9a-f:.]+\])$/i', $host) !== 1) {
            return 'an unparseable location';
        }

        $port = $uri->getPort();

        return "{$scheme}://{$host}".($port === null ? '' : ":{$port}");
    }
}
