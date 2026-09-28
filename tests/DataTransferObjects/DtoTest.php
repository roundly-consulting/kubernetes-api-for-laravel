<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\DataTransferObjects\ExecResult;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubeConfig;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubernetesPatch;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\PodLogOptions;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\Scale;
use RoundlyConsulting\KubernetesApi\Enums\PatchType;

it('builds each patch type with the right content type and encoded body', function () {
    expect(KubernetesPatch::strategicMerge(['a' => 1])->type)->toBe(PatchType::StrategicMerge)
        ->and(KubernetesPatch::merge(['a' => 1])->type)->toBe(PatchType::Merge)
        ->and(KubernetesPatch::json([['op' => 'add']])->type)->toBe(PatchType::Json)
        ->and(KubernetesPatch::apply(['a' => 1])->type)->toBe(PatchType::Apply);

    expect(KubernetesPatch::merge(['a' => 1])->encode())->toBe('{"a":1}')
        ->and(KubernetesPatch::merge(['a' => 1])->type->contentType())->toBe('application/merge-patch+json');
});

it('exposes the content type for every patch strategy', function () {
    expect(PatchType::StrategicMerge->contentType())->toBe('application/strategic-merge-patch+json')
        ->and(PatchType::Json->contentType())->toBe('application/json-patch+json')
        ->and(PatchType::Apply->contentType())->toBe('application/apply-patch+yaml');
});

it('omits unset pod log options and includes the set ones', function () {
    expect((new PodLogOptions)->toQuery())->toBe([]);

    $query = (new PodLogOptions(
        container: 'app', follow: true, tailLines: 5, sinceSeconds: 30,
        timestamps: true, previous: true, limitBytes: 256,
    ))->toQuery();

    expect($query)->toBe([
        'container' => 'app',
        'follow' => 'true',
        'tailLines' => 5,
        'sinceSeconds' => 30,
        'timestamps' => 'true',
        'previous' => 'true',
        'limitBytes' => 256,
    ]);
});

it('reports exec success based on the exit code', function () {
    expect((new ExecResult('out', '', 0))->successful())->toBeTrue()
        ->and((new ExecResult('', 'err', 2))->successful())->toBeFalse();
});

it('reports whether a kubeconfig carries a client certificate', function () {
    expect((new KubeConfig(server: 'https://x', clientCertificatePath: '/c', clientKeyPath: '/k'))->hasClientCertificate())->toBeTrue()
        ->and((new KubeConfig(server: 'https://x', token: 't'))->hasClientCertificate())->toBeFalse();
});

it('maps a Scale payload and tolerates missing fields', function () {
    expect(Scale::fromResponse([]))->toEqual(new Scale(name: '', namespace: null, replicas: 0, currentReplicas: 0))
        ->and(Scale::fromResponse(['metadata' => ['name' => 'web'], 'spec' => ['replicas' => '3'], 'status' => ['replicas' => 2]]))
        ->toEqual(new Scale(name: 'web', namespace: null, replicas: 3, currentReplicas: 2));
});
