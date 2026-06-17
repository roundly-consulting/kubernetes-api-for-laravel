<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use Generator;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\PodLogOptions;
use RoundlyConsulting\KubernetesApi\Resources\Types\Container;
use RoundlyConsulting\KubernetesApi\Resources\Types\ContainerStatus;
use RoundlyConsulting\KubernetesApi\Resources\Types\Volume;
use RoundlyConsulting\KubernetesApi\Traits\Resource\CanExec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatusPhase;

class Pod extends Resource
{
    use CanExec;
    use HasSpec;
    use HasStatus;
    use HasStatusPhase;

    protected string $kind = 'Pod';

    protected bool $usesNamespaces = true;

    /**
     * Fetch the pod's logs from the `/log` subresource as a single string.
     */
    public function logs(?PodLogOptions $options = null): string
    {
        $options ??= new PodLogOptions;

        $response = $this->request(
            method: 'GET',
            path: $this->getSubresourcePath('log'),
            query: $options->toQuery(),
            payload: '',
        );

        return $response->body();
    }

    /**
     * Stream the pod's logs line by line. Pair with `follow: true` to tail a
     * running container; the generator yields each log line as it arrives.
     *
     * @return Generator<int, string>
     */
    public function streamLogs(?PodLogOptions $options = null): Generator
    {
        $options ??= new PodLogOptions(follow: true);

        $response = $this->request(
            method: 'GET',
            path: $this->getSubresourcePath('log'),
            query: $options->toQuery(),
            payload: '',
            stream: true,
        );

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(8192);

            while (($newline = strpos($buffer, "\n")) !== false) {
                yield substr($buffer, 0, $newline);
                $buffer = substr($buffer, $newline + 1);
            }
        }

        if ($buffer !== '') {
            yield $buffer;
        }
    }

    /** @param array<int, Container> $containers */
    public function setContainers(array $containers = []): static
    {
        return $this->setSpec('containers', collect($containers)->toArray());
    }

    /** @param array<int, Container> $containers */
    public function setInitContainers(array $containers = []): static
    {
        return $this->setSpec('initContainers', collect($containers)->toArray());
    }

    /** @return array<int, Container> */
    public function getContainers(): array
    {
        $containers = (array) $this->getSpec('containers', []);

        return collect($containers)->mapInto(Container::class)->values()->all();
    }

    /** @return array<int, Container> */
    public function getInitContainers(): array
    {
        $containers = (array) $this->getSpec('initContainers', []);

        return collect($containers)->mapInto(Container::class)->values()->all();
    }

    public function addImagePullSecret(string $name): static
    {
        return $this->addToSpec('imagePullSecrets', ['name' => $name]);
    }

    /** @param array<int, string> $names */
    public function addImagePullSecrets(array $names): static
    {
        foreach ($names as $name) {
            $this->addImagePullSecret($name);
        }

        return $this;
    }

    /** @return array<int, array<string, string>> */
    public function getImagePullSecrets(): array
    {
        return (array) $this->getSpec('imagePullSecrets', []);
    }

    public function addVolume(Volume $volume): static
    {
        return $this->addToSpec('volumes', $volume->toArray());
    }

    /** @param array<int, Volume> $volumes */
    public function addVolumes(array $volumes): static
    {
        foreach ($volumes as $volume) {
            $this->addVolume($volume);
        }

        return $this;
    }

    /** @param array<int, Volume> $volumes */
    public function setVolumes(array $volumes): static
    {
        $volumes = collect($volumes)
            ->map(fn (Volume $volume): array => $volume->toArray())
            ->all();

        return $this->setSpec('volumes', $volumes);
    }

    /** @return array<int, Volume> */
    public function getVolumes(): array
    {
        $volumes = (array) $this->getSpec('volumes', []);

        return collect($volumes)->mapInto(Volume::class)->values()->all();
    }

    /** @return array<int, ContainerStatus> */
    public function getContainerStatuses(): array
    {
        $containers = (array) $this->getStatus('containerStatuses', []);

        return collect($containers)->mapInto(ContainerStatus::class)->values()->all();
    }

    /** @return array<int, ContainerStatus> */
    public function getInitContainerStatuses(): array
    {
        $containers = (array) $this->getStatus('initContainerStatuses', []);

        return collect($containers)->mapInto(ContainerStatus::class)->values()->all();
    }

    public function getContainerStatus(string $name): ?ContainerStatus
    {
        return collect($this->getContainerStatuses())
            ->firstWhere(fn (ContainerStatus $status): bool => $status->getName() === $name);
    }

    public function getInitContainerStatus(string $name): ?ContainerStatus
    {
        return collect($this->getInitContainerStatuses())
            ->firstWhere(fn (ContainerStatus $status): bool => $status->getName() === $name);
    }

    public function containersReady(): bool
    {
        return collect($this->getContainerStatuses())
            ->every(fn (ContainerStatus $status): bool => $status->isReady());
    }

    public function initContainersReady(): bool
    {
        return collect($this->getInitContainerStatuses())
            ->every(fn (ContainerStatus $status): bool => $status->isReady());
    }

    public function getQos(): string
    {
        return $this->getStatus('qosClass', 'BestEffort');
    }

    public function getStatusMessage(): ?string
    {
        return $this->getStatus('message');
    }

    public function isRunning(): bool
    {
        return $this->statusPhaseIs('Running');
    }

    public function isSuccessful(): bool
    {
        return $this->statusPhaseIs('Succeeded');
    }

    public function hasFailed(): bool
    {
        return $this->statusPhaseIs('Failed');
    }
}
