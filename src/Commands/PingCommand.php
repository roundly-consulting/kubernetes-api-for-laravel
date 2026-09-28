<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\KubernetesApi\Exceptions\ClusterConfigurationException;
use RoundlyConsulting\KubernetesApi\Exceptions\ClusterNotFoundException;
use RoundlyConsulting\KubernetesApi\KubernetesManager;
use Throwable;

final class PingCommand extends Command
{
    protected $signature = 'kubernetes:ping {cluster? : The cluster name to ping (the default cluster when omitted)}';

    protected $description = 'Check connectivity to a Kubernetes cluster by calling its /version endpoint';

    public function handle(KubernetesManager $kubernetes): int
    {
        $name = $this->argument('cluster');
        $name = is_string($name) && $name !== '' ? $name : null;

        try {
            $cluster = $kubernetes->cluster($name);
        } catch (ClusterNotFoundException|ClusterConfigurationException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $label = $cluster->name() ?? $cluster->getUrl();

        try {
            // Through the cluster's transport: the same per-cluster rate limiter as
            // resource operations, and `Kubernetes::fake()` answers it in tests.
            $version = $cluster->version();
        } catch (ClusterConfigurationException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->components->error("Could not reach {$label}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info("Connected to {$label} — server ".($version->gitVersion !== '' ? $version->gitVersion : 'unknown'));

        return self::SUCCESS;
    }
}
