<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Types\Container;
use RoundlyConsulting\KubernetesApi\Resources\Types\Probe;
use RoundlyConsulting\KubernetesApi\Resources\Types\VolumeMount;

it('sets container image with tag', function () {
    $container = Container::make();

    $container->setImage('alpine', '3');

    expect($container->toArray())
        ->toBe([
            'image' => 'alpine:3',
        ]);
});

it('keeps an image reference that already has a digest or a tag', function () {
    // setImage() used to append `:latest` to everything: `nginx:1.27:latest` and a
    // digest-pinned `…@sha256:…:latest` are both invalid image names.
    $digest = 'nginx@sha256:'.str_repeat('a1', 32);

    expect(Container::make()->setImage($digest)->getAttribute('image'))->toBe($digest)
        ->and(Container::make()->setImage('nginx:1.27')->getAttribute('image'))->toBe('nginx:1.27')
        ->and(Container::make()->setImage('registry.local:5000/app:2.0')->getAttribute('image'))->toBe('registry.local:5000/app:2.0')
        ->and(Container::make()->setImage('registry.local:5000/app')->getAttribute('image'))->toBe('registry.local:5000/app:latest')
        ->and(Container::make()->setImage('nginx')->getAttribute('image'))->toBe('nginx:latest')
        ->and(Container::make()->setImage('nginx', '1.27')->getAttribute('image'))->toBe('nginx:1.27');
});

it('sets an image without a tag when the tag is null', function () {
    expect(Container::make()->setImage('nginx', null)->getAttribute('image'))->toBe('nginx');
});

it('adds port to container', function () {
    $container = Container::make();

    $container->addPort(8080);

    expect($container->toArray())
        ->toBe([
            'ports' => [
                [
                    'protocol' => 'TCP',
                    'containerPort' => 8080,
                ],
            ],
        ]);

    $container->addPort(
        containerPort: 8888,
        protocol: 'UDP',
        name: 'Socket',
    );

    expect($container->toArray())
        ->toBe([
            'ports' => [
                [
                    'protocol' => 'TCP',
                    'containerPort' => 8080,
                ],
                [
                    'protocol' => 'UDP',
                    'containerPort' => 8888,
                    'name' => 'Socket',
                ],
            ],
        ]);
});

it('adds volume mount to container', function () {
    $container = Container::make();

    $container->addVolumeMount(VolumeMount::make()->mountTo('/etc/cert', 'prod'));

    expect($container->getAttribute('volumeMounts'))->toBe([
        [
            'mountPath' => '/etc/cert',
            'subPath' => 'prod',
        ],
    ]);
});

it('adds multiple volume mounts to container at once', function () {
    $first = VolumeMount::make()->mountTo('/etc/certs', 'prod');
    $second = VolumeMount::make()->mountTo('/etc/certs', 'staging');

    $container = $this->partialMock(Container::class);
    $container->expects('addVolumeMount')->once()->with($first);
    $container->expects('addVolumeMount')->once()->with($second);

    $container->addVolumeMounts([$first, $second]);
});

it('sets volume mounts to container', function () {
    $container = Container::make([
        'volumeMounts' => [
            [
                'mountPath' => '/etc/something',
                'subPath' => 'ok',
            ],
        ],
    ]);

    $container->setVolumeMounts([
        VolumeMount::make()->mountTo('/etc/cert', 'prod'),
    ]);

    expect($container->getAttribute('volumeMounts'))->toBe([
        [
            'mountPath' => '/etc/cert',
            'subPath' => 'prod',
        ],
    ]);
});

it('returns volume mounts', function () {
    $container = Container::make([
        'volumeMounts' => [
            [
                'mountPath' => '/etc/something',
                'subPath' => 'ok',
            ],
        ],
    ]);

    $vm = $container->getVolumeMounts();

    expect($vm)
        ->toBeArray()
        ->toHaveLength(1)
        ->and($vm[0])
        ->toBeInstanceOf(VolumeMount::class)
        ->getMountPath()
        ->toBe('/etc/something')
        ->getSubPath()
        ->toBe('ok');
});

