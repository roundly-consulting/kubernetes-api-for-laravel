<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\Secret;

it('extends resource class', function () {
    expect(Secret::class)->toUse(Resource::class);
});

it('has correct kind', function () {
    expect(Secret::make()->getKind())->toBe('Secret');
});

it('uses namespaces', function () {
    expect(Secret::make()->usesNamespaces())->toBeTrue();
});

it('sets data and automatically encrypts it to base64', function () {
    $secret = Secret::make();

    $secret->setData(['name' => 'John']);

    expect($secret->getAttribute('data'))
        ->toBe([
            'name' => 'Sm9obg==',
        ]);
});

it('gets data and decrypts it on the fly', function () {
    $secret = Secret::make(['data' => ['name' => 'Sm9obg==']]);

    expect($secret)
        ->getData()
        ->toBe([
            'name' => 'John',
        ])
        ->getData('name')
        ->toBe('John')
        ->getData('age')
        ->toBeNull()
        ->getData('age', '30')
        ->toBe('30');
});

it('adds to data', function () {
    $secret = Secret::make(['data' => ['name' => 'Sm9obg==']]);

    $secret->addData('age', '20');

    expect($secret->getAttribute('data'))
        ->toBe([
            'name' => 'Sm9obg==',
            'age' => 'MjA=',
        ]);
});

it('removes from data', function () {
    $secret = Secret::make([
        'name' => 'Sm9obg==',
        'age' => 'MjA=',
    ]);

    $secret->removeData('age');

    expect($secret->getData('age'))->toBeNull();
});
