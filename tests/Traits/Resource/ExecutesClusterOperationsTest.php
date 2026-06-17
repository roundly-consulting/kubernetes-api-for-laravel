<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use RoundlyConsulting\KubernetesApi\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;
use RoundlyConsulting\KubernetesApi\Resources\ResourcesCollection;
use RoundlyConsulting\KubernetesApi\Traits\Resource\ExecutesClusterOperations;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasCluster;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasClusterPaths;

beforeEach(function () {
    $this->resource = Deployment::make()->setNamespace('production');

    $this->resource->setCluster(
        Kubernetes::make()
            ->url('https://localhost')
            ->withToken('secret')
            ->setManagerName('Pest Tests')
    );

    Http::preventStrayRequests();
});

it('uses required traits', function () {
    expect(ExecutesClusterOperations::class)->toUse([
        HasCluster::class,
        HasClusterPaths::class,
    ]);
});

it('uses custom http client options defined in config when making requests', function () {
    Http::fake([
        '*' => Http::response([
            'items' => [
                [
                    'metadata' => [
                        'name' => 'api-deployment',
                        'namespace' => 'production',
                    ],
                ],
            ],
        ]),
    ]);

    config()->set('kubernetes.client.options.headers', [
        'X-Foo' => 'Testing',
    ]);

    $this->resource->get();

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://localhost/apis/apps/v1/namespaces/production/deployments?pretty=1' &&
               $request->method() === 'GET' &&
               $request->isJson() &&
               $request->hasHeader('Authorization', 'Bearer secret') &&
               $request->hasHeader('User-Agent', 'Pest Tests') &&
               $request->hasHeader('X-Foo', 'Testing') &&
               $request->data() === [
                   'apiVersion' => 'apps/v1',
                   'kind' => 'Deployment',
                   'metadata' => [
                       'namespace' => 'production',
                   ],
               ];
    });
});

it('makes get request to get all resources of type', function () {
    Http::fake([
        '*' => Http::response([
            'items' => [
                [
                    'metadata' => [
                        'name' => 'api-deployment',
                        'namespace' => 'production',
                    ],
                ],
            ],
        ]),
    ]);

    $result = $this->resource->get();

    expect($result)
        ->toBeInstanceOf(ResourcesCollection::class)
        ->toHaveLength(1)
        ->and($result->first())
        ->toBeInstanceOf(Deployment::class)
        ->getName()
        ->toBe('api-deployment')
        ->getNamespace()
        ->toBe('production');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://localhost/apis/apps/v1/namespaces/production/deployments?pretty=1' &&
               $request->method() === 'GET' &&
               $request->isJson() &&
               $request->hasHeader('Authorization', 'Bearer secret') &&
               $request->hasHeader('User-Agent', 'Pest Tests') &&
               $request->data() === [
                   'apiVersion' => 'apps/v1',
                   'kind' => 'Deployment',
                   'metadata' => [
                       'namespace' => 'production',
                   ],
               ];
    });
});

it('makes get request to find specific resource', function () {
    Http::fake([
        '*' => Http::response([
            'metadata' => [
                'name' => 'api-deployment',
                'namespace' => 'production',
            ],
        ]),
    ]);

    $result = $this->resource->withName('api-deployment')->find();

    expect($result)
        ->toBeInstanceOf(Deployment::class)
        ->getName()
        ->toBe('api-deployment')
        ->getNamespace()
        ->toBe('production');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://localhost/apis/apps/v1/namespaces/production/deployments/api-deployment?pretty=1' &&
               $request->method() === 'GET' &&
               $request->isJson() &&
               $request->hasHeader('Authorization', 'Bearer secret') &&
               $request->hasHeader('User-Agent', 'Pest Tests') &&
               $request->data() === [
                   'apiVersion' => 'apps/v1',
                   'kind' => 'Deployment',
                   'metadata' => [
                       'namespace' => 'production',
                       'name' => 'api-deployment',
                   ],
               ];
    });
});

it('makes get request to find specific resource existence', function () {
    Http::fake([
        '*' => Http::sequence([
            Http::response([
                'metadata' => [
                    'name' => 'api-deployment',
                    'namespace' => 'production',
                ],
            ]),
            Http::response([
                'message' => 'Resource not found',
            ], 404),
        ]),
    ]);

    $api = $this->resource->withName('api-deployment')->existsOnCluster();
    $web = $this->resource->withName('web-deployment')->existsOnCluster();

    expect($api)->toBeTrue();
    expect($web)->toBeFalse();

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://localhost/apis/apps/v1/namespaces/production/deployments/api-deployment?pretty=1' &&
               $request->method() === 'GET' &&
               $request->isJson() &&
               $request->hasHeader('Authorization', 'Bearer secret') &&
               $request->hasHeader('User-Agent', 'Pest Tests') &&
               $request->data() === [
                   'apiVersion' => 'apps/v1',
                   'kind' => 'Deployment',
                   'metadata' => [
                       'namespace' => 'production',
                       'name' => 'api-deployment',
                   ],
               ];
    });

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://localhost/apis/apps/v1/namespaces/production/deployments/web-deployment?pretty=1' &&
               $request->method() === 'GET' &&
               $request->isJson() &&
               $request->hasHeader('Authorization', 'Bearer secret') &&
               $request->hasHeader('User-Agent', 'Pest Tests') &&
               $request->data() === [
                   'apiVersion' => 'apps/v1',
                   'kind' => 'Deployment',
                   'metadata' => [
                       'namespace' => 'production',
                       'name' => 'web-deployment',
                   ],
               ];
    });
});

