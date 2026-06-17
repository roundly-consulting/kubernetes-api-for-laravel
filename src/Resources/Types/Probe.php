<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources\Types;

class Probe extends Type
{
    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        parent::__construct(array_merge([
            'failureThreshold' => 1,
            'successThreshold' => 1,
        ], $attributes));
    }

    /** @param array<int, string> $command */
    public function command(array $command): static
    {
        return $this->setAttribute('exec.command', $command);
    }

    /** @return array<int, string>|null */
    public function getCommand(): ?array
    {
        return $this->getAttribute('exec.command');
    }

    /** @param array<string, string> $headers */
    public static function http(string $path = '/healthz', int $port = 8080, array $headers = [], string $scheme = 'HTTP'): static
    {
        $probe = [
            'path' => $path,
            'port' => $port,
            'scheme' => $scheme,
        ];

        if (count($headers) > 0) {
            $probe['httpHeaders'] = collect($headers)
                ->map(fn ($value, $key) => ['name' => $key, 'value' => $value])
                ->values()
                ->all();
        }

        return static::make(['httpGet' => $probe]);
    }

    /** @return array<string, mixed>|null */
    public function getHttp(): ?array
    {
        return $this->getAttribute('httpGet');
    }

    public static function tcp(int $port, ?string $host = null): static
    {
        $probe = static::make();

        if ($host) {
            $probe->setAttribute('tcpSocket.host', $host);
        }

        return $probe->setAttribute('tcpSocket.port', $port);
    }

    /** @return array<string, mixed>|null */
    public function getTcp(): ?array
    {
        return $this->getAttribute('tcpSocket');
    }
}
