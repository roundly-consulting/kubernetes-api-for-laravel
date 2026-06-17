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

it('sets template from pod instance to spec', function () {
    $instance = new class
    {
        use HasTemplate;
    };

    $pod = $this->mock(Pod::class);
    $pod->expects('toArray')->once()->andReturn(['ok' => 'yes']);

    $instance->setTemplate($pod);

    expect($instance->getSpec('template'))->toBe(['ok' => 'yes']);
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
