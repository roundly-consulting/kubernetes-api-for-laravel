<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use Illuminate\Support\Facades\Date;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubernetesPatch;

trait CanRolloutRestart
{
    /**
     * Trigger a rolling restart by patching the pod template's
     * `kubectl.kubernetes.io/restartedAt` annotation with the current time,
     * matching `kubectl rollout restart`.
     *
     * @param  array<string, mixed>  $query
     */
    public function rolloutRestart(array $query = ['pretty' => 1]): static
    {
        $patch = KubernetesPatch::strategicMerge([
            'spec' => [
                'template' => [
                    'metadata' => [
                        'annotations' => [
                            'kubectl.kubernetes.io/restartedAt' => Date::now()->toIso8601String(),
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->request(
            method: 'PATCH',
            path: $this->getResourcePath(),
            query: $this->withDryRun($query),
            payload: $patch->encode(),
            contentType: $patch->type->contentType(),
        );

        return $this
            ->newInstance((array) $response->json())
            ->markAsExisting();
    }
}
