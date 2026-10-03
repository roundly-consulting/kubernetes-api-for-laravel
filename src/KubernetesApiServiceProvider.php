<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi;

use RoundlyConsulting\KubernetesApi\Commands\PingCommand;
use RoundlyConsulting\KubernetesApi\Support\ConfigValue;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;

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
                $throttled = Config::boolean('kubernetes.rate_limits.enabled', true);

                // Names and counts only — never a URL, token or certificate path.
                return [
                    'Default cluster' => ConfigValue::isSet($default = config('kubernetes.default')) ? (string) $default : 'default',
                    'Configured clusters' => (string) count($clusters),
                    'Rate limiting' => $throttled ? 'ENABLED' : 'OFF',
                    'Adaptive throttling' => $throttled && Config::boolean('kubernetes.rate_limits.adaptive', true) ? 'ON' : 'OFF',
                    'Registered resources' => (string) count($resources),
                    'Traefik group' => ConfigValue::isSet($group = config('kubernetes.traefik.group')) ? (string) $group : 'traefik.io/v1alpha1',
                ];
            });
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(KubernetesManager::class);
    }
}