it('adds to env from secret', function () {
    $container = Container::make();
    $container->addToEnvironmentFromSecret('APP_KEY', 'app-env', 'key');

    expect($container->getAttribute('env'))->toBe([
        [
            'name' => 'APP_KEY',
            'valueFrom' => [
                'secretKeyRef' => [
                    'name' => 'app-env',
                    'key' => 'key',
                ],
            ],
        ],
    ]);
});

it('adds multiple env variables from secrets', function () {
    $container = Container::make();
    $container->addToEnvironmentFromSecrets([
        'APP_KEY' => ['app-env', 'key'],
        'APP_SECRET' => [
            'secretName' => 'sidecar-env',
            'key' => 'secret',
        ],
    ]);

    expect($container->getAttribute('env'))->toBe([
        [
            'name' => 'APP_KEY',
            'valueFrom' => [
                'secretKeyRef' => [
                    'name' => 'app-env',
                    'key' => 'key',
                ],
            ],
        ],
        [
            'name' => 'APP_SECRET',
            'valueFrom' => [
                'secretKeyRef' => [
                    'name' => 'sidecar-env',
                    'key' => 'secret',
                ],
            ],
        ],
    ]);
});

it('adds to env from config map', function () {
    $container = Container::make();
    $container->addToEnvironmentFromConfigMap('APP_KEY', 'app-setup', 'key');

    expect($container->getAttribute('env'))->toBe([
        [
            'name' => 'APP_KEY',
            'valueFrom' => [
                'configMapKeyRef' => [
                    'name' => 'app-setup',
                    'key' => 'key',
                ],
            ],
        ],
    ]);
});

it('adds multiple env variables from config maps', function () {
    $container = Container::make();
    $container->addToEnvironmentFromConfigMaps([
        'APP_KEY' => ['app-setup', 'key'],
        'APP_SECRET' => [
            'configMapName' => 'sidecar-setup',
            'key' => 'secret',
        ],
    ]);

    expect($container->getAttribute('env'))->toBe([
        [
            'name' => 'APP_KEY',
            'valueFrom' => [
                'configMapKeyRef' => [
                    'name' => 'app-setup',
                    'key' => 'key',
                ],
            ],
        ],
        [
            'name' => 'APP_SECRET',
            'valueFrom' => [
                'configMapKeyRef' => [
                    'name' => 'sidecar-setup',
                    'key' => 'secret',
                ],
            ],
        ],
    ]);
});

it('adds to env from field reference', function () {
    $container = Container::make();
    $container->addToEnvironmentFromFieldReference('APP_NAME', 'metadata.name');

    expect($container->getAttribute('env'))->toBe([
        [
            'name' => 'APP_NAME',
            'valueFrom' => [
                'fieldRef' => [
                    'fieldPath' => 'metadata.name',
                ],
            ],
        ],
    ]);
});

it('adds multiple env variables from field references', function () {
    $container = Container::make();
    $container->addToEnvironmentFromFieldReferences([
        'APP_NAME' => 'metadata.name',
        'APP_NAMESPACE' => 'metadata.namespace',
    ]);

    expect($container->getAttribute('env'))->toBe([
        [
            'name' => 'APP_NAME',
            'valueFrom' => [
                'fieldRef' => [
                    'fieldPath' => 'metadata.name',
                ],
            ],
        ],
        [
            'name' => 'APP_NAMESPACE',
            'valueFrom' => [
                'fieldRef' => [
                    'fieldPath' => 'metadata.namespace',
                ],
            ],
        ],
    ]);
});

it('adds value to env', function () {
    $container = Container::make();

    $container->addToEnvironment('APP_NAME', 'SayHalo');
    $container->addToEnvironment([
        'name' => 'APP_KEY',
        'valueFrom' => [
            'secretKeyRef' => [
                'name' => 'app-env',
                'key' => 'key',
            ],
        ],
    ]);

    expect($container->getAttribute('env'))->toBe([
        [
            'name' => 'APP_NAME',
            'value' => 'SayHalo',
        ],
        [
            'name' => 'APP_KEY',
            'valueFrom' => [
                'secretKeyRef' => [
                    'name' => 'app-env',
                    'key' => 'key',
                ],
            ],
        ],
    ]);
});

