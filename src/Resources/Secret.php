<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Resources;

use Illuminate\Support\Arr;

class Secret extends Resource
{
    protected string $kind = 'Secret';

    protected bool $usesNamespaces = true;

    /** @return array<string, string>|string|null */
    public function getData(?string $key = null, ?string $default = null): null|string|array
    {
        $data = $this->getAttribute('data', []);

        foreach ($data as $dataKey => &$value) {
            $value = base64_decode($value, true);
        }

        if (! $key) {
            return $data;
        }

        return Arr::get($data, $key, $default);
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
        return $this->setAttribute("data.{$name}", base64_encode($value));
    }

    public function removeData(string $name): static
    {
        return $this->removeAttribute("data.{$name}");
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
