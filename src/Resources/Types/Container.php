<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources\Types;

class Container extends Type
{
    public function setImage(string $image, string $tag = 'latest'): static
    {
        return $this->setAttribute('image', $image.':'.$tag);
    }

    public function addPort(int $containerPort, string $protocol = 'TCP', ?string $name = null): static
    {
        $port = [
            'protocol' => $protocol,
            'containerPort' => $containerPort,
        ];

        if ($name) {
            $port['name'] = $name;
        }

        return $this->addToAttribute('ports', $port);
    }

    public function addVolumeMount(VolumeMount $volume): static
    {
        return $this->addToAttribute('volumeMounts', $volume->toArray());
    }

    /** @param array<int, VolumeMount> $volumes */
    public function addVolumeMounts(array $volumes): static
    {
        foreach ($volumes as $volume) {
            $this->addVolumeMount($volume);
        }

        return $this;
    }

    /** @param array<int, VolumeMount> $volumes */
    public function setVolumeMounts(array $volumes): static
    {
        return $this->setAttribute('volumeMounts', collect($volumes)->toArray());
    }

    /** @return array<int, VolumeMount> */
    public function getVolumeMounts(): array
    {
        return collect((array) $this->getAttribute('volumeMounts', []))
            ->mapInto(VolumeMount::class)
            ->values()
            ->all();
    }

    public function addToEnvironmentFromSecret(string $name, string $secretName, ?string $key = null): static
    {
        return $this->addToEnvironment([
            'name' => $name,
            'valueFrom' => [
                'secretKeyRef' => [
                    'name' => $secretName,
                    'key' => $key ?? $secretName,
                ],
            ],
        ]);
    }

    /** @param array<string, array<int, string>> $envsWithSecretRef */
    public function addToEnvironmentFromSecrets(array $envsWithSecretRef): static
    {
        foreach ($envsWithSecretRef as $name => $ref) {
            $this->addToEnvironmentFromSecret($name, ...$ref);
        }

        return $this;
    }

    public function addToEnvironmentFromConfigMap(string $name, string $configMapName, string $key): static
    {
        return $this->addToEnvironment([
            'name' => $name,
            'valueFrom' => [
                'configMapKeyRef' => [
                    'name' => $configMapName,
                    'key' => $key,
                ],
            ],
        ]);
    }

    /** @param array<string, array<int, string>> $envsWithConfigMapRef */
    public function addToEnvironmentFromConfigMaps(array $envsWithConfigMapRef): static
    {
        foreach ($envsWithConfigMapRef as $name => $refs) {
            $this->addToEnvironmentFromConfigMap($name, ...$refs);
        }

        return $this;
    }

    public function addToEnvironmentFromFieldReference(string $name, string $fieldPath): static
    {
        return $this->addToEnvironment([
            'name' => $name,
            'valueFrom' => [
                'fieldRef' => [
                    'fieldPath' => $fieldPath,
                ],
            ],
        ]);
    }

    /** @param array<string, string> $envsWithFieldRefs */
    public function addToEnvironmentFromFieldReferences(array $envsWithFieldRefs): static
    {
        foreach ($envsWithFieldRefs as $name => $path) {
            $this->addToEnvironmentFromFieldReference($name, $path);
        }

        return $this;
    }

    /** @param string|array<string, mixed> $name */
    public function addToEnvironment(string|array $name, ?string $value = null): static
    {
        if (is_array($name)) {
            return $this->addToAttribute('env', $name);
        }

        return $this->addToAttribute('env', ['name' => $name, 'value' => $value]);
    }

    /** @param array<int|string, mixed> $values */
    public function addMultipleToEnvironment(array $values): static
    {
        foreach ($values as $name => $value) {
            if (is_array($value)) {
                $this->addToEnvironment($value);
            } else {
                $this->addToEnvironment((string) $name, $value === null ? null : (string) $value);
            }
        }

        return $this;
    }

    /** @param array<int|string, mixed> $values */
    public function setEnvironment(array $values): static
    {
        $this->removeAttribute('env');

        $this->addMultipleToEnvironment($values);

        return $this;
    }

    public function setMinimumMemory(int $size, string $measure = 'Gi'): static
    {
        return $this->setAttribute('resources.requests.memory', $size.$measure);
    }

    public function getMinimumMemory(): ?string
    {
        return $this->getAttribute('resources.requests.memory');
    }

    public function setMinimumCpu(string $size): static
    {
        return $this->setAttribute('resources.requests.cpu', $size);
    }

    public function getMinimumCpu(): ?string
    {
        return $this->getAttribute('resources.requests.cpu');
    }

    public function setMaximumMemory(int $size, string $measure = 'Gi'): static
    {
        return $this->setAttribute('resources.limits.memory', $size.$measure);
    }

    public function getMaximumMemory(): ?string
    {
        return $this->getAttribute('resources.limits.memory');
    }

    public function setMaximumCpu(string $size): static
    {
        return $this->setAttribute('resources.limits.cpu', $size);
    }

    public function getMaximumCpu(): ?string
    {
        return $this->getAttribute('resources.limits.cpu');
    }

    public function setReadinessProbe(Probe $probe): static
    {
        return $this->setAttribute('readinessProbe', $probe->toArray());
    }

    public function getReadinessProbe(): Probe
    {
        return Probe::make($this->getAttribute('readinessProbe', []));
    }

    public function setLivenessProbe(Probe $probe): static
    {
        return $this->setAttribute('livenessProbe', $probe->toArray());
    }

    public function getLivenessProbe(): Probe
    {
        return Probe::make($this->getAttribute('livenessProbe', []));
    }

    public function setStartupProbe(Probe $probe): static
    {
        return $this->setAttribute('startupProbe', $probe->toArray());
    }

    public function getStartupProbe(): Probe
    {
        return Probe::make($this->getAttribute('startupProbe', []));
    }

    public function isReady(): bool
    {
        return $this->getAttribute('ready', false);
    }
}
