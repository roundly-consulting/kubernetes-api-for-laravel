<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubernetesPatch;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\Scale;

trait CanScale
{
    /**
     * Set the desired replica count via the `/scale` subresource using a
     * merge patch, instead of a full read-modify-write `update()`. Returns the
     * apiserver's `Scale` answer (desired and observed replicas), not the workload.
     *
     * @param  array<string, mixed>  $query
     */
    public function scale(int $replicas, array $query = ['pretty' => 1]): Scale
    {
        $patch = KubernetesPatch::merge(['spec' => ['replicas' => $replicas]]);

        $response = $this->request(
            method: 'PATCH',
            path: $this->getSubresourcePath('scale'),
            query: $this->withFieldManager($this->withDryRun($query)),
            payload: $patch->encode(),
            contentType: $patch->type->contentType(),
        );

        /** @var array<string, mixed> $payload */
        $payload = (array) $response->json();

        return Scale::fromResponse($payload);
    }
}
