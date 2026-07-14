<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi;

use RoundlyConsulting\KubernetesApi\Commands\PingCommand;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

final class KubernetesApiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('kubernetes')
            ->hasConfigFile()
            ->hasCommands([
                PingCommand::class,
            ])
            ->contributesToAbout(static function (): array {
                /** @var array<string, mixed> $resources */
                $resources = config('kubernetes.resources', []);
                $throttled = config('kubernetes.rate_limits.enabled', true) !== false;

                return [
                    'Rate limiting' => $throttled ? 'ENABLED' : 'OFF',
                    'Adaptive throttling' => $throttled && config('kubernetes.rate_limits.adaptive', true) !== false ? 'ON' : 'OFF',
                    'Registered resources' => (string) count($resources),
                    'Traefik group' => (string) config('kubernetes.traefik.group', 'traefik.io/v1alpha1'),
                ];
            });
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerConfiguredResources();
    }

    private function registerConfiguredResources(): void
    {
        /** @var array<string, class-string<resource>> $resources */
        $resources = config('kubernetes.resources', []);

        foreach ($resources as $name => $resourceClassName) {
            Kubernetes::registerResource($name, $resourceClassName);
        }
    }
}
