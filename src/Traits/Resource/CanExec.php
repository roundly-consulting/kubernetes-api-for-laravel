<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use RoundlyConsulting\KubernetesApi\DataTransferObjects\ExecResult;

trait CanExec
{
    /**
     * Execute a command inside the pod via the `exec` subresource. This rides
     * a WebSocket connection speaking the `v4.channel.k8s.io` subprotocol
     * (handled by an isolated transport), returning the captured stdout,
     * stderr, and exit code.
     *
     * @param  list<string>  $command
     */
    public function exec(array $command, ?string $container = null, bool $tty = false): ExecResult
    {
        return $this->requireCluster()->execute($this->getExecPath($command, $container, $tty));
    }

    /**
     * Build the exec subresource path with the query string the apiserver
     * expects (one `command` parameter per argument, plus stdout/stderr).
     *
     * @param  list<string>  $command
     */
    public function getExecPath(array $command, ?string $container = null, bool $tty = false): string
    {
        $query = [
            'stdout' => 'true',
            'stderr' => 'true',
            'stdin' => 'false',
            'tty' => $tty ? 'true' : 'false',
        ];

        if ($container !== null) {
            $query['container'] = $container;
        }

        $queryString = http_build_query($query);

        foreach ($command as $argument) {
            $queryString .= '&command='.rawurlencode($argument);
        }

        return $this->getSubresourcePath('exec').'?'.$queryString;
    }
}
