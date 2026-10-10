<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\Exceptions\ClusterConfigurationException;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Exceptions\WebSocketException;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Tests\Fixtures\LocalServer;

/*
 * An exception message ends up in logs and in `failed_jobs`. It carries the apiserver's
 * own Status `message` (what developers debug with) and never the raw body: an edge HTML
 * page or a proxy that echoes the request headers would otherwise log the bearer token.
 */

function failedWith(string|array $body, int $status, ?Cluster $cluster = null): KubernetesException
{
    Http::fake(['*' => Http::response($body, $status)]);

    try {
        ($cluster ?? Kubernetes::url('https://k8s.example'))->request('GET', '/api/v1/namespaces/shop/secrets/db');
    } catch (KubernetesException $e) {
        return $e;
    }

    throw new RuntimeException('Expected a KubernetesException.');
}

function statusBody(string $reason, string $message, int $code, array $details = []): array
{
    return array_filter([
        'kind' => 'Status',
        'apiVersion' => 'v1',
        'metadata' => [],
        'status' => 'Failure',
        'message' => $message,
        'reason' => $reason,
        'details' => $details,
        'code' => $code,
    ], fn (mixed $value): bool => $value !== [] && $value !== '');
}

it('never puts a non-Status body into the message', function (): void {
    $e = failedWith("<html><body>Bad gateway\nAuthorization: Bearer abc</body></html>", 502);

    expect($e->getMessage())->toBe('Kubernetes API request failed: HTTP 502')
        ->and($e->getMessage())->not->toContain('Bearer abc')
        ->and($e->getCode())->toBe(502);
});

it('keeps the message body-free once Laravel reports the exception', function (): void {
    // Laravel's handler calls report(), which re-summarises the body into the message.
    $e = failedWith(statusBody('Forbidden', 'secrets "db" is forbidden', 403, ['name' => 'Authorization: Bearer abc']), 403);

    $e->report();

    expect($e->getMessage())->toBe('secrets "db" is forbidden')
        ->and($e->getMessage())->not->toContain('Bearer abc');
});

it('keeps the apiserver Status message', function (): void {
    $e = failedWith(statusBody('AlreadyExists', 'secrets "db" already exists', 409), 409);

    expect($e->getMessage())->toBe('secrets "db" already exists');
});

it('names the reason when the body has one but no message', function (): void {
    expect(failedWith(['kind' => 'Status', 'reason' => 'Forbidden'], 403)->getMessage())
        ->toBe('Kubernetes API request failed: HTTP 403 Forbidden');
});

it('reads the Status fields on demand', function (): void {
    $e = failedWith(statusBody('NotFound', 'secrets "db" not found', 404, ['name' => 'db', 'kind' => 'secrets']), 404);

    expect($e->status())->toBe(404)
        ->and($e->reason())->toBe('NotFound')
        ->and($e->apiMessage())->toBe('secrets "db" not found')
        ->and($e->details())->toBe(['name' => 'db', 'kind' => 'secrets'])
        ->and($e->response->notFound())->toBeTrue()
        ->and($e)->toBeInstanceOf(RequestException::class);
});

it('reads nothing from a body that is not a Status', function (): void {
    $e = failedWith('<html>oops</html>', 500);

    expect($e->reason())->toBeNull()
        ->and($e->apiMessage())->toBeNull()
        ->and($e->details())->toBe([]);
});

it('takes only a well-formed reason', function (mixed $reason): void {
    $e = failedWith(['reason' => $reason], 500);

    expect($e->reason())->toBeNull()
        ->and($e->getMessage())->toBe('Kubernetes API request failed: HTTP 500');
})->with([
    'free text' => ['Authorization: Bearer abc'],
    'empty' => [''],
    'not a string' => [42],
    'too long' => [str_repeat('A', 65)],
]);

it('gives status and reason only when the cluster redacts errors', function (): void {
    $cluster = Kubernetes::url('https://k8s.example')->withRedactedErrors();

    $e = failedWith(statusBody('AlreadyExists', 'secrets "db" already exists', 409), 409, $cluster);

    expect($e->getMessage())->toBe('HTTP 409 AlreadyExists')
        ->and($e->apiMessage())->toBe('secrets "db" already exists');

    $e->report();

    expect($e->getMessage())->toBe('HTTP 409 AlreadyExists');
});

