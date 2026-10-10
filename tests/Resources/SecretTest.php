<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\Secret;
use RoundlyConsulting\KubernetesApi\Support\JsonPayload;

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

it('returns null type by default and round-trips a set type', function () {
    expect(Secret::make()->getType())->toBeNull();

    $secret = Secret::make()->setType('Opaque');

    expect($secret->getType())->toBe('Opaque')
        ->and($secret->getAttribute('type'))->toBe('Opaque');
});

it('builds a kubernetes.io/tls secret from a cert and key', function () {
    $cert = "-----BEGIN CERTIFICATE-----\nMIICert\n-----END CERTIFICATE-----\n";
    $key = "-----BEGIN PRIVATE KEY-----\nMIIKey\n-----END PRIVATE KEY-----\n";

    $secret = Secret::make()->setName('tls')->asTlsCertificate($cert, $key);

    expect($secret->getType())->toBe('kubernetes.io/tls');

    // tls.crt / tls.key are literal data keys (with a dot), not a nested path.
    expect($secret->getAttribute('data'))
        ->toBe([
            'tls.crt' => base64_encode($cert),
            'tls.key' => base64_encode($key),
        ]);

    // The base64 round-trips back to the original PEM through getData().
    expect($secret->getData())
        ->toBe([
            'tls.crt' => $cert,
            'tls.key' => $key,
        ])
        ->and($secret->getData('tls.crt'))->toBe($cert)
        ->and($secret->getData('tls.key'))->toBe($key);
});

it('keeps dotted data keys flat', function () {
    $secret = Secret::make()->addData('tls.crt', 'PEM')->addData('.dockerconfigjson', '{}');

    expect($secret->toArray()['data'])->toBe(['tls.crt' => base64_encode('PEM'), '.dockerconfigjson' => base64_encode('{}')])
        ->and($secret->getData('tls.crt'))->toBe('PEM')
        ->and($secret->getData('.dockerconfigjson'))->toBe('{}')
        ->and(array_keys(Secret::make()->asTlsCertificate('C', 'K')->removeData('tls.crt')->toArray()['data']))->toBe(['tls.key']);
});

it('drops the data map once its last key is removed, instead of sending a json list', function () {
    $secret = Secret::make()->setName('s')->addData('tls.crt', 'PEM')->removeData('tls.crt');

    expect($secret->toArray())->not->toHaveKey('data')
        ->and($secret->getData())->toBe([]);
});

it('sends numeric-string data keys as an object, never a json list', function () {
    $decoded = Secret::make(JsonPayload::decode('{"metadata":{"name":"s"},"data":{"0":"YQ=="}}'));

    expect($decoded->toJson())->toContain('"data":{"0":"YQ=="}')
        ->and(Secret::make()->setName('s')->setData(['0' => 'a'])->toJson())->toContain('"data":{"0":"YQ=="}')
        ->and(Secret::make()->setAttribute('stringData', ['0' => 'a'])->toJson())->toContain('"stringData":{"0":"a"}');
});

it('drops the data map when set to an empty one, instead of sending a json list', function () {
    $secret = Secret::make()->setName('s')->setData(['a' => 'b'])->setData([]);

    expect($secret->toJson())->not->toContain('[]')
        ->and($secret->toArray())->not->toHaveKey('data')
        ->and($secret->getData())->toBe([]);
});

it('reads the data key "0" as that entry, and only a null key as the whole map', function () {
    $secret = Secret::make(JsonPayload::decode('{"data":{"0":"YQ==","1":"Yg=="}}'));

    expect($secret->getData('0'))->toBe('a')
        ->and($secret->getData('1'))->toBe('b')
        ->and($secret->getData('2', 'fallback'))->toBe('fallback')
        ->and($secret->getData())->toBe(['0' => 'a', '1' => 'b'])
        ->and($secret->getData(null))->toBe(['0' => 'a', '1' => 'b']);
});
