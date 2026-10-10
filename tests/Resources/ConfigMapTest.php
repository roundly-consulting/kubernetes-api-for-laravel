<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\ConfigMap;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Support\JsonPayload;

it('extends resource class', function () {
    expect(ConfigMap::class)->toUse(Resource::class);
});

it('has correct kind', function () {
    expect(ConfigMap::make()->getKind())->toBe('ConfigMap');
});

it('uses namespaces', function () {
    expect(ConfigMap::make()->usesNamespaces())->toBeTrue();
});

it('gets and sets data in data attribute', function () {
    $configMap = ConfigMap::make();

    expect($configMap->setData(['works' => true]))
        ->toBeInstanceOf(ConfigMap::class)
        ->and($configMap->getData())
        ->toBe([
            'works' => true,
        ])
        ->and($configMap->getData('works'))
        ->toBeTrue()
        ->and($configMap->getAttribute('data'))
        ->toBe([
            'works' => true,
        ]);
});

it('adds to data or removes from data', function () {
    $configMap = ConfigMap::make();

    $configMap->addData('works', 'yep');

    expect($configMap->getData('works'))->toBe('yep');

    $configMap->removeData('works');

    expect($configMap->getData('works'))->toBeNull();
});

it('keeps dotted data keys flat', function () {
    $cm = ConfigMap::make()->addData('nginx.conf', 'x')->addData('app.env', 'y');

    expect($cm->toArray()['data'])->toBe(['nginx.conf' => 'x', 'app.env' => 'y'])
        ->and($cm->getData('nginx.conf'))->toBe('x')
        ->and($cm->removeData('nginx.conf')->toArray()['data'])->toBe(['app.env' => 'y'])
        ->and(ConfigMap::make()->setData(['a' => ['b' => 'nested']])->getData('a.b', 'default'))->toBe('default');
});

it('drops the data map once its last key is removed, instead of sending a json list', function () {
    $cm = ConfigMap::make()->setName('c')->addData('nginx.conf', 'x')->removeData('nginx.conf');

    expect($cm->toArray())->not->toHaveKey('data')
        ->and($cm->toJson())->not->toContain('"data"')
        ->and($cm->getData())->toBe([]);
});

it('sends numeric-string data keys as an object, never a json list', function () {
    // PHP turns the keys "0", "1" into a list, which encodes as `["a","b"]` and gets a
    // 400 for a `map[string]string`.
    $decoded = ConfigMap::make(JsonPayload::decode('{"metadata":{"name":"c"},"data":{"0":"a","1":"b"}}'));

    expect($decoded->toJson())->toContain('"data":{"0":"a","1":"b"}')
        ->and(ConfigMap::make()->setName('c')->setData(['0' => 'a'])->toJson())->toContain('"data":{"0":"a"}')
        ->and(ConfigMap::make()->setAttribute('binaryData', ['0' => 'YQ=='])->toJson())->toContain('"binaryData":{"0":"YQ=="}');
});

it('sends numeric-string label and annotation keys as objects', function () {
    $json = ConfigMap::make()->setName('c')->setLabels(['0' => 'a'])->setAnnotations(['0' => 'b'])->toJson();

    expect($json)->toContain('"labels":{"0":"a"}')
        ->and($json)->toContain('"annotations":{"0":"b"}');
});

it('drops the data map when set to an empty one, instead of sending a json list', function () {
    $cm = ConfigMap::make()->setName('c')->setData(['a' => 'b'])->setData([]);

    expect($cm->toJson())->not->toContain('[]')
        ->and($cm->toArray())->not->toHaveKey('data')
        ->and($cm->getData())->toBe([]);
});

it('reads the data key "0" as that entry, and only a null key as the whole map', function () {
    $cm = ConfigMap::make(JsonPayload::decode('{"data":{"0":"a","1":"b"}}'));

    expect($cm->getData('0'))->toBe('a')
        ->and($cm->getData('1'))->toBe('b')
        ->and($cm->getData('2', 'fallback'))->toBe('fallback')
        ->and($cm->getData())->toBe(['0' => 'a', '1' => 'b'])
        ->and($cm->getData(null))->toBe(['0' => 'a', '1' => 'b']);
});
