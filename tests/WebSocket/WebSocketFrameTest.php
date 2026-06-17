<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\WebSocket\WebSocketFrame;

it('round-trips a small payload through encode and decode', function () {
    $encoded = WebSocketFrame::encode('hello', WebSocketFrame::OPCODE_BINARY);

    $decoded = WebSocketFrame::decode($encoded);

    expect($decoded)->not->toBeNull()
        ->and($decoded['opcode'])->toBe(WebSocketFrame::OPCODE_BINARY)
        ->and($decoded['payload'])->toBe('hello')
        ->and($decoded['consumed'])->toBe(strlen($encoded));
});

it('encodes and decodes a medium 16-bit length payload', function () {
    $payload = str_repeat('x', 300);

    $decoded = WebSocketFrame::decode(WebSocketFrame::encode($payload));

    expect($decoded['payload'])->toBe($payload);
});

it('encodes and decodes a large 64-bit length payload', function () {
    $payload = str_repeat('y', 70000);

    $decoded = WebSocketFrame::decode(WebSocketFrame::encode($payload));

    expect($decoded['payload'])->toBe($payload);
});

it('returns null when the buffer holds an incomplete frame', function () {
    $encoded = WebSocketFrame::encode(str_repeat('z', 300));

    expect(WebSocketFrame::decode(substr($encoded, 0, 1)))->toBeNull()
        ->and(WebSocketFrame::decode(substr($encoded, 0, 3)))->toBeNull()
        ->and(WebSocketFrame::decode(substr($encoded, 0, 8)))->toBeNull();
});

it('decodes an unmasked server frame', function () {
    // Server frames are unmasked: FIN+binary, length 2, payload "ok".
    $frame = chr(0x82).chr(0x02).'ok';

    $decoded = WebSocketFrame::decode($frame);

    expect($decoded['opcode'])->toBe(WebSocketFrame::OPCODE_BINARY)
        ->and($decoded['payload'])->toBe('ok');
});

it('decodes a close frame', function () {
    $decoded = WebSocketFrame::decode(WebSocketFrame::encode('', WebSocketFrame::OPCODE_CLOSE));

    expect($decoded['opcode'])->toBe(WebSocketFrame::OPCODE_CLOSE);
});
