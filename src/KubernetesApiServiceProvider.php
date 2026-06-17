<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\KubernetesApi\Commands\PingCommand;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Resource;

final class KubernetesApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/kubernetes.php', 'kubernetes');
    }

    public function boot(): void
    {
        $this->registerConfiguredResources();

        if ($this->app->runningInConsole()) {
            $this->commands([
                PingCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/kubernetes.php' => config_path('kubernetes.php'),
            ], 'kubernetes-config');
        }
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
