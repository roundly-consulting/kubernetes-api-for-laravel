<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\PodLogOptions;
use RoundlyConsulting\KubernetesApi\Resources\Pod;

beforeEach(function () {
    $this->cluster = Cluster::make()->url('https://localhost')->withToken('secret')->withManagerName('Pest Tests');
    $this->pod = Pod::make()->setNamespace('production')->setName('api')->setCluster($this->cluster);
});

it('builds the exec subresource path with one command param per argument', function () {
    $path = $this->pod->getExecPath(['sh', '-c', 'echo hi'], container: 'app');

    expect($path)
        ->toContain('/api/v1/namespaces/production/pods/api/exec?')
        ->toContain('stdout=true')
        ->toContain('stderr=true')
        ->toContain('stdin=false')
        ->toContain('container=app')
        ->toContain('command=sh')
        ->toContain('command=-c')
        ->toContain('command=echo%20hi');
});

it('marks tty in the exec path when requested', function () {
    $path = $this->pod->getExecPath(['bash'], tty: true);

    expect($path)->toContain('tty=true');
});

it('streams logs line by line', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response("first\nsecond\nthird")]);

    $lines = iterator_to_array($this->pod->streamLogs(new PodLogOptions(follow: true)));

    expect($lines)->toBe(['first', 'second', 'third']);
});
