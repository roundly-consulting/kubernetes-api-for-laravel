<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAttributes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasVersion;

it('uses required traits', function () {
    expect(HasVersion::class)->toUse([
        HasAttributes::class,
    ]);
});

it('uses default when no version is defined', function () {
    $instance = new class
    {
        use HasVersion;

        public function setDefaultVersion(string $version): void
        {
            $this->version = $version;
        }
    };

    expect($instance->getVersion())->toBe('v1');

    $instance->setDefaultVersion('v2');

    expect($instance->getVersion())->toBe('v2');
});

it('gets version from attribute', function () {
    $instance = new class
    {
        use HasVersion;
    };

    // Default
    expect($instance->getVersion())->toBe('v1');

    $instance->setAttribute('apiVersion', 'v2beta');

    expect($instance->getVersion())->toBe('v2beta');
});

it('sets version to attribute', function () {
    $instance = new class
    {
        use HasVersion;
    };

    $instance->setVersion('v2beta');

    expect($instance->getAttribute('apiVersion'))->toBe('v2beta');
});