it('gives the status alone for a redacted error without a reason', function (): void {
    $cluster = Kubernetes::url('https://k8s.example')->withRedactedErrors();

    expect(failedWith('<html>Bearer abc</html>', 502, $cluster)->getMessage())->toBe('HTTP 502');
});

it('redacts immutably, and keeps the choice across applyConfig', function (): void {
    $plain = Cluster::make()->url('https://k8s.example');
    $redacting = $plain->withRedactedErrors();

    expect($plain->redactsErrors())->toBeFalse()
        ->and($redacting->redactsErrors())->toBeTrue()
        ->and($redacting->applyConfig(new KubeConfig(server: 'https://other.example'))->redactsErrors())->toBeTrue();
});

it('reads redact_errors the way an env file writes it', function (mixed $value, bool $redacts): void {
    config()->set('kubernetes.clusters.default', ['url' => 'https://k8s.example', 'redact_errors' => $value]);

    expect(Kubernetes::cluster()->redactsErrors())->toBe($redacts);
})->with([
    'absent' => [null, false],
    'blank' => ['', false],
    '"true"' => ['true', true],
    'false' => [false, false],
]);

it('reads redact_errors strictly, naming the key', function (): void {
    config()->set('kubernetes.clusters.default', ['url' => 'https://k8s.example', 'redact_errors' => 'maybe']);

    Kubernetes::cluster();
})->throws(ClusterConfigurationException::class, 'Configuration value [kubernetes.clusters.default.redact_errors] must be a boolean');

it('builds a body-free message through the public constructor too', function (): void {
    $response = new Response(Factory::response('<html>Authorization: Bearer abc</html>', 500)->wait());

    expect((new KubernetesException($response))->getMessage())->toBe('Kubernetes API request failed: HTTP 500')
        ->and((new KubernetesException($response, 'Something specific.'))->getMessage())->toBe('Something specific.')
        ->and((new KubernetesException($response, redacted: true))->getMessage())->toBe('HTTP 500');
});

describe('exec', function (): void {
    beforeEach(function (): void {
        $this->server = LocalServer::script(__DIR__.'/../Fixtures/servers/exec.php');
        $this->pod = fn (?Cluster $cluster = null): mixed => ($cluster ?? Kubernetes::url($this->server->url())->withToken('secret-token'))
            ->pods()
            ->setNamespace('default')
            ->setName('api');
    });

    afterEach(function (): void {
        $this->server->stop();
    });

    it('never puts the refused upgrade body into the message', function (): void {
        expect(fn () => ($this->pod)()->exec(['echo']))
            ->toThrow(function (WebSocketException $e): void {
                expect($e->getMessage())->toBe('WebSocket upgrade failed: HTTP/1.1 502 Bad Gateway')
                    ->not->toContain('secret-token');
            });
    });

    it('never puts the redirect target into the message', function (): void {
        expect(fn () => ($this->pod)()->exec(['redirect']))
            ->toThrow(function (WebSocketException $e): void {
                expect($e->getMessage())->toBe('WebSocket upgrade failed: HTTP/1.1 302 Found');
            });
    });

    it('keeps the apiserver Status message of a refused upgrade', function (): void {
        expect(fn () => ($this->pod)()->exec(['reject']))
            ->toThrow(WebSocketException::class, 'WebSocket upgrade failed: HTTP/1.1 403 Forbidden: pods "api" is forbidden');
    });

    it('gives the status line only when the cluster redacts errors', function (): void {
        $cluster = Kubernetes::url($this->server->url())->withToken('secret-token')->withRedactedErrors();

        expect(fn () => ($this->pod)($cluster)->exec(['reject']))
            ->toThrow(function (WebSocketException $e): void {
                expect($e->getMessage())->toBe('WebSocket upgrade failed: HTTP/1.1 403 Forbidden');
            });
    });
});
