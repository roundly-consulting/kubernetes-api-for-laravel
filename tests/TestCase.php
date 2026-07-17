<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\HttpClientRateLimits\HttpClientRateLimitsServiceProvider;
use RoundlyConsulting\KubernetesApi\KubernetesApiServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider kubernetes-api hard-requires, in registration order — the
     * rate-limiter a host auto-discovers first, then the package itself.
     *
     * This package ships no migrations and opens no database connection: it is an HTTP
     * client for a cluster API. `migrationSources()` is therefore left at the base
     * case's empty default rather than pointed at a fixture directory.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [
            HttpClientRateLimitsServiceProvider::class,
            KubernetesApiServiceProvider::class,
        ];
    }
}
