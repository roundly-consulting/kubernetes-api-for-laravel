<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\ServiceAccountToken;
use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\KubernetesManager;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;
use RoundlyConsulting\KubernetesApi\Resources\Node;
use RoundlyConsulting\KubernetesApi\Resources\Pod;
use RoundlyConsulting\KubernetesApi\Resources\Secret;
use RoundlyConsulting\KubernetesApi\Resources\ServiceAccount;
use RoundlyConsulting\KubernetesApi\Testing\RecordedRequest;
use RoundlyConsulting\KubernetesApi\Testing\RequestVerb;

/*
 * `ServiceAccount::requestToken()`: a TokenRequest through the `token` subresource, the
 * way `kubectl create token` mints one. It is a credential endpoint, so its failures are
 * body-free whatever the cluster's redaction.
 */

function deployer(): ServiceAccount
{
    return Kubernetes::url('https://k8s.example')->withToken('admin')->serviceAccounts()->setNamespace('ci')->setName('deployer');
}

function tokenAnswer(array $status = ['token' => 'minted-token', 'expirationTimestamp' => '2026-10-10T13:00:00Z'], array $spec = ['audiences' => ['https://kubernetes.default.svc.cluster.local'], 'expirationSeconds' => 3600]): array
{
    return ['apiVersion' => 'authentication.k8s.io/v1', 'kind' => 'TokenRequest', 'metadata' => ['name' => 'deployer'], 'spec' => $spec, 'status' => $status];
}

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('the request', function (): void {
    it('posts a bare TokenRequest to the token subresource', function (): void {
        Http::fake(['*' => Http::response(tokenAnswer(), 201)]);

        deployer()->requestToken();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://k8s.example/api/v1/namespaces/ci/serviceaccounts/deployer/token'
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->hasHeader('Authorization', 'Bearer admin')
            && $request->body() === '{"apiVersion":"authentication.k8s.io/v1","kind":"TokenRequest","spec":{}}');
    });

    it('sends only the spec fields given', function (): void {
        Http::fake(['*' => Http::response(tokenAnswer(), 201)]);

        deployer()->requestToken(900, ['vault', 'https://api.example']);

        Http::assertSent(fn (Request $request): bool => $request->data() === [
            'apiVersion' => 'authentication.k8s.io/v1',
            'kind' => 'TokenRequest',
            'spec' => ['audiences' => ['vault', 'https://api.example'], 'expirationSeconds' => 900],
        ]);
    });

    it('binds the token to a pod, a secret or a node', function (string $class, string $apiVersion): void {
        Http::fake(['*' => Http::response(tokenAnswer(), 201)]);
        $object = (new $class)->setName('owner')->setAttribute('metadata.uid', 'b0c7-11');

        deployer()->requestToken(boundTo: $object);

        Http::assertSent(fn (Request $request): bool => $request->data()['spec'] === [
            'boundObjectRef' => ['kind' => (new $class)->getKind(), 'apiVersion' => $apiVersion, 'name' => 'owner', 'uid' => 'b0c7-11'],
        ]);
    })->with([
        'a pod' => [Pod::class, 'v1'],
        'a secret' => [Secret::class, 'v1'],
        'a node' => [Node::class, 'v1'],
    ]);
});

