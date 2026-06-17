<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\Types\EmptyObject;

it('serialises to an empty json object', function () {
    expect(json_encode(new EmptyObject))->toBe('{}');
});

it('emits empty objects only where marked and preserves real empty lists and strings', function () {
    $resource = new Resource([
        'apiVersion' => 'v1',
        'kind' => 'ConfigMap',
        'spec' => new EmptyObject,
        'metadata' => [
            'finalizers' => [],
            'annotations' => ['note' => 'see []'],
        ],
    ]);

    $json = $resource->toJson();

    expect($json)
        ->toContain('"spec":{}')
        ->toContain('"finalizers":[]')
        ->toContain('"note":"see []"');
});
