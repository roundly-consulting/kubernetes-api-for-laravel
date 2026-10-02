<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\Pod;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasTemplate;

it('uses required traits', function () {
    expect(HasTemplate::class)->toUse([
        HasSpec::class,
    ]);
});

it('sets the template from a pod, keeping only the PodTemplateSpec fields', function () {
    $instance = new class
    {
        use HasTemplate;
    };

    $instance->setTemplate(Pod::make()->setName('ignored-kind')->setLabels(['app' => 'web'])->setSpec('restartPolicy', 'Always'));

    // apiVersion/kind are not template fields: a strict apiserver refuses them.
    expect($instance->getSpec('template'))->toBe([
        'metadata' => ['name' => 'ignored-kind', 'labels' => ['app' => 'web']],
        'spec' => ['restartPolicy' => 'Always'],
    ]);
});

it('returns template from spec', function () {
    $instance = new class
    {
        use HasTemplate;
    };

    $instance->setSpec('template', ['metadata' => [
        'name' => 'test',
    ]]);

    expect($instance->getTemplate())
        ->toBeInstanceOf(Pod::class)
        ->toArray()
        ->toBe([
            'apiVersion' => 'v1',
            'kind' => 'Pod',
            'metadata' => [
                'name' => 'test',
            ],
        ]);
});
