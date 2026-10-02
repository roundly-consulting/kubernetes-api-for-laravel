<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use Illuminate\Support\Arr;
use RoundlyConsulting\KubernetesApi\Resources\Pod;

trait HasTemplate
{
    use HasSpec;

    /**
     * Use the pod's `metadata` and `spec` as the pod template. A PodTemplateSpec has
     * no `apiVersion`/`kind`, and a strict apiserver refuses them, so they are left out.
     */
    public function setTemplate(Pod $pod): static
    {
        return $this->setSpec('template', Arr::only($pod->toArray(), ['metadata', 'spec']));
    }

    public function getTemplate(): Pod
    {
        $template = $this->getSpec('template', []);

        return Pod::make($template);
    }
}
