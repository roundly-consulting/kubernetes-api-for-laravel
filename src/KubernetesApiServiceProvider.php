<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi;

use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use Acme\LaravelPackageTools\Package;
use Acme\LaravelPackageTools\PackageServiceProvider;

final class KubernetesApiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('kubernetes')
            ->hasConfigFile('kubernetes');
    }

    public function packageRegistered(): void
    {
        /** @var array<string, class-string<Resources\Resource>> $resources */
        $resources = config('kubernetes.resources', []);

        foreach ($resources as $name => $resourceClassName) {
            Kubernetes::registerResource($name, $resourceClassName);
        }
    }
}
