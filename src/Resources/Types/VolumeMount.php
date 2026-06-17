<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources\Types;

/**
 * @method static setName(string $name)
 * @method static setMountPath(string $path)
 * @method static setSubPath(?string $subPath)
 */
class VolumeMount extends Type
{
    public function mountTo(string $path, ?string $subPath = null): static
    {
        $mount = $this->setMountPath($path);

        if ($subPath !== null) {
            $mount->setSubPath($subPath);
        }

        return $mount;
    }
}