describe('the guards', function (): void {
    beforeEach(function (): void {
        Http::fake();
    });

    afterEach(function (): void {
        Http::assertNothingSent();
    });

    it('refuses a lifetime under the apiserver minimum', function (): void {
        expect(fn () => deployer()->requestToken(599))
            ->toThrow(InvalidResourceException::class, 'at least 600 seconds');
    });

    it('refuses an unnamed service account', function (): void {
        expect(fn () => Kubernetes::url('https://k8s.example')->serviceAccounts()->requestToken())
            ->toThrow(InvalidResourceException::class, 'Name the service account');
    });

    it('refuses a dry run, which mints nothing', function (): void {
        expect(fn () => deployer()->dryRun()->requestToken())
            ->toThrow(InvalidResourceException::class, 'dry-run');
    });

    it('refuses to bind to anything but a pod, secret or node', function (): void {
        $deployment = Deployment::make()->setName('web')->setAttribute('metadata.uid', 'u-1');

        expect(fn () => deployer()->requestToken(boundTo: $deployment))
            ->toThrow(InvalidResourceException::class, 'Pod, Secret or Node, not a Deployment');
    });

    it('refuses to bind to an object without a uid or a name', function (Pod $pod): void {
        expect(fn () => deployer()->requestToken(boundTo: $pod))
            ->toThrow(InvalidResourceException::class, 'existing Pod');
    })->with([
        'no uid' => fn (): Pod => Pod::make()->setName('api'),
        'no name' => fn (): Pod => Pod::make()->setAttribute('metadata.uid', 'u-1'),
    ]);

    it('refuses an empty audience', function (string $audience): void {
        expect(fn () => deployer()->requestToken(audiences: ['vault', $audience]))
            ->toThrow(InvalidResourceException::class, 'audience');
    })->with(['', '  ']);
});

describe('the answer', function (): void {
    it('maps the minted token, its expiry in UTC, its audiences and binding', function (): void {
        Http::fake(['*' => Http::response(tokenAnswer(
            ['token' => 'minted-token', 'expirationTimestamp' => '2026-10-10T15:00:00+02:00'],
            ['audiences' => ['vault'], 'expirationSeconds' => 3600, 'boundObjectRef' => ['kind' => 'Pod', 'apiVersion' => 'v1', 'name' => 'api', 'uid' => 'u-1']],
        ), 201)]);

        $token = deployer()->requestToken();

        expect($token)->toBeInstanceOf(ServiceAccountToken::class)
            ->and($token->token)->toBe('minted-token')
            ->and($token->expiresAt)->toBeInstanceOf(CarbonImmutable::class)
            ->and($token->expiresAt->toIso8601ZuluString())->toBe('2026-10-10T13:00:00Z')
            ->and($token->expiresAt->getTimezone()->getName())->toBeIn(['UTC', '+00:00'])
            ->and($token->audiences)->toBe(['vault'])
            ->and($token->boundObjectRef)->toBe(['kind' => 'Pod', 'apiVersion' => 'v1', 'name' => 'api', 'uid' => 'u-1']);
    });

    it('reads an answer without audiences or binding', function (): void {
        Http::fake(['*' => Http::response(tokenAnswer(spec: []), 201)]);

        $token = deployer()->requestToken();

        expect($token->audiences)->toBe([])
            ->and($token->boundObjectRef)->toBeNull();
    });

    it('knows when it has expired', function (): void {
        $token = new ServiceAccountToken('t', CarbonImmutable::parse('2026-10-10T13:00:00Z'));

        Carbon::setTestNow('2026-10-10T12:59:59Z');
        expect($token->isExpired())->toBeFalse();

        Carbon::setTestNow('2026-10-10T13:00:00Z');
        expect($token->isExpired())->toBeTrue()
            ->and($token->isExpired(CarbonImmutable::parse('2026-10-10T12:00:00Z')))->toBeFalse();
    });

    it('hides the token from var_dump, print_r and dump', function (): void {
        $token = new ServiceAccountToken('secret-jwt', CarbonImmutable::parse('2026-10-10T13:00:00Z'), ['vault']);

        ob_start();
        var_dump($token);
        $dumped = (string) ob_get_clean();

        expect($dumped)->not->toContain('secret-jwt')->toContain('vault')
            ->and(print_r($token, true))->not->toContain('secret-jwt')->toContain('2026-10-10')
            ->and($token->__debugInfo()['token'])->toBe('********');
    });

    it('refuses a malformed answer without the body in the message', function (array $status): void {
        Http::fake(['*' => Http::response(tokenAnswer($status), 201)]);

        expect(fn () => deployer()->requestToken())
            ->toThrow(function (KubernetesException $e): void {
                expect($e->getMessage())->toBe('Malformed TokenRequest response from the Kubernetes API (HTTP 201).')
                    ->not->toContain('leaky-token');
            });
    })->with([
        'no token' => [['expirationTimestamp' => '2026-10-10T13:00:00Z']],
        'an empty token' => [['token' => '', 'expirationTimestamp' => '2026-10-10T13:00:00Z']],
        'no expiry' => [['token' => 'leaky-token']],
        'an unparseable expiry' => [['token' => 'leaky-token', 'expirationTimestamp' => 'tomorrow']],
        'an impossible expiry' => [['token' => 'leaky-token', 'expirationTimestamp' => '2026-13-45T99:00:00Z']],
    ]);

    it('reports a failure body-free even when the cluster does not redact', function (int $status, string $reason): void {
        Http::fake(['*' => Http::response(['kind' => 'Status', 'reason' => $reason, 'message' => 'serviceaccounts "deployer" is forbidden: Authorization: Bearer leaked', 'code' => $status], $status)]);

        expect(fn () => deployer()->requestToken())
            ->toThrow(function (KubernetesException $e) use ($status, $reason): void {
                expect($e->getMessage())->toBe("HTTP {$status} {$reason}")
                    ->and($e->apiMessage())->toContain('is forbidden');
            });
    })->with([
        'forbidden' => [403, 'Forbidden'],
        'not found' => [404, 'NotFound'],
    ]);
});

