<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\WebSocket;

/**
 * Minimal RFC 6455 frame codec covering the subset needed for the Kubernetes
 * exec channel: binary/close opcodes, client-side masking, and 7/16/64-bit
 * payload lengths, and the FIN bit so a message fragmented over continuation
 * frames can be reassembled.
 */
final class WebSocketFrame
{
    public const OPCODE_CONTINUATION = 0x0;

    public const OPCODE_TEXT = 0x1;

    public const OPCODE_BINARY = 0x2;

    public const OPCODE_CLOSE = 0x8;

    public const OPCODE_PING = 0x9;

    public const OPCODE_PONG = 0xA;

    /**
     * Encode a client frame. Client frames MUST be masked per RFC 6455.
     */
    public static function encode(string $payload, int $opcode = self::OPCODE_BINARY): string
    {
        $length = strlen($payload);
        $frame = chr(0x80 | $opcode);

        if ($length <= 125) {
            $frame .= chr(0x80 | $length);
        } elseif ($length <= 65535) {
            $frame .= chr(0x80 | 126).pack('n', $length);
        } else {
            $frame .= chr(0x80 | 127).pack('J', $length);
        }

        $mask = random_bytes(4);
        $frame .= $mask;

        for ($i = 0; $i < $length; $i++) {
            $frame .= $payload[$i] ^ $mask[$i % 4];
        }

        return $frame;
    }

    /**
     * Decode the first frame in the buffer. Returns the FIN bit, the opcode, the
     * payload, and the number of bytes consumed, or null when the buffer holds an
     * incomplete frame and more bytes are required.
     *
     * @return array{fin: bool, opcode: int, payload: string, consumed: int}|null
     */
    public static function decode(string $buffer): ?array
    {
        $bufferLength = strlen($buffer);

        if ($bufferLength < 2) {
            return null;
        }

        $fin = (ord($buffer[0]) & 0x80) !== 0;
        $opcode = ord($buffer[0]) & 0x0F;
        $second = ord($buffer[1]);
        $masked = ($second & 0x80) !== 0;
        $length = $second & 0x7F;
        $offset = 2;

        if ($length === 126) {
            if ($bufferLength < 4) {
                return null;
            }
            /** @var array{1: int} $unpacked */
            $unpacked = unpack('n', substr($buffer, 2, 2));
            $length = $unpacked[1];
            $offset = 4;
        } elseif ($length === 127) {
            if ($bufferLength < 10) {
                return null;
            }
            /** @var array{1: int} $unpacked */
            $unpacked = unpack('J', substr($buffer, 2, 8));
            $length = $unpacked[1];
            $offset = 10;
        }

        $maskKey = '';

        if ($masked) {
            if ($bufferLength < $offset + 4) {
                return null;
            }
            $maskKey = substr($buffer, $offset, 4);
            $offset += 4;
        }

        if ($bufferLength < $offset + $length) {
            return null;
        }

        $payload = substr($buffer, $offset, $length);

        if ($masked && $maskKey !== '') {
            $unmasked = '';
            for ($i = 0; $i < $length; $i++) {
                $unmasked .= $payload[$i] ^ $maskKey[$i % 4];
            }
            $payload = $unmasked;
        }

        return [
            'fin' => $fin,
            'opcode' => $opcode,
            'payload' => $payload,
            'consumed' => $offset + $length,
        ];
    }
}
