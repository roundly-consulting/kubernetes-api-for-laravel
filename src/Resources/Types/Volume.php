<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources\Types;

use RoundlyConsulting\KubernetesApi\Exceptions\InvalidResourceException;
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

    /**
     * `emptyDir` is a struct: with no options it is the empty object `{}`, which the
     * apiserver reads as "all defaults" — never the string "{}", which it cannot decode.
     *
     * @param  array<string, mixed>  $options
     */
    public function emptyDirectory(string $name, array $options = []): static
    {
        return $this->setAttribute('name', $name)
            ->setAttribute('emptyDir', $options === [] ? new EmptyObject : $options);
    }

    /**
     * @throws InvalidResourceException when the secret has no name
     */
    public function fromSecret(Secret $secret): static
    {
        $name = $secret->getName() ?? throw InvalidResourceException::missingName();

        return $this->setAttribute('name', "{$name}-secret-volume")
            ->setAttribute('secret', ['secretName' => $name]);
    }

    /**
     * @throws InvalidResourceException when the config map has no name
     */
    public function fromConfigMap(ConfigMap $configMap): static
    {
        $name = $configMap->getName() ?? throw InvalidResourceException::missingName();

        return $this->setAttribute('name', "{$name}-config-volume")
            ->setAttribute('configMap', ['name' => $name]);
    }
}
