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

it('derives a valid DNS-1123 label as the volume name', function () {
    // Secret and ConfigMap names are DNS subdomains (dots, up to 253 characters); a pod
    // volume name must be a DNS-1123 label of at most 63, or the apiserver answers 422.
    $label = '/^[a-z0-9]([-a-z0-9]*[a-z0-9])?$/';
    $dotted = Volume::make()->fromSecret(Secret::make()->setName('app.example.com'));
    $long = Volume::make()->fromConfigMap(ConfigMap::make()->setName(str_repeat('a', 30).'.'.str_repeat('b', 24)));

    expect($dotted->getName())->toBe('app-example-com-secret-volume')
        ->and($dotted->getAttribute('secret'))->toBe(['secretName' => 'app.example.com'])
        ->and($long->getName())->toMatch($label)
        ->and(strlen($long->getName()))->toBeLessThanOrEqual(63)
        ->and($long->getName())->toEndWith('-config-volume')
        ->and($long->getAttribute('configMap'))->toBe(['name' => str_repeat('a', 30).'.'.str_repeat('b', 24)]);
});

it('uses an explicit volume name as given', function () {
    expect(Volume::make()->fromSecret(Secret::make()->setName('app.example.com'), 'tls')->getName())->toBe('tls')
        ->and(Volume::make()->fromConfigMap(ConfigMap::make()->setName('app.config'), 'config')->getName())->toBe('config');
});
