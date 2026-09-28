<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\ConfigMap;
use RoundlyConsulting\KubernetesApi\Resources\Resource;

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
