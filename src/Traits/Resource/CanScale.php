<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use RoundlyConsulting\KubernetesApi\DataTransferObjects\KubernetesPatch;

trait CanScale
{
    /**
     * Set the desired replica count via the `/scale` subresource using a
     * strategic-merge patch, instead of a full read-modify-write `update()`.
     *
     * @param  array<string, mixed>  $query
     */
    public function scale(int $replicas, array $query = ['pretty' => 1]): static
    {
        $patch = KubernetesPatch::merge(['spec' => ['replicas' => $replicas]]);

        $response = $this->request(
            method: 'PATCH',
            path: $this->getSubresourcePath('scale'),
            query: $this->withDryRun($query),
            payload: $patch->encode(),
            contentType: $patch->type->contentType(),
        );

        return $this
            ->newInstance((array) $response->json())
            ->markAsExisting();
    }
}
