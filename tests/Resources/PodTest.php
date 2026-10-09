<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Pod;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\Types\Container;
use RoundlyConsulting\KubernetesApi\Resources\Types\ContainerStatus;
use RoundlyConsulting\KubernetesApi\Resources\Types\EmptyObject;
use RoundlyConsulting\KubernetesApi\Resources\Types\Volume;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatusPhase;

it('extends resource class', function () {
    expect(Pod::class)->toUse([
        Resource::class,
        HasSpec::class,
        HasStatus::class,
        HasStatusPhase::class,
    ]);
});

it('has correct kind', function () {
    expect(Pod::make()->getKind())->toBe('Pod');
});

it('uses namespaces', function () {
    expect(Pod::make()->usesNamespaces())->toBeTrue();
});

it('sets containers to spec', function () {
    $pod = Pod::make();

    $pod->setContainers([
        Container::make()->setName('app'),
        Container::make()->setName('sidecar'),
    ]);

    expect($pod->getSpec('containers'))->toBe([
        ['name' => 'app'],
        ['name' => 'sidecar'],
    ]);
});

it('sets init containers to spec', function () {
    $pod = Pod::make();

    $pod->setInitContainers([
        Container::make()->setName('init-app'),
        Container::make()->setName('init-sidecar'),
    ]);

    expect($pod->getSpec('initContainers'))->toBe([
        ['name' => 'init-app'],
        ['name' => 'init-sidecar'],
    ]);
});

it('returns containers from spec', function () {
    $pod = Pod::make([
        'spec' => [
            'containers' => [
                ['name' => 'app'],
                ['name' => 'sidecar'],
            ],
        ],
    ]);

    $containers = $pod->getContainers();

    expect($containers)
        ->toBeArray()
        ->toHaveLength(2)
        ->and($containers[0])
        ->toBeInstanceOf(Container::class)
        ->getName()
        ->toBe('app');
});

it('returns init containers from spec', function () {
    $pod = Pod::make([
        'spec' => [
            'initContainers' => [
                ['name' => 'setup-app'],
            ],
        ],
    ]);

    $containers = $pod->getInitContainers();

    expect($containers)
        ->toBeArray()
        ->toHaveLength(1)
        ->and($containers[0])
        ->toBeInstanceOf(Container::class)
        ->getName()
        ->toBe('setup-app');
});

it('adds image pull secret', function () {
    $pod = Pod::make();

    $pod->addImagePullSecret('private-registry-creds');

    expect($pod->getSpec('imagePullSecrets'))->toBe([
        ['name' => 'private-registry-creds'],
    ]);
});

it('adds multiple pull secrets at once', function () {
    $pod = $this->partialMock(Pod::class);
    $pod->expects('addImagePullSecret')->once()->with('private-registry-creds');
    $pod->expects('addImagePullSecret')->once()->with('another-private-registry-creds');

    $pod->addImagePullSecrets([
        'private-registry-creds',
        'another-private-registry-creds',
    ]);
});

it('returns image pull secrets', function () {
    $pod = Pod::make([
        'spec' => [
            'imagePullSecrets' => [
                ['name' => 'private-registry'],
            ],
        ],
    ]);

    expect($pod->getImagePullSecrets())->toBe([
        ['name' => 'private-registry'],
    ]);
});

it('adds volume to spec', function () {
    $pod = Pod::make();

    $pod->addVolume(Volume::make()->emptyDirectory('temp'));

    expect($pod->getSpec('volumes'))->toEqual([
        [
            'emptyDir' => new EmptyObject,
            'name' => 'temp',
        ],
    ]);

    // The apiserver decodes `emptyDir` as a struct: it must go out as `{}`, not "{}".
    expect(json_decode($pod->setName('p')->toJson())->spec->volumes[0]->emptyDir)->toEqual(new stdClass);
});

it('adds multiple volumes at once', function () {
    $temp = Volume::make()->emptyDirectory('temp');
    $another = Volume::make()->emptyDirectory('another');

    $pod = $this->partialMock(Pod::class);
    $pod->expects('addVolume')->once()->with($temp);
    $pod->expects('addVolume')->once()->with($another);

    $pod->addVolumes([
        $temp,
        $another,
    ]);
});

it('sets volumes', function () {
    $pod = Pod::make([
        'spec' => [
            'volumes' => [
                [
                    'emptyDir' => new EmptyObject,
                    'name' => 'temp',
                ],
            ],
        ],
    ]);

    $pod->setVolumes([
        Volume::make()->emptyDirectory('logs'),
    ]);

    expect($pod->getSpec('volumes'))->toEqual([
        [
            'emptyDir' => new EmptyObject,
            'name' => 'logs',
        ],
    ]);
});

it('returns volumes', function () {
    $pod = Pod::make([
        'spec' => [
            'volumes' => [
                [
                    'emptyDir' => new EmptyObject,
                    'name' => 'temp',
                ],
            ],
        ],
    ]);

    $volumes = $pod->getVolumes();

    expect($volumes)
        ->toBeArray()
        ->toHaveLength(1)
        ->and($volumes[0])
        ->toBeInstanceOf(Volume::class)
        ->getName()
        ->toBe('temp')
        ->getEmptyDir()
        ->toBe([]);
});

