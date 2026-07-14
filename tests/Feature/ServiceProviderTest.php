<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\KubernetesApiServiceProvider;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;

it('merges the package config', function (): void {
    expect(config('kubernetes.traefik.group'))->toBe('traefik.io/v1alpha1')
        ->and(config('kubernetes.client.options.timeout'))->toBe(5);
});

it('registers the package commands', function (): void {
    expect(Artisan::all())->toHaveKey('kubernetes:ping');
});

it('registers the configured resources on the cluster client', function (): void {
    expect(Kubernetes::make()->deployments())->toBeInstanceOf(Deployment::class);
});

it('publishes the config under the kubernetes-config tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        KubernetesApiServiceProvider::class,
        'kubernetes-config',
    );

    expect($paths)->toHaveCount(1)
        ->and(array_values($paths)[0])->toEndWith('config/kubernetes.php');
});

it('contributes a section to the about command', function (): void {
    $this->artisan('about', ['--only' => 'kubernetes'])
        ->expectsOutputToContain('ENABLED')
        ->expectsOutputToContain('traefik.io/v1alpha1')
        ->assertSuccessful();
});

it('reports disabled rate limiting to the about command', function (): void {
    config()->set('kubernetes.rate_limits.enabled', false);

    $this->artisan('about', ['--only' => 'kubernetes'])
        ->expectsOutputToContain('OFF')
        ->assertSuccessful();
});
