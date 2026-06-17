<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

expect()->extend('protected', function (string $method, array $args = []) {
    $class = new ReflectionClass($this->value);
    $method = $class->getMethod($method);
    $method->setAccessible(true);

    $this->value = $method->invokeArgs($this->value, $args);

    return $this;
});
