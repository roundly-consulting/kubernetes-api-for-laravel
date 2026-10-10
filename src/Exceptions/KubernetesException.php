<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Exceptions;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

class KubernetesException extends RequestException
{
    public function __construct(Response $response, ?string $message = null)
    {
        parent::__construct($response);

        if ($message) {
            $this->message = $message;
        }
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
