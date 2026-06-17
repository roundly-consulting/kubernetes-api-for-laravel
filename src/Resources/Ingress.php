<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use RoundlyConsulting\KubernetesApi\Traits\Resource\HasSpec;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasStatus;

class Ingress extends Resource
{
    use HasSpec;
    use HasStatus;

    protected string $kind = 'Ingress';

    protected string $version = 'networking.k8s.io/v1';

    protected bool $usesNamespaces = true;

    public function setIngressClassName(string $className): static
    {
        return $this->setSpec('ingressClassName', $className);
    }

    public function getIngressClassName(): ?string
    {
        return $this->getSpec('ingressClassName');
    }

    public function addRule(string $host, string $path, string $serviceName, int $servicePort, string $pathType = 'Prefix'): static
    {
        return $this->addToSpec('rules', [
            'host' => $host,
            'http' => [
                'paths' => [
                    [
                        'path' => $path,
                        'pathType' => $pathType,
                        'backend' => [
                            'service' => [
                                'name' => $serviceName,
                                'port' => ['number' => $servicePort],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function getRules(): array
    {
        return (array) $this->getSpec('rules', []);
    }

    /** @param array<int, string> $hosts */
    public function addTls(array $hosts, string $secretName): static
    {
        return $this->addToSpec('tls', [
            'hosts' => $hosts,
            'secretName' => $secretName,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function getTls(): array
    {
        return (array) $this->getSpec('tls', []);
    }

    /** @return array<int, array<string, mixed>> */
    public function getLoadBalancerIngress(): array
    {
        return (array) $this->getStatus('loadBalancer.ingress', []);
    }
}
