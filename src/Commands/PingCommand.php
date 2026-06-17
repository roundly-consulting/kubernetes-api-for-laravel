<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use Throwable;

final class PingCommand extends Command
{
    protected $signature = 'kubernetes:ping {cluster? : The registered cluster name to ping}';

    protected $description = 'Check connectivity to a Kubernetes cluster by calling its /version endpoint';

    public function handle(): int
    {
        $name = $this->argument('cluster');

        try {
            $cluster = is_string($name) && $name !== ''
                ? Kubernetes::cluster($name)
                : Kubernetes::getFacadeRoot();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($cluster->getUrl() === '') {
            $this->components->error('No cluster URL configured. Register a cluster or pass a cluster name.');

            return self::FAILURE;
        }

        try {
            $request = Http::baseUrl($cluster->getUrl())->throw();

            if ($cluster->shouldVerify()) {
                $request->withOptions([
                    'verify' => $cluster->hasPathToCaCertificate() ? $cluster->getPathToCaCertificate() : true,
                ]);
            } else {
                $request->withoutVerifying();
            }

            if ($cluster->hasToken()) {
                $request->withToken((string) $cluster->getToken());
            }

            if ($cluster->hasPathToCertificate()) {
                $request->withOptions(['cert' => $cluster->getPathToCertificate()]);
            }

            if ($cluster->hasPathToPrivateKey()) {
                $request->withOptions(['ssl_key' => $cluster->getPathToPrivateKey()]);
            }

            $version = $request->get('/version')->json('gitVersion');
        } catch (Throwable $e) {
            $this->components->error("Could not reach {$cluster->getUrl()}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $label = is_string($name) && $name !== '' ? $name : $cluster->getUrl();

        $this->components->info("Connected to {$label} — server ".(is_string($version) ? $version : 'unknown'));

        return self::SUCCESS;
    }
}
