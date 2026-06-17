<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Kubernetes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasCluster;

it('sets and gets cluster', function () {
    $instance = new class
    {
        use HasCluster;
    };

    expect($instance->getCluster())->toBeNull();

    $cluster = $this->mock(Kubernetes::class);

    $instance->setCluster($cluster);

    expect($instance->getCluster())->toBe($cluster);
});