it('makes get request to find specific resource existence and throws error when we receive something else than 404', function () {
    Http::fake([
        '*' => Http::sequence([
            Http::response([
                'message' => 'Server Error',
            ], 500),
        ]),
    ]);

    $this->resource->withName('api-deployment')->existsOnCluster();
})->throws(KubernetesException::class, 'Server Error');

it('makes post request to create specific resource', function () {
    Http::fake([
        '*' => Http::response([
            'metadata' => [
                'name' => 'api-deployment',
                'namespace' => 'production',
            ],
        ]),
    ]);

    $result = $this->resource->setName('api-deployment')->create();

    expect($result)
        ->toBeInstanceOf(Deployment::class)
        ->getName()
        ->toBe('api-deployment')
        ->getNamespace()
        ->toBe('production');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://localhost/apis/apps/v1/namespaces/production/deployments?pretty=1' &&
               $request->method() === 'POST' &&
               $request->isJson() &&
               $request->hasHeader('Authorization', 'Bearer secret') &&
               $request->hasHeader('User-Agent', 'Pest Tests') &&
               $request->data() === [
                   'apiVersion' => 'apps/v1',
                   'kind' => 'Deployment',
                   'metadata' => [
                       'namespace' => 'production',
                       'name' => 'api-deployment',
                   ],
               ];
    });
});

it('makes put request to update specific resource', function () {
    Http::fake([
        '*' => Http::response([
            'metadata' => [
                'name' => 'api-deployment',
                'namespace' => 'production',
            ],
            'spec' => [
                'replicas' => 2,
            ],
        ]),
    ]);

    $result = $this->resource->setName('api-deployment')->setReplicas(2)->update();

    expect($result)
        ->toBeInstanceOf(Deployment::class)
        ->getName()
        ->toBe('api-deployment')
        ->getNamespace()
        ->toBe('production')
        ->getReplicas()
        ->toBe(2);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://localhost/apis/apps/v1/namespaces/production/deployments/api-deployment?pretty=1' &&
               $request->method() === 'PUT' &&
               $request->isJson() &&
               $request->hasHeader('Authorization', 'Bearer secret') &&
               $request->hasHeader('User-Agent', 'Pest Tests') &&
               $request->data() === [
                   'apiVersion' => 'apps/v1',
                   'kind' => 'Deployment',
                   'metadata' => [
                       'namespace' => 'production',
                       'name' => 'api-deployment',
                   ],
                   'spec' => [
                       'replicas' => 2,
                   ],
               ];
    });
});

it('makes delete request to delete specific resource', function () {
    Http::fake([
        '*' => Http::response([
            'metadata' => [
                'name' => 'api-deployment',
                'namespace' => 'production',
            ],
        ]),
    ]);

    $result = $this->resource->setName('api-deployment')->delete();

    expect($result)
        ->toBeInstanceOf(Deployment::class)
        ->getName()
        ->toBe('api-deployment')
        ->getNamespace()
        ->toBe('production')
        ->exists()
        ->toBeFalse();

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://localhost/apis/apps/v1/namespaces/production/deployments/api-deployment?pretty=1' &&
               $request->method() === 'DELETE' &&
               $request->isJson() &&
               $request->hasHeader('Authorization', 'Bearer secret') &&
               $request->hasHeader('User-Agent', 'Pest Tests') &&
               $request->data() === [
                   'apiVersion' => 'v1',
                   'kind' => 'DeleteOptions',
                   'propagationPolicy' => 'Foreground',
               ];
    });
});

it('updates or creates resource based on existence - create', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push(['message' => 'Resource not found'], 404)
            ->push([
                'metadata' => ['name' => 'api-deployment', 'namespace' => 'production'],
            ]),
    ]);

    $result = $this->resource->setName('api-deployment')->updateOrCreate();

    expect($result)
        ->toBeInstanceOf(Deployment::class)
        ->wasRecentlyCreated()->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://localhost/apis/apps/v1/namespaces/production/deployments/api-deployment?pretty=1');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://localhost/apis/apps/v1/namespaces/production/deployments?pretty=1');
});

it('updates or creates resource based on existence - update carries resourceVersion', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push([
                'metadata' => [
                    'name' => 'api-deployment',
                    'namespace' => 'production',
                    'resourceVersion' => '4242',
                ],
            ])
            ->push([
                'metadata' => ['name' => 'api-deployment', 'namespace' => 'production'],
            ]),
    ]);

    $result = $this->resource->setName('api-deployment')->setReplicas(3)->updateOrCreate();

    expect($result)
        ->toBeInstanceOf(Deployment::class)
        ->wasRecentlyCreated()->toBeFalse();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === 'https://localhost/apis/apps/v1/namespaces/production/deployments/api-deployment?pretty=1'
        && $request->data()['metadata']['resourceVersion'] === '4242');
});

it('updates or creates rethrows non-404 errors from the existence check', function () {
    Http::fake([
        '*' => Http::response(['message' => 'Server Error'], 500),
    ]);

    $this->resource->setName('api-deployment')->updateOrCreate();
})->throws(KubernetesException::class, 'Server Error');
