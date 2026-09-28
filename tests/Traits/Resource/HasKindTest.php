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

it('gets plural version of kind', function () {
    $instance = new class
    {
        use HasKind;
    };

    $instance->setKind('deployment');

    expect($instance->getPluralKind())->toBe('deployments');
});

it('uses an explicit plural override when set', function () {
    $instance = new class
    {
        use HasKind;
    };

    $instance->setKind('NetworkPolicy')->setPlural('networkpolicies');

    expect($instance->getPluralKind())->toBe('networkpolicies');
});

it('pluralises irregular kinds from the kind when no override is set', function () {
    $instance = new class
    {
        use HasKind;
    };

    $instance->setKind('Ingress');

    expect($instance->getPluralKind())->toBe('ingresses');
});

it('sets kind to attributes', function () {
    $instance = new class
    {
        use HasKind;
    };

    $instance->setKind('deployment');

    expect($instance->getAttribute('kind'))->toBe('deployment');
});
