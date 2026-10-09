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
     * A volume backed by the secret, named `$volumeName` or `<secret>-secret-volume`.
     *
     * @throws InvalidResourceException when the secret has no name
     */
    public function fromSecret(Secret $secret, ?string $volumeName = null): static
    {
        $name = $secret->getName() ?? throw InvalidResourceException::missingName();

        return $this->setAttribute('name', $volumeName ?? self::derivedName($name, '-secret-volume'))
            ->setAttribute('secret', ['secretName' => $name]);
    }

    /**
     * A volume backed by the config map, named `$volumeName` or `<map>-config-volume`.
     *
     * @throws InvalidResourceException when the config map has no name
     */
    public function fromConfigMap(ConfigMap $configMap, ?string $volumeName = null): static
    {
        $name = $configMap->getName() ?? throw InvalidResourceException::missingName();

        return $this->setAttribute('name', $volumeName ?? self::derivedName($name, '-config-volume'))
            ->setAttribute('configMap', ['name' => $name]);
    }

    /**
     * A pod volume name must be a DNS-1123 label (no dots, at most 63 characters),
     * while a secret or config map name is a DNS subdomain (dots, up to 253). Dots
     * become hyphens and a name too long is cut — two long names that share their
     * start would then collide, so pass an explicit volume name for those.
     */
    private static function derivedName(string $name, string $suffix): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9-]+/', '-', strtolower($name)), '-');

        return rtrim(substr($base, 0, 63 - strlen($suffix)), '-').$suffix;
    }
}