it('adds multiple envs at once', function () {
    $container = $this->partialMock(Container::class);
    $container->expects('addToEnvironment')->once()->with('APP_NAME', 'SayHalo');
    $container->expects('addToEnvironment')->once()->with([
        'name' => 'APP_KEY',
        'valueFrom' => [
            'secretKeyRef' => [
                'name' => 'app-env',
                'key' => 'key',
            ],
        ],
    ]);

    $container->addMultipleToEnvironment([
        'APP_NAME' => 'SayHalo',
        [
            'name' => 'APP_KEY',
            'valueFrom' => [
                'secretKeyRef' => [
                    'name' => 'app-env',
                    'key' => 'key',
                ],
            ],
        ],
    ]);
});

it('sets env', function () {
    $container = $this->partialMock(Container::class);
    $container->expects('removeAttribute')->once()->with('env');
    $container->expects('addMultipleToEnvironment')->once()->with([
        'APP_NAME' => 'SayHalo',
        [
            'name' => 'APP_KEY',
            'valueFrom' => [
                'secretKeyRef' => [
                    'name' => 'app-env',
                    'key' => 'key',
                ],
            ],
        ],
    ]);

    $container->setEnvironment([
        'APP_NAME' => 'SayHalo',
        [
            'name' => 'APP_KEY',
            'valueFrom' => [
                'secretKeyRef' => [
                    'name' => 'app-env',
                    'key' => 'key',
                ],
            ],
        ],
    ]);
});

it('sets minimum memory', function () {
    $container = Container::make();

    $container->setMinimumMemory(128);
    expect($container->getAttribute('resources.requests.memory'))->toBe('128Gi');

    $container->setMinimumMemory(128, 'Ki');
    expect($container->getAttribute('resources.requests.memory'))->toBe('128Ki');
});

it('returns minimum memory', function () {
    $container = Container::make();
    $container->setAttribute('resources.requests.memory', '128Gi');

    expect($container->getMinimumMemory())->toBe('128Gi');
});

it('sets maximum memory', function () {
    $container = Container::make();

    $container->setMaximumMemory(128);
    expect($container->getAttribute('resources.limits.memory'))->toBe('128Gi');

    $container->setMaximumMemory(128, 'Ki');
    expect($container->getAttribute('resources.limits.memory'))->toBe('128Ki');
});

it('returns maximum memory', function () {
    $container = Container::make();
    $container->setAttribute('resources.limits.memory', '128Gi');

    expect($container->getMaximumMemory())->toBe('128Gi');
});

it('sets minimum cpu', function () {
    $container = Container::make();

    $container->setMinimumCpu('500m');
    expect($container->getAttribute('resources.requests.cpu'))->toBe('500m');
});

it('returns minimum cpu', function () {
    $container = Container::make();
    $container->setAttribute('resources.requests.cpu', '500m');

    expect($container->getMinimumCpu())->toBe('500m');
});

it('sets maximum cpu', function () {
    $container = Container::make();

    $container->setMaximumCpu('500m');
    expect($container->getAttribute('resources.limits.cpu'))->toBe('500m');
});

it('returns maximum cpu', function () {
    $container = Container::make();
    $container->setAttribute('resources.limits.cpu', '500m');

    expect($container->getMaximumCpu())->toBe('500m');
});

it('sets readliness probe', function () {
    $container = Container::make();

    $container->setReadinessProbe(Probe::http());

    expect($container->getAttribute('readinessProbe'))
        ->toBe([
            'failureThreshold' => 1,
            'httpGet' => [
                'path' => '/healthz',
                'port' => 8080,
                'scheme' => 'HTTP',
            ],
            'successThreshold' => 1,
        ]);

    $container->setReadinessProbe(Probe::http(
        path: '/probe',
        port: 4443,
        headers: ['User-Agent' => 'Probe'],
        scheme: 'HTTPS',
    ));

    expect($container->getAttribute('readinessProbe'))
        ->toBe([
            'failureThreshold' => 1,
            'httpGet' => [
                'path' => '/probe',
                'port' => 4443,
                'scheme' => 'HTTPS',
                'httpHeaders' => [
                    [
                        'name' => 'User-Agent',
                        'value' => 'Probe',
                    ],
                ],
            ],
            'successThreshold' => 1,
        ]);
});

