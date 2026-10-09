<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;
use RoundlyConsulting\KubernetesApi\Resources\ConfigMap;
use RoundlyConsulting\KubernetesApi\Resources\Secret;
use RoundlyConsulting\KubernetesApi\Resources\Types\EmptyObject;
use RoundlyConsulting\KubernetesApi\Resources\Types\Volume;

it('creates volume mount instance with given volume name', function () {
    $volume = Volume::make(['name' => 'certificate']);

    expect($volume->mountTo('/etc/volumes', 'cert'))->toArray()->toBe([
        'mountPath' => '/etc/volumes',
        'name' => 'certificate',
        'subPath' => 'cert',
    ]);
});

it('sets volume as empty directory', function () {
    $volume = Volume::make()->emptyDirectory('temp');

    // `emptyDir` is a struct: with no options it must encode as the empty object
    // `{}`, never as the string "{}" the apiserver cannot decode.
    expect($volume->toArray())->toEqual([
        'emptyDir' => new EmptyObject,
        'name' => 'temp',
    ])->and($volume->toJson())->toBe('{"emptyDir":{},"name":"temp"}');

    $volume = Volume::make()->emptyDirectory('temp', ['sizeLimit' => '500Mi']);

    expect($volume->toArray())->toBe([
        'emptyDir' => [
            'sizeLimit' => '500Mi',
        ],
        'name' => 'temp',
    ]);
});

it('sets volume source from secret', function () {
    $secret = $this->mock(Secret::class);
    $secret->shouldReceive('getName')->once()->andReturn('app-key');

    $volume = Volume::make()->fromSecret($secret);

    expect($volume->toArray())->toBe([
        'name' => 'app-key-secret-volume',
        'secret' => [
            'secretName' => 'app-key',
        ],
    ]);
});

it('sets volume source from config map', function () {
    $configMap = $this->mock(ConfigMap::class);
    $configMap->shouldReceive('getName')->once()->andReturn('app-config');

    $volume = Volume::make()->fromConfigMap($configMap);

    expect($volume->toArray())->toBe([
        'configMap' => [
            'name' => 'app-config',
        ],
        'name' => 'app-config-config-volume',
    ]);
});

it('refuses a volume from an unnamed secret or config map with a typed exception', function (Closure $call) {
    expect($call)->toThrow(InvalidResourceException::class, 'A resource name is required');
})->with([
    'secret' => [fn () => Volume::make()->fromSecret(Secret::make())],
    'config map' => [fn () => Volume::make()->fromConfigMap(ConfigMap::make())],
]);
