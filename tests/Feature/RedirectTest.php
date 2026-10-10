<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\Exceptions\ClusterConfigurationException;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Exceptions\WebSocketException;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Tests\Fixtures\LocalServer;

/*
 * The apiserver never redirects an API call. Anything that does — an edge login page, a
 * path rewrite, a hostile proxy — must not get the bearer token, the client certificate
 * or a write's body replayed to it, and its answer must not be parsed as the apiserver's.
 */

/** @return list<string> */
function redirectLog(LocalServer $server): array
{
    return $server->requests();
}

describe('against a real server', function (): void {
    beforeEach(function (): void {
        $this->server = LocalServer::http(__DIR__.'/../Fixtures/servers/redirect.php');
    });

    afterEach(function (): void {
        $this->server->stop();
        isset($this->other) && $this->other->stop();
    });

    it('does not replay the bearer token through a same-origin 302', function (): void {
        $cluster = Kubernetes::url($this->server->url('/r302'))->withToken('secret-token');

        try {
            $cluster->secrets()->setNamespace('default')->setName('db')->find();
            $this->fail('Expected the redirect to throw.');
        } catch (KubernetesException $e) {
            expect($e->getMessage())->toContain('HTTP 302')
                ->toContain($this->server->url())
                ->not->toContain('/landing')
                ->not->toContain('token=abc');
        }

        expect(redirectLog($this->server))->toHaveCount(1)
            ->and(redirectLog($this->server)[0])->toStartWith('GET /r302/api/v1/namespaces/default/secrets/db');
    });

    it('does not replay a write body through a 307', function (): void {
        $cluster = Kubernetes::url($this->server->url('/r307'))->withToken('secret-token');

        expect(fn () => $cluster->secrets()->setNamespace('default')->setName('db')->setData(['password' => 'hunter2'])->create())
            ->toThrow(KubernetesException::class, 'HTTP 307');

        $log = redirectLog($this->server);

        expect($log)->toHaveCount(1)
            ->and($log[0])->toStartWith('POST /r307/api/v1/namespaces/default/secrets')
            ->and(implode("\n", $log))->not->toContain('/landing');
    });

    it('does not follow a redirect to another origin, and names only that origin', function (): void {
        $this->other = LocalServer::http(__DIR__.'/../Fixtures/servers/redirect.php');
        $cluster = Kubernetes::url($this->server->url("/r302-{$this->other->port}"))->withToken('secret-token');

        try {
            $cluster->request('GET', '/version');
            $this->fail('Expected the redirect to throw.');
        } catch (KubernetesException $e) {
            expect($e->getMessage())->toContain('HTTP 302')
                ->toContain("http://127.0.0.1:{$this->other->port}")
                ->not->toContain('/landing')
                ->not->toContain('token=abc')
                ->and($e->response->status())->toBe(302);
        }

        expect(redirectLog($this->server))->toHaveCount(1)
            ->and(redirectLog($this->other))->toBe([]);
    });

    it('reports a redirecting cluster as unreachable on ping', function (): void {
        expect(Kubernetes::url($this->server->url('/r302'))->ping())->toBeFalse()
            ->and(redirectLog($this->server))->toHaveCount(1);
    });

    it('follows a redirect once the cluster opts in', function (): void {
        $cluster = Kubernetes::url($this->server->url('/r302'))->withToken('secret-token')->withRedirects();

        $response = $cluster->request('GET', '/version');

        expect($response->json('metadata.name'))->toBe('landed')
            ->and(redirectLog($this->server))->toHaveCount(2)
            ->and(redirectLog($this->server)[1])->toStartWith('GET /landing/path?token=abc');
    });

    it('follows a redirect for a configured cluster with follow_redirects on', function (): void {
        config()->set('kubernetes.clusters.default', ['url' => $this->server->url('/r302'), 'follow_redirects' => 'true']);

        expect(Kubernetes::cluster()->request('GET', '/version')->json('metadata.name'))->toBe('landed')
            ->and(redirectLog($this->server))->toHaveCount(2);
    });
});

it('tells the HTTP client not to follow redirects by default', function (): void {
    $seen = [];

    Http::fake(function (Request $request, array $options) use (&$seen) {
        $seen[] = $options['allow_redirects'] ?? 'absent';

        return Http::response(['items' => []]);
    });

    Kubernetes::url('https://k8s.example')->withToken('t')->pods()->setNamespace('shop')->get();
    Kubernetes::url('https://k8s.example')->pods()->setNamespace('shop')->watch(fn () => null);

    expect($seen)->toBe([false, false]);
});

/*
 * Guzzle hands the handler its normalised options: `true` arrives as its defaults (five
 * hops), so a followed policy is recorded by its `max`.
 */
function redirectPolicySeen(array $options): mixed
{
    $policy = $options['allow_redirects'] ?? 'absent';

    return is_array($policy) ? ['max' => $policy['max'] ?? null, 'strict' => $policy['strict'] ?? null] : $policy;
}

it('honours an explicit client.options.allow_redirects as the global form', function (mixed $given, mixed $sent): void {
    config()->set('kubernetes.client.options.allow_redirects', $given);
    $seen = [];

    Http::fake(function (Request $request, array $options) use (&$seen) {
        $seen[] = redirectPolicySeen($options);

        return Http::response(['gitVersion' => 'v1']);
    });

    Kubernetes::url('https://k8s.example')->request('GET', '/version');

    expect($seen)->toBe([$sent]);
})->with([
    'an options array' => [['max' => 2], ['max' => 2, 'strict' => false]],
    'true' => [true, ['max' => 5, 'strict' => false]],
    'false' => [false, false],
    'an env-style "true"' => ['true', ['max' => 5, 'strict' => false]],
    'blank means not set' => ['', false],
    'null means not set' => [null, false],
]);

