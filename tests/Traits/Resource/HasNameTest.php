<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;
use RoundlyConsulting\KubernetesApi\Resources\Job;
use RoundlyConsulting\KubernetesApi\Resources\Pod;
use RoundlyConsulting\KubernetesApi\Resources\Service;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAttributes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasName;

it('uses required traits', function () {
    expect(HasName::class)->toUse([
        HasAttributes::class,
    ]);
});

it('sets and gets name', function () {
    $instance = new class
    {
        use HasName;
    };

    $instance->setName('beta');

    expect($instance->getName())->toBe('beta');
});

it('uses attribute metadata to store and retrieve name', function () {
    $instance = new class
    {
        use HasName;
    };

    $instance->setAttribute('metadata.name', 'beta');
    expect($instance->getName())->toBe('beta');

    $instance->setName('staging');
    expect($instance->getAttribute('metadata.name'))->toBe('staging');
});

it('returns null for a resource without a name', function () {
    // Unnamed resources are normal (pod templates, generateName): no TypeError.
    expect(Pod::make()->getName())->toBeNull()
        ->and(Deployment::make()->setTemplate(Pod::make()->setLabels(['app' => 'web']))->getTemplate()->getName())->toBeNull();
});

it('refuses name-dependent helpers on an unnamed resource with a typed exception', function (Closure $call) {
    expect($call)->toThrow(InvalidResourceException::class, 'A resource name is required');
})->with([
    'job pod selectors' => [fn () => Job::make()->podsSelectors()],
    'service cluster dns' => [fn () => Service::make()->getClusterDns()],
]);
