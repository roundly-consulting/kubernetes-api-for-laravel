<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAttributes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasKind;

it('uses required traits', function () {
    expect(HasKind::class)->toUse([
        HasAttributes::class,
    ]);
});

it('sets and gets kind of resource', function () {
    $instance = new class
    {
        use HasKind;
    };

    $instance->setKind('deployment');

    expect($instance->getKind())->toBe('deployment');
});

it('gets default kind from property', function () {
    $instance = new class
    {
        use HasKind;

        public function setDefaultKind(string $kind): void
        {
            $this->kind = $kind;
        }
    };

    $instance->setDefaultKind('service');

    expect($instance->getKind())->toBe('service');
});

it('gets plurar version of kind', function () {
    $instance = new class
    {
        use HasKind;
    };

    $instance->setKind('deployment');

    expect($instance->getPlurarKind())->toBe('deployments');
});

it('sets kind to attributes', function () {
    $instance = new class
    {
        use HasKind;
    };

    $instance->setKind('deployment');

    expect($instance->getAttribute('kind'))->toBe('deployment');
});
