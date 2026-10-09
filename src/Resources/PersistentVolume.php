<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAccessModes;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasMountOptions;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSelectors;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatusPhase;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStorageClass;

class PersistentVolume extends Resource
{
    use HasAccessModes;
    use HasMountOptions;
    use HasSelectors;
    use HasSpec;
    use HasStatus;
    use HasStatusPhase;
    use HasStorageClass;

    protected string $kind = 'PersistentVolume';

    /**
     * The `PersistentVolumeSource` fields. The apiserver embeds them inline in `spec`
     * (`spec.nfs`, `spec.csi`, …) — there is no `spec.source` — and a volume has
     * exactly one.
     */
    public const array VOLUME_SOURCES = [
        'awsElasticBlockStore', 'azureDisk', 'azureFile', 'cephfs', 'cinder', 'csi', 'fc',
        'flexVolume', 'flocker', 'gcePersistentDisk', 'glusterfs', 'hostPath', 'iscsi',
        'local', 'nfs', 'photonPersistentDisk', 'portworxVolume', 'quobyte', 'rbd',
        'scaleIO', 'storageos', 'vsphereVolume',
    ];

    /**
     * Set the volume source (`setSource('nfs', ['server' => …, 'path' => …])`),
     * replacing any other source already set.
     */
    public function setSource(string $name, mixed $parameters): static
    {
        foreach (self::VOLUME_SOURCES as $source) {
            if ($source !== $name) {
                $this->removeSpec($source);
            }
        }

        return $this->setSpec($name, $parameters);
    }

    /**
     * One source's parameters, or — without a name — the source set on the volume,
     * keyed by its type (`['csi' => [...]]`); null when there is none.
     */
    public function getSource(?string $name = null): mixed
    {
        if ($name !== null) {
            return $this->getSpec($name);
        }

        $sources = array_intersect_key((array) $this->getAttribute('spec', []), array_flip(self::VOLUME_SOURCES));

        return $sources === [] ? null : $sources;
    }

    public function setCapacity(int $size, string $measure = 'Gi'): static
    {
        return $this->setSpec('capacity.storage', $size.$measure);
    }

    public function getCapacity(): ?string
    {
        return $this->getSpec('capacity.storage');
    }

    public function isAvailable(): bool
    {
        return $this->statusPhaseIs('Available');
    }

    public function isBound(): bool
    {
        return $this->statusPhaseIs('Bound');
    }
}