it('gets readliness probe', function () {
    $container = Container::make([
        'readinessProbe' => [
            'failureThreshold' => 1,
            'httpGet' => [
                'path' => '/healthz',
                'port' => 8080,
                'scheme' => 'HTTP',
            ],
            'successThreshold' => 1,
        ],
    ]);

    expect($container->getReadinessProbe())
        ->toBeInstanceOf(Probe::class)
        ->getHttpGet()->toBe([
            'path' => '/healthz',
            'port' => 8080,
            'scheme' => 'HTTP',
        ])
        ->getFailureThreshold()->toBe(1)
        ->getSuccessThreshold()->toBe(1);
});

it('sets liveness probe', function () {
    $container = Container::make();

    $container->setLivenessProbe(Probe::http());

    expect($container->getAttribute('livenessProbe'))
        ->toBe([
            'failureThreshold' => 1,
            'httpGet' => [
                'path' => '/healthz',
                'port' => 8080,
                'scheme' => 'HTTP',
            ],
            'successThreshold' => 1,
        ]);

    $container->setLivenessProbe(Probe::http(
        path: '/probe',
        port: 4443,
        headers: ['User-Agent' => 'Probe'],
        scheme: 'HTTPS',
    ));

    expect($container->getAttribute('livenessProbe'))
        ->toBe([
            'failureThreshold' => 1,
            'httpGet' => [
                'path' => '/probe',
                'port' => 4443,
                'scheme' => 'HTTPS',
                'httpHeaders' => [
                    [
                        'name' => 'User-Agent',
                        'value' => 'Probe',
                    ],
                ],
            ],
            'successThreshold' => 1,
        ]);
});

it('gets liveness probe', function () {
    $container = Container::make([
        'livenessProbe' => [
            'failureThreshold' => 1,
            'httpGet' => [
                'path' => '/healthz',
                'port' => 8080,
                'scheme' => 'HTTP',
            ],
            'successThreshold' => 1,
        ],
    ]);

    expect($container->getLivenessProbe())
        ->toBeInstanceOf(Probe::class)
        ->getHttpGet()->toBe([
            'path' => '/healthz',
            'port' => 8080,
            'scheme' => 'HTTP',
        ])
        ->getFailureThreshold()->toBe(1)
        ->getSuccessThreshold()->toBe(1);
});

it('sets startup probe', function () {
    $container = Container::make();

    $container->setStartupProbe(Probe::http());

    expect($container->getAttribute('startupProbe'))
        ->toBe([
            'failureThreshold' => 1,
            'httpGet' => [
                'path' => '/healthz',
                'port' => 8080,
                'scheme' => 'HTTP',
            ],
            'successThreshold' => 1,
        ]);

    $container->setStartupProbe(Probe::http(
        path: '/probe',
        port: 4443,
        headers: ['User-Agent' => 'Probe'],
        scheme: 'HTTPS',
    ));

    expect($container->getAttribute('startupProbe'))
        ->toBe([
            'failureThreshold' => 1,
            'httpGet' => [
                'path' => '/probe',
                'port' => 4443,
                'scheme' => 'HTTPS',
                'httpHeaders' => [
                    [
                        'name' => 'User-Agent',
                        'value' => 'Probe',
                    ],
                ],
            ],
            'successThreshold' => 1,
        ]);
});

it('gets startup probe', function () {
    $container = Container::make([
        'startupProbe' => [
            'failureThreshold' => 1,
            'httpGet' => [
                'path' => '/healthz',
                'port' => 8080,
                'scheme' => 'HTTP',
            ],
            'successThreshold' => 1,
        ],
    ]);

    expect($container->getStartupProbe())
        ->toBeInstanceOf(Probe::class)
        ->getHttpGet()->toBe([
            'path' => '/healthz',
            'port' => 8080,
            'scheme' => 'HTTP',
        ])
        ->getFailureThreshold()->toBe(1)
        ->getSuccessThreshold()->toBe(1);
});

it('returns boolean whether container is ready', function () {
    $container = Container::make([
        'ready' => true,
    ]);

    expect($container->isReady())->toBeTrue();
});
