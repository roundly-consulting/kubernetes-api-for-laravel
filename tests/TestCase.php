<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\HttpClientRateLimits\HttpClientRateLimitsServiceProvider;
use RoundlyConsulting\KubernetesApi\KubernetesApiServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            HttpClientRateLimitsServiceProvider::class,
            KubernetesApiServiceProvider::class,
        ];
    }
}