describe('under the fake', function (): void {
    beforeEach(function (): void {
        Http::preventStrayRequests();
        Carbon::setTestNow('2026-10-10T12:00:00Z');
    });

    it('answers 404 for a service account that was never seeded', function (): void {
        Kubernetes::fake();

        expect(fn () => Kubernetes::serviceAccounts()->setNamespace('ci')->setName('deployer')->requestToken())
            ->toThrow(function (KubernetesException $e): void {
                expect($e->status())->toBe(404)
                    ->and($e->getMessage())->toBe('HTTP 404 NotFound');
            });
    });

    it('mints a deterministic token for a seeded service account', function (): void {
        Kubernetes::fake()->seed('serviceAccounts', [['metadata' => ['name' => 'deployer', 'namespace' => 'ci']]]);

        $first = Kubernetes::namespace('ci')->serviceAccounts()->setName('deployer')->requestToken();
        $second = Kubernetes::namespace('ci')->serviceAccounts()->setName('deployer')->requestToken(900, ['vault']);

        expect($first->token)->toBe('fake-token-ci.deployer.1')
            ->and($first->expiresAt->toIso8601ZuluString())->toBe('2026-10-10T13:00:00Z')
            ->and($first->audiences)->toBe(['https://kubernetes.default.svc.cluster.local'])
            ->and($second->token)->toBe('fake-token-ci.deployer.2')
            ->and($second->expiresAt->toIso8601ZuluString())->toBe('2026-10-10T12:15:00Z')
            ->and($second->audiences)->toBe(['vault']);
    });

    it('echoes a binding, and refuses one in another namespace with a 404', function (): void {
        Kubernetes::fake()->seed('serviceAccounts', [['metadata' => ['name' => 'deployer', 'namespace' => 'ci']]]);
        $pod = Pod::make()->setName('api')->setAttribute('metadata.uid', 'u-1');

        expect(Kubernetes::serviceAccounts()->setNamespace('ci')->setName('deployer')->requestToken(boundTo: $pod)->boundObjectRef)
            ->toBe(['kind' => 'Pod', 'apiVersion' => 'v1', 'name' => 'api', 'uid' => 'u-1'])
            ->and(fn () => Kubernetes::serviceAccounts()->setNamespace('prod')->setName('deployer')->requestToken())
            ->toThrow(KubernetesException::class, 'HTTP 404 NotFound');
    });

    it('records the request as a create on the token subresource', function (): void {
        $fake = Kubernetes::fake()->seed('serviceAccounts', [['metadata' => ['name' => 'deployer', 'namespace' => 'ci']]]);

        Kubernetes::serviceAccounts()->setNamespace('ci')->setName('deployer')->requestToken(900);

        $recorded = $fake->recorded(fn (RecordedRequest $request): bool => $request->subresource === 'token');

        expect($recorded)->toHaveCount(1)
            ->and($recorded[0]->verb)->toBe(RequestVerb::Create)
            ->and($recorded[0]->name)->toBe('deployer')
            ->and($recorded[0]->namespace)->toBe('ci')
            ->and($recorded[0]->input('spec.expirationSeconds'))->toBe(900);

        // The scale precedent: a token request is a create, so assertCreated() sees it too.
        $fake->assertCreated('serviceAccounts', 'deployer');
    });

    it('asserts a token request, and fails when none matches', function (): void {
        $fake = Kubernetes::fake()->seed('serviceAccounts', [['metadata' => ['name' => 'deployer', 'namespace' => 'ci']]]);

        Kubernetes::serviceAccounts()->setNamespace('ci')->setName('deployer')->requestToken(900);

        $fake->assertTokenRequested('deployer');
        $fake->assertTokenRequested('deployer', 'ci');
        Kubernetes::assertTokenRequested('deployer', 'ci', 900);

        expect(fn () => $fake->assertTokenRequested('builder'))
            ->toThrow(AssertionFailedError::class, "Expected a token to be requested for service account 'builder', but none was.")
            ->and(fn () => $fake->assertTokenRequested('deployer', 'prod'))
            ->toThrow(AssertionFailedError::class, "service account 'prod/deployer'")
            ->and(fn () => $fake->assertTokenRequested('deployer', 'ci', 3600))
            ->toThrow(AssertionFailedError::class, "Expected a token to be requested for 3600 seconds for service account 'ci/deployer'")
            ->and(fn () => $fake->assertNoTokenRequested())
            ->toThrow(AssertionFailedError::class, 'Expected no service-account token request, but 1 were sent.');
    });

    it('asserts that no token was requested', function (): void {
        $fake = Kubernetes::fake()->seed('serviceAccounts', [['metadata' => ['name' => 'deployer', 'namespace' => 'ci']]]);

        Kubernetes::serviceAccounts()->setNamespace('ci')->setName('deployer')->find();
        Kubernetes::cluster()->request('POST', '/api/v1/namespaces/ci/serviceaccounts/deployer/token', ['dryRun' => 'All'], '{"spec":{}}', 'application/json');

        $fake->assertNoTokenRequested();
        Kubernetes::assertNoTokenRequested();

        expect(fn () => $fake->assertTokenRequested('deployer'))->toThrow(AssertionFailedError::class);
    });

    it('answers a raw dry-run token request without a token, as the apiserver does', function (): void {
        Kubernetes::fake()->seed('serviceAccounts', [['metadata' => ['name' => 'deployer', 'namespace' => 'ci']]]);

        $response = Kubernetes::cluster()->request('POST', '/api/v1/namespaces/ci/serviceaccounts/deployer/token', ['dryRun' => 'All'], '{"spec":{}}', 'application/json');

        expect($response->status())->toBe(201)
            ->and($response->json('status.token'))->toBe('');
    });

    it('reaches the fake through an injected manager', function (): void {
        Kubernetes::fake()->seed('serviceAccounts', [['metadata' => ['name' => 'deployer', 'namespace' => 'ci']]]);

        $token = app(KubernetesManager::class)->serviceAccounts()->setNamespace('ci')->setName('deployer')->requestToken();

        expect($token->token)->toBe('fake-token-ci.deployer.1');
        Kubernetes::assertTokenRequested('deployer', 'ci');
    });
});
