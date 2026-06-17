<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Node;
use RoundlyConsulting\KubernetesApi\Resources\Resource;

it('extends resource class', function () {
    expect(Node::class)->toUse(Resource::class);
});

it('has correct kind', function () {
    expect(Node::make()->getKind())->toBe('Node');
});

it('does not use namespaces', function () {
    expect(Node::make()->usesNamespaces())->toBeFalse();
});

it('has status', function () {
    $node = Node::make(['status' => ['nodeInfo' => ['osImage' => 'Ubuntu']]]);

    expect($node->getStatus('nodeInfo'))
        ->toBe(['osImage' => 'Ubuntu']);
});

it('gets info from node info in status', function () {
    $node = Node::make(['status' => ['nodeInfo' => ['osImage' => 'Ubuntu']]]);

    expect($node->getInfo())
        ->toBe(['osImage' => 'Ubuntu']);
});

it('gets images from images in status', function () {
    $node = Node::make(['status' => ['images' => ['ubuntu', 'debian']]]);

    expect($node->getImages())
        ->toBe(['ubuntu', 'debian']);
});

it('gets capacity from capacity in status', function () {
    $node = Node::make(['status' => ['capacity' => ['memory' => '8141104Ki']]]);

    expect($node->getCapacity())
        ->toBe(['memory' => '8141104Ki']);
});

it('gets allocatable info from allocatable in status', function () {
    $node = Node::make(['status' => ['allocatable' => ['memory' => '5485872Ki']]]);

    expect($node->getAllocatableInfo())
        ->toBe(['memory' => '5485872Ki']);
});
