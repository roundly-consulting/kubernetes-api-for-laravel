<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi;

use RoundlyConsulting\KubernetesApi\Commands\PingCommand;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use Acme\LaravelPackageTools\Commands\InstallCommand;
use Acme\LaravelPackageTools\Package;
use Acme\LaravelPackageTools\PackageServiceProvider;

final class KubernetesApiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('kubernetes')
            ->hasConfigFile('kubernetes')
            ->hasCommand(PingCommand::class)
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('roundly-consulting/kubernetes-api-for-laravel');
            });
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
