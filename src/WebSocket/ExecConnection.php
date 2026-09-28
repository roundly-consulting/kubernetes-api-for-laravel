<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\WebSocket;

use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\ExecResult;
use RoundlyConsulting\KubernetesApi\Exceptions\WebSocketException;

/**
 * A self-contained WebSocket client for the Kubernetes exec subresource. It
 * opens a TLS stream (honouring the cluster's client certificate / CA /
 * token), performs the RFC 6455 upgrade requesting the `v4.channel.k8s.io`
 * subprotocol, then reads the demultiplexed stdout/stderr/error channels to
 * completion. Kept deliberately isolated so the core client stays pure
 * Laravel HTTP.
 *
 * @codeCoverageIgnore Exercised by the live OrbStack integration suite; the
 *   pure framing/parsing logic is unit-tested separately.
 */
final class ExecConnection
{
    private const SUBPROTOCOL = 'v4.channel.k8s.io';

    /** @var resource */
    private $socket;

    public function __construct(
        private readonly Cluster $cluster,
        private readonly int $timeout = 30,
    ) {}

    /**
     * Run the exec request and return the captured result.
     */
    public function send(string $path): ExecResult
    {
        $url = $this->cluster->getUrl();
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            throw new WebSocketException("Invalid cluster URL: {$url}");
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? 443;

        $this->socket = $this->openSocket($host, $port);

        $this->handshake($host, $port, $path);

        return $this->readStream();
    }

    /** @return resource */
    private function openSocket(string $host, int $port)
    {
        $contextOptions = ['ssl' => [
            'verify_peer' => $this->cluster->shouldVerify(),
            'verify_peer_name' => $this->cluster->shouldVerify(),
            'allow_self_signed' => ! $this->cluster->shouldVerify(),
        ]];

        if ($this->cluster->hasPathToCaCertificate()) {
            $contextOptions['ssl']['cafile'] = $this->cluster->getPathToCaCertificate();
        }

        if ($this->cluster->hasPathToCertificate()) {
            $contextOptions['ssl']['local_cert'] = $this->cluster->getPathToCertificate();
        }

        if ($this->cluster->hasPathToPrivateKey()) {
            $contextOptions['ssl']['local_pk'] = $this->cluster->getPathToPrivateKey();
        }

        $context = stream_context_create($contextOptions);

        $socket = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($socket === false) {
            throw new WebSocketException("Unable to connect to {$host}:{$port}: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $this->timeout);

        return $socket;
    }

    private function handshake(string $host, int $port, string $path): void
    {
        $key = base64_encode(random_bytes(16));

        $headers = [
            "GET {$path} HTTP/1.1",
            "Host: {$host}:{$port}",
            'Upgrade: websocket',
            'Connection: Upgrade',
            "Sec-WebSocket-Key: {$key}",
            'Sec-WebSocket-Version: 13',
            'Sec-WebSocket-Protocol: '.self::SUBPROTOCOL,
        ];

        if ($this->cluster->hasToken()) {
            $headers[] = 'Authorization: Bearer '.$this->cluster->getToken();
        }

        fwrite($this->socket, implode("\r\n", $headers)."\r\n\r\n");

        $response = '';

        while (! str_contains($response, "\r\n\r\n")) {
            $chunk = fread($this->socket, 1024);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $response .= $chunk;
        }

        if (! str_contains($response, ' 101 ')) {
            throw new WebSocketException('WebSocket upgrade failed: '.trim($response));
        }
    }

    private function readStream(): ExecResult
    {
        $parser = new ExecStreamParser;
        $buffer = '';

        while (! feof($this->socket)) {
            $chunk = fread($this->socket, 8192);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $buffer .= $chunk;

            while (($frame = WebSocketFrame::decode($buffer)) !== null) {
                $buffer = substr($buffer, $frame['consumed']);

                if ($frame['opcode'] === WebSocketFrame::OPCODE_CLOSE) {
                    fclose($this->socket);

                    return $parser->result();
                }

                if ($frame['opcode'] === WebSocketFrame::OPCODE_BINARY
                    || $frame['opcode'] === WebSocketFrame::OPCODE_CONTINUATION) {
                    $parser->feed($frame['payload']);
                }
            }
        }

        fclose($this->socket);

        return $parser->result();
    }
}
