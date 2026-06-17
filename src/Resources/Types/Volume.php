<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources\Types;

use RoundlyConsulting\KubernetesApi\Resources\ConfigMap;
use RoundlyConsulting\KubernetesApi\Resources\Secret;

/**
 * @method string getName()
 */
class Volume extends Type
{
    public function mountTo(string $mountPath, ?string $subPath = null): VolumeMount
    {
        return VolumeMount::make()
            ->setName($this->getName())
            ->mountTo($mountPath, $subPath);
    }

    /** @param array<string, mixed> $options */
    public function emptyDirectory(string $name, array $options = []): static
    {
        return $this->setAttribute('name', $name)
            ->setAttribute('emptyDir', empty($options) ? '{}' : $options);
    }

    public function fromSecret(Secret $secret): static
    {
        return $this->setAttribute('name', "{$secret->getName()}-secret-volume")
            ->setAttribute('secret', ['secretName' => $secret->getName()]);
    }

    public function fromConfigMap(ConfigMap $configMap): static
    {
        return $this->setAttribute('name', "{$configMap->getName()}-config-volume")
            ->setAttribute('configMap', ['name' => $configMap->getName()]);
    }
}