it('refuses an unreadable client.options.allow_redirects', function (): void {
    config()->set('kubernetes.client.options.allow_redirects', 'sometimes');
    Http::fake();

    Kubernetes::url('https://k8s.example')->request('GET', '/version');
})->throws(ClusterConfigurationException::class, 'Configuration value [kubernetes.client.options.allow_redirects] must be a boolean');

it('lets the cluster opt-in win over a global allow_redirects = false', function (): void {
    config()->set('kubernetes.client.options.allow_redirects', false);
    $seen = [];

    Http::fake(function (Request $request, array $options) use (&$seen) {
        $seen[] = redirectPolicySeen($options);

        return Http::response(['gitVersion' => 'v1']);
    });

    Kubernetes::url('https://k8s.example')->withRedirects()->request('GET', '/version');

    expect($seen)->toBe([['max' => 5, 'strict' => false]]);
});

it('throws for a 3xx the client did not follow, without the body', function (): void {
    Http::fake(['*' => Http::response('<html>Authorization: Bearer leaked</html>', 302, ['Location' => 'https://login.example:8443/sso?next=/api'])]);

    try {
        Kubernetes::url('https://k8s.example')->request('GET', '/api/v1/pods');
        test()->fail('Expected the redirect to throw.');
    } catch (KubernetesException $e) {
        expect($e->getMessage())->toContain('HTTP 302')
            ->toContain('https://login.example:8443')
            ->not->toContain('/sso')
            ->not->toContain('next=')
            ->not->toContain('Bearer leaked');
    }
});

it('describes a redirect without a usable Location', function (array $headers, string $described): void {
    Http::fake(['*' => Http::response('', 301, $headers)]);

    expect(fn () => Kubernetes::url('https://k8s.example')->request('GET', '/version'))
        ->toThrow(KubernetesException::class, $described);
})->with([
    'no Location' => [[], 'HTTP 301 without a Location'],
    'a relative Location' => [['Location' => '/elsewhere?x=1'], 'HTTP 301 to https://k8s.example'],
    'a scheme-relative Location' => [['Location' => '//other.example:6443/x'], 'HTTP 301 to https://other.example:6443'],
    'an unparseable Location' => [['Location' => 'http://[bad'], 'HTTP 301 to an unparseable location'],
]);

it('reports false from ping on a 3xx', function (): void {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://elsewhere.example/'])]);

    expect(Kubernetes::url('https://k8s.example')->ping())->toBeFalse();
});

it('opts in and out immutably, and keeps the choice across applyConfig', function (): void {
    $plain = Cluster::make()->url('https://k8s.example');
    $following = $plain->withRedirects();

    expect($plain->followsRedirects())->toBeFalse()
        ->and($following->followsRedirects())->toBeTrue()
        ->and($following->withoutRedirects()->followsRedirects())->toBeFalse()
        ->and($following->followsRedirects())->toBeTrue()
        ->and($following->applyConfig(new KubeConfig(server: 'https://other.example', token: 't'))->followsRedirects())->toBeTrue();
});

it('reads follow_redirects strictly, naming the key', function (): void {
    config()->set('kubernetes.clusters.default', ['url' => 'https://k8s.example', 'follow_redirects' => 'maybe']);

    Kubernetes::cluster();
})->throws(ClusterConfigurationException::class, 'Configuration value [kubernetes.clusters.default.follow_redirects] must be a boolean');

it('reads follow_redirects the way an env file writes it', function (mixed $value, bool $follows): void {
    config()->set('kubernetes.clusters.default', ['url' => 'https://k8s.example', 'follow_redirects' => $value]);

    expect(Kubernetes::cluster()->followsRedirects())->toBe($follows);
})->with([
    'absent' => [null, false],
    'blank' => ['', false],
    '"true"' => ['true', true],
    '"false"' => ['false', false],
    'true' => [true, true],
    '"1"' => ['1', true],
]);

it('applies follow_redirects for every cluster source', function (string $source): void {
    $path = (string) tempnam(sys_get_temp_dir(), 'k8s-redirect-kubeconfig-');
    file_put_contents($path, "apiVersion: v1\nkind: Config\ncurrent-context: c\ncontexts:\n- name: c\n  context: {cluster: c, user: u}\nclusters:\n- name: c\n  cluster: {server: 'https://k8s.example'}\nusers:\n- name: u\n  user: {token: t}\n");

    config()->set('kubernetes.clusters.default', ['source' => $source, 'url' => 'https://k8s.example', 'kubeconfig' => $path, 'follow_redirects' => true]);

    try {
        expect(Kubernetes::cluster()->followsRedirects())->toBeTrue();
    } finally {
        unlink($path);
    }
})->with(['url', 'kubeconfig']);

it('refuses an exec answered with a redirect', function (): void {
    $server = LocalServer::script(__DIR__.'/../Fixtures/servers/exec.php');

    try {
        expect(fn () => Kubernetes::url($server->url())->withToken('t')->pods()->setName('api')->exec(['redirect']))
            ->toThrow(WebSocketException::class, 'WebSocket upgrade failed: HTTP/1.1 302 Found');

        expect(array_values(array_filter($server->requests(), fn (string $line): bool => str_starts_with($line, 'GET '))))->toHaveCount(1);
    } finally {
        $server->stop();
    }
});
