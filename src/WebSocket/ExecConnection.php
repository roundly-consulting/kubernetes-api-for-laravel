<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\WebSocket;

use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\ExecResult;
use RoundlyConsulting\KubernetesApi\Exceptions\WebSocketException;

/**
 * A self-contained WebSocket client for the Kubernetes exec subresource. It
 * dials the cluster URL's scheme, host and port (TLS for `https`, honouring the
 * cluster's client certificate / CA / token), keeps the URL's path prefix (a
 * Rancher-style proxied apiserver), performs the RFC 6455 upgrade requesting the
 * `v4.channel.k8s.io` subprotocol, then reads the demultiplexed
 * stdout/stderr/error channels until the apiserver reports the command's status.
 * Kept deliberately isolated so the core client stays pure Laravel HTTP.
 */
final class ExecConnection
{
    private const SUBPROTOCOL = 'v4.channel.k8s.io';

    /**
     * @param  int  $connectTimeout  seconds allowed to connect and complete the upgrade
     * @param  int  $idleTimeout  seconds of silence after which reading stops (0 = none)
     */
    public function __construct(
        private readonly Cluster $cluster,
        private readonly int $connectTimeout = 30,
        private readonly int $idleTimeout = 0,
    ) {}

    /**
     * Run the exec request and return the captured result. A stream that ends before
     * the apiserver reported a status yields a result without an exit code.
     *
     * @throws WebSocketException
     */
    public function send(string $path): ExecResult
    {
        $url = $this->cluster->getUrl();
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            throw new WebSocketException("Invalid cluster URL: {$url}");
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');

        if (! in_array($scheme, ['https', 'http'], true)) {
            throw new WebSocketException("Unsupported cluster URL scheme for exec: {$scheme}");
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $prefix = rtrim($parts['path'] ?? '', '/');

        $socket = $this->openSocket($scheme === 'https' ? 'ssl' : 'tcp', $host, $port);

        try {
            $leftover = $this->handshake($socket, "{$host}:{$port}", $prefix.$path);

            return $this->readStream($socket, $leftover);
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    /** @return resource */
    private function openSocket(string $transport, string $host, int $port)
    {
        $contextOptions = ['ssl' => [
            'verify_peer' => $this->cluster->shouldVerify(),
            'verify_peer_name' => $this->cluster->shouldVerify(),
            'allow_self_signed' => ! $this->cluster->shouldVerify(),
            // PHP would match the bracketed `[fd00::1]` against the certificate and never
            // find the IPv6 address an in-cluster apiserver's certificate names.
            'peer_name' => trim($host, '[]'),
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

        $socket = @stream_socket_client(
            "{$transport}://{$host}:{$port}",
            $errno,
            $errstr,
            $this->connectTimeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create($contextOptions),
        );

        if ($socket === false) {
            throw new WebSocketException("Unable to connect to {$host}:{$port}: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $this->connectTimeout);

        return $socket;
    }

    /**
     * Upgrade the connection and return whatever arrived after the response headers:
     * a quick apiserver (or a TLS-terminating proxy) sends the first frames — even
     * the whole session — in the same read as the `101`.
     *
     * @param  resource  $socket
     *
     * @throws WebSocketException
     */
    private function handshake($socket, string $hostHeader, string $path): string
    {
        $key = base64_encode(random_bytes(16));

        $headers = [
            "GET {$path} HTTP/1.1",
            "Host: {$hostHeader}",
            'Upgrade: websocket',
            'Connection: Upgrade',
            "Sec-WebSocket-Key: {$key}",
            'Sec-WebSocket-Version: 13',
            'Sec-WebSocket-Protocol: '.self::SUBPROTOCOL,
        ];

        $token = $this->cluster->getToken();

        if ($token !== null) {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        fwrite($socket, implode("\r\n", $headers)."\r\n\r\n");

        $response = '';

        while (($end = strpos($response, "\r\n\r\n")) === false) {
            $chunk = fread($socket, 1024);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $response .= $chunk;
        }

        if ($end === false || preg_match('/^HTTP\/\d(?:\.\d)? 101\b/', $response) !== 1) {
            throw new WebSocketException('WebSocket upgrade failed: '.trim($response));
        }

        return substr($response, $end + 4);
    }

    /**
     * Read frames until the apiserver closes the session, the connection ends or the
     * idle timeout passes. Fragmented messages are reassembled and pings answered.
     *
     * @param  resource  $socket
     */
    private function readStream($socket, string $buffer): ExecResult
    {
        // -1: PHP's "wait indefinitely" — a command may run quietly for a long time.
        stream_set_timeout($socket, $this->idleTimeout > 0 ? $this->idleTimeout : -1);

        $parser = new ExecStreamParser;
        $message = null;

        while (true) {
            while (($frame = WebSocketFrame::decode($buffer)) !== null) {
                $buffer = substr($buffer, $frame['consumed']);

                switch ($frame['opcode']) {
                    case WebSocketFrame::OPCODE_CLOSE:
                        return $parser->result();
                    case WebSocketFrame::OPCODE_PING:
                        fwrite($socket, WebSocketFrame::encode($frame['payload'], WebSocketFrame::OPCODE_PONG));
                        break;
                    case WebSocketFrame::OPCODE_BINARY:
                    case WebSocketFrame::OPCODE_TEXT:
                        $message = $frame['payload'];
                        break;
                    case WebSocketFrame::OPCODE_CONTINUATION:
                        $message = ($message ?? '').$frame['payload'];
                        break;
                }

                if ($message !== null && $frame['fin'] && $frame['opcode'] < WebSocketFrame::OPCODE_CLOSE) {
                    $parser->feed($message);
                    $message = null;
                }
            }

            $chunk = feof($socket) ? false : fread($socket, 8192);

            if ($chunk === false || $chunk === '') {
                return $parser->result();
            }

            $buffer .= $chunk;
        }
    }
}
