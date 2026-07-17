<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Tests\Fixtures;

use RoundlyConsulting\KubernetesApi\Resources\Pod;

/**
 * A host's own Pod resource, exactly as `config('kubernetes.resources.pods')` invites.
 *
 * This fixture is why the 45 classes in the Resources namespace must never be `final`:
 * this class is the shape a host writes, and `final` on Pod would make it a fatal error
 * at autoload — the fleet's 7×-shipped bug, in a package whose seam is not an Eloquent
 * model and so is not covered by `swappableModelsAreNotFinal`.
 */
class CustomPod extends Pod
{
    public function customMarker(): string
    {
        return 'host-owned-pod';
    }
}