it('returns container statuses', function () {
    $pod = Pod::make([
        'status' => [
            'containerStatuses' => [
                [
                    'name' => 'my-container',
                    'state' => [
                        'running' => [
                            'startedAt' => '2023-03-30 15:00:00',
                            'reason' => 'Container is healthly.',
                        ],
                    ],
                    'ready' => true,
                    'started' => true,
                    'restartCount' => 4,
                ],
            ],
        ],
    ]);

    $statuses = $pod->getContainerStatuses();

    expect($statuses)
        ->toBeArray()
        ->toHaveLength(1)
        ->and($statuses[0])
        ->toBeInstanceOf(ContainerStatus::class)
        ->restarts()->toBe(4)
        ->isStarted()->toBeTrue()
        ->isReady()->toBeTrue()
        ->getStateReason()->toBe('Container is healthly.')
        ->getStartedAt()->format('d.m.Y H:i')->toBe('30.03.2023 15:00')
        ->getState()->toBe('running');
});

it('returns init container statuses', function () {
    $pod = Pod::make([
        'status' => [
            'initContainerStatuses' => [
                [
                    'name' => 'my-container',
                    'state' => [
                        'running' => [
                            'startedAt' => '2023-03-30 15:00:00',
                            'reason' => 'Container is healthly.',
                        ],
                    ],
                    'ready' => true,
                    'started' => true,
                    'restartCount' => 4,
                ],
            ],
        ],
    ]);

    $statuses = $pod->getInitContainerStatuses();

    expect($statuses)
        ->toBeArray()
        ->toHaveLength(1)
        ->and($statuses[0])
        ->toBeInstanceOf(ContainerStatus::class)
        ->restarts()->toBe(4)
        ->isStarted()->toBeTrue()
        ->isReady()->toBeTrue()
        ->getStateReason()->toBe('Container is healthly.')
        ->getStartedAt()->format('d.m.Y H:i')->toBe('30.03.2023 15:00')
        ->getState()->toBe('running');
});

it('returns single container status for specific container', function () {
    $pod = Pod::make([
        'status' => [
            'containerStatuses' => [
                [
                    'name' => 'my-container',
                    'state' => [
                        'running' => [
                            'startedAt' => '2023-03-30 15:00:00',
                            'reason' => 'Container is healthly.',
                        ],
                    ],
                    'ready' => true,
                    'started' => true,
                    'restartCount' => 4,
                ],
            ],
        ],
    ]);

    expect($pod->getContainerStatus('my-container'))
        ->toBeInstanceOf(ContainerStatus::class)
        ->restarts()->toBe(4)
        ->isStarted()->toBeTrue()
        ->isReady()->toBeTrue()
        ->getStateReason()->toBe('Container is healthly.')
        ->getStartedAt()->format('d.m.Y H:i')->toBe('30.03.2023 15:00')
        ->getState()->toBe('running');
});

it('returns single container status for specific init container', function () {
    $pod = Pod::make([
        'status' => [
            'initContainerStatuses' => [
                [
                    'name' => 'my-container',
                    'state' => [
                        'running' => [
                            'startedAt' => '2023-03-30 15:00:00',
                            'reason' => 'Container is healthly.',
                        ],
                    ],
                    'ready' => true,
                    'started' => true,
                    'restartCount' => 4,
                ],
            ],
        ],
    ]);

    expect($pod->getInitContainerStatus('my-container'))
        ->toBeInstanceOf(ContainerStatus::class)
        ->restarts()->toBe(4)
        ->isStarted()->toBeTrue()
        ->isReady()->toBeTrue()
        ->getStateReason()->toBe('Container is healthly.')
        ->getStartedAt()->format('d.m.Y H:i')->toBe('30.03.2023 15:00')
        ->getState()->toBe('running');
});

it('returns boolean whether all containers are ready based on container statuses', function () {
    $pod = Pod::make([
        'status' => [
            'containerStatuses' => [
                [
                    'name' => 'my-container',
                    'ready' => true,
                ],
                [
                    'name' => 'another-container',
                    'ready' => true,
                ],
            ],
        ],
    ]);

    expect($pod->containersReady())->toBeTrue();

    $pod->setAttribute('status.containerStatuses.0.ready', false);

    expect($pod->containersReady())->toBeFalse();
});

it('returns boolean whether all init containers are ready based on container statuses', function () {
    $pod = Pod::make([
        'status' => [
            'initContainerStatuses' => [
                [
                    'name' => 'my-container',
                    'ready' => true,
                ],
                [
                    'name' => 'another-container',
                    'ready' => true,
                ],
            ],
        ],
    ]);

    expect($pod->initContainersReady())->toBeTrue();

    $pod->setAttribute('status.initContainerStatuses.0.ready', false);

    expect($pod->initContainersReady())->toBeFalse();
});

it('returns qos class from status while having default to BestEffort', function () {
    $pod = Pod::make([
        'status' => [
            'qosClass' => 'Burstable',
        ],
    ]);

    expect($pod->getQos())->toBe('Burstable');

    $pod->removeAttribute('status');

    expect($pod->getQos())->toBe('BestEffort');
});

it('returns status message', function () {
    $pod = Pod::make([
        'status' => [
            'message' => 'OOM',
        ],
    ]);

    expect($pod->getStatusMessage())->toBe('OOM');
});

it('checks whether status phase is running', function () {
    $pod = Pod::make([
        'status' => [
            'phase' => 'Running',
        ],
    ]);

    expect($pod->isRunning())->toBeTrue();

    $pod->removeAttributes();

    expect($pod->isRunning())->toBeFalse();
});

it('checks whether status phase is Succeeded', function () {
    $pod = Pod::make([
        'status' => [
            'phase' => 'Succeeded',
        ],
    ]);

    expect($pod->isSuccessful())->toBeTrue();

    $pod->removeAttributes();

    expect($pod->isSuccessful())->toBeFalse();
});

it('checks whether status phase is Failed', function () {
    $pod = Pod::make([
        'status' => [
            'phase' => 'Failed',
        ],
    ]);

    expect($pod->hasFailed())->toBeTrue();

    $pod->removeAttributes();

    expect($pod->hasFailed())->toBeFalse();
});
