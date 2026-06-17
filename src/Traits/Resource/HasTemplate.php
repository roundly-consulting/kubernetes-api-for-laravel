<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Traits\Resource;

use RoundlyConsulting\KubernetesApi\Resources\Pod;

trait HasTemplate
{
    use HasSpec;

    public function setTemplate(Pod $pod): static
    {
        return $this->setSpec('template', $pod->toArray());
    }

    public function getTemplate(): Pod
    {
        $template = $this->getSpec('template', []);

        return Pod::make($template);
    }
}
