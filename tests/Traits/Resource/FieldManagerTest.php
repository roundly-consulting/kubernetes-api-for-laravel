<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubernetesPatch;
use RoundlyConsulting\KubernetesApi\Enums\PatchType;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;

/*
 * The apiserver requires `fieldManager` on a server-side apply (PatchOptions: "required
 * for apply requests (application/apply-patch)") and forbids `force` on any other patch.
 * The manager name used to travel only as the User-Agent, so every apply was a 422.
 */

beforeEach(function (): void {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['kind' => 'Deployment', 'metadata' => ['name' => 'web']])]);

    $this->deployment = fn (?string $manager): Deployment => Deployment::make()
        ->setCluster(Cluster::make()->url('https://k8s.example')->withManagerName($manager))
        ->setNamespace('shop')
        ->setName('web');
});

/**
 * @return array<string, string>
 */
function sentQuery(Request $request): array
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    /** @var array<string, string> $query */
    return $query;
}

it('sends the manager name as fieldManager on a server-side apply', function (): void {
    ($this->deployment)('my-app')->patch(KubernetesPatch::apply(['spec' => ['replicas' => 2]]));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && $request->hasHeader('Content-Type', PatchType::Apply->contentType())
        && sentQuery($request) === ['pretty' => '1', 'fieldManager' => 'my-app']);
});

it('falls back to the package field manager when no manager name is set', function (): void {
    ($this->deployment)(null)->patch(KubernetesPatch::apply(['spec' => ['replicas' => 2]]));

    Http::assertSent(fn (Request $request): bool => sentQuery($request)['fieldManager'] === 'kubernetes-api-for-laravel');
});

it('forces an apply only when asked, and never another patch type', function (): void {
    ($this->deployment)('my-app')->patch(KubernetesPatch::apply(['spec' => ['replicas' => 2]], force: true));
    ($this->deployment)('my-app')->patch(new KubernetesPatch(PatchType::Merge, ['spec' => ['paused' => true]], force: true));

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Content-Type', PatchType::Apply->contentType())
        && sentQuery($request) === ['pretty' => '1', 'fieldManager' => 'my-app', 'force' => 'true']);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Content-Type', PatchType::Merge->contentType())
        && ! array_key_exists('force', sentQuery($request)));
});

it('records the manager name on every write', function (): void {
    $deployment = ($this->deployment)('my-app');

    $deployment->create();
    $deployment->update();
    $deployment->patch(KubernetesPatch::merge(['spec' => ['paused' => true]]));
    $deployment->scale(3);
    $deployment->rolloutRestart();

    Http::assertSentCount(5);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && sentQuery($request)['fieldManager'] === 'my-app');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && sentQuery($request)['fieldManager'] === 'my-app');
    Http::assertNotSent(fn (Request $request): bool => ($request->method() !== 'GET') && ! isset(sentQuery($request)['fieldManager']));
});

it('leaves reads, deletes and manager-less writes without a fieldManager', function (): void {
    ($this->deployment)(null)->create();
    ($this->deployment)(null)->patch(KubernetesPatch::merge(['spec' => ['paused' => true]]));
    ($this->deployment)('my-app')->find();
    ($this->deployment)('my-app')->delete();

    Http::assertNotSent(fn (Request $request): bool => isset(sentQuery($request)['fieldManager']));
});

it('lets a per-request fieldManager win', function (): void {
    ($this->deployment)('my-app')->patch(KubernetesPatch::apply(['spec' => []]), ['fieldManager' => 'migration']);

    Http::assertSent(fn (Request $request): bool => sentQuery($request)['fieldManager'] === 'migration');
});

it('sends the manager name as the user agent only when one is set', function (): void {
    ($this->deployment)('my-app')->find();
    ($this->deployment)(null)->find();

    Http::assertSent(fn (Request $request): bool => $request->header('User-Agent') === ['my-app']);
    Http::assertNotSent(fn (Request $request): bool => $request->header('User-Agent') === ['']);
});

it('answers an apply without fieldManager with the apiserver 422 under the fake', function (): void {
    Kubernetes::fake()->seed(Deployment::class, [['metadata' => ['name' => 'web', 'namespace' => 'shop']]]);

    Kubernetes::cluster()->request('PATCH', '/apis/apps/v1/namespaces/shop/deployments/web', [], '{}', PatchType::Apply->contentType());
})->throws(KubernetesException::class, 'fieldManager: Required value: is required for apply patch');

it('answers force on a non-apply patch with the apiserver 422 under the fake', function (): void {
    Kubernetes::fake()->seed(Deployment::class, [['metadata' => ['name' => 'web', 'namespace' => 'shop']]]);

    Kubernetes::cluster()->request('PATCH', '/apis/apps/v1/namespaces/shop/deployments/web', ['force' => 'true'], '{}', PatchType::Merge->contentType());
})->throws(KubernetesException::class, 'force: Forbidden: may not be specified for non-apply patch');

it('applies under the fake with the fieldManager recorded', function (): void {
    $fake = Kubernetes::fake()->seed(Deployment::class, [['metadata' => ['name' => 'web', 'namespace' => 'shop']]]);

    Kubernetes::namespace('shop')->deployments()->setName('web')->patch(KubernetesPatch::apply(['spec' => ['replicas' => 4]]));

    $fake->assertPatched(Deployment::class, fn ($request): bool => $request->query['fieldManager'] === 'kubernetes-api-for-laravel');
});
