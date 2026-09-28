<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi;

use RoundlyConsulting\KubernetesApi\Commands\PingCommand;
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
                /** @var array<string, mixed> $clusters */
                $clusters = config('kubernetes.clusters', []);
                $throttled = config('kubernetes.rate_limits.enabled', true) !== false;

                // Names and counts only — never a URL, token or certificate path.
                return [
                    'Default cluster' => (string) config('kubernetes.default', 'default'),
                    'Configured clusters' => (string) count($clusters),
                    'Rate limiting' => $throttled ? 'ENABLED' : 'OFF',
                    'Adaptive throttling' => $throttled && config('kubernetes.rate_limits.adaptive', true) !== false ? 'ON' : 'OFF',
                    'Registered resources' => (string) count($resources),
                    'Traefik group' => (string) config('kubernetes.traefik.group', 'traefik.io/v1alpha1'),
                ];
            });
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(KubernetesManager::class);
    }
}
