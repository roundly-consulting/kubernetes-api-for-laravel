<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

class Secret extends Resource
{
    protected string $kind = 'Secret';

    protected bool $usesNamespaces = true;

    /**
     * The decoded data, or one decoded value. Data keys are file names (`tls.crt`,
     * `.dockerconfigjson`), so they are always handled as flat keys — never as dotted
     * attribute paths. A value that is not valid base64 is returned as stored.
     *
     * @return array<string, string>|string|null
     */
    public function getData(?string $key = null, ?string $default = null): null|string|array
    {
        $data = [];

        foreach ((array) $this->getAttribute('data', []) as $dataKey => $value) {
            $value = is_scalar($value) ? (string) $value : '';
            $decoded = base64_decode($value, true);

            $data[(string) $dataKey] = $decoded === false ? $value : $decoded;
        }

        if (! $key) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    /** @param array<string, string> $data */
    public function setData(array $data): static
    {
        foreach ($data as $key => &$value) {
            $value = base64_encode($value);
        }

        return $this->setAttribute('data', $data);
    }

    public function addData(string $name, string $value): static
    {
        $data = (array) $this->getAttribute('data', []);
        $data[$name] = base64_encode($value);

        return $this->setAttribute('data', $data);
    }

    public function removeData(string $name): static
    {
        $data = (array) $this->getAttribute('data', []);
        unset($data[$name]);

        return $this->setAttribute('data', $data);
    }

    public function setType(string $type): static
    {
        return $this->setAttribute('type', $type);
    }

    public function getType(): ?string
    {
        $type = $this->getAttribute('type');

        return is_string($type) ? $type : null;
    }

    /**
     * Build a `kubernetes.io/tls` Secret from a PEM-encoded certificate and
     * private key. The PEM blocks are base64-encoded into `data['tls.crt']` and
     * `data['tls.key']` as the apiserver expects.
     */
    public function asTlsCertificate(string $certificate, #[\SensitiveParameter] string $privateKey): static
    {
        // `tls.crt` / `tls.key` are literal Secret data keys (with a dot), so
        // they must not be treated as a nested `data.tls.crt` path.
        $this->setType('kubernetes.io/tls');

        $data = (array) $this->getAttribute('data', []);
        $data['tls.crt'] = base64_encode($certificate);
        $data['tls.key'] = base64_encode($privateKey);

        return $this->setAttribute('data', $data);
    }
}
