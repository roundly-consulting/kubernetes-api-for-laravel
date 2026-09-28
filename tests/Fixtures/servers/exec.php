<?php

declare(strict_types=1);

/*
 * A stand-in for the apiserver's exec WebSocket (`v4.channel.k8s.io`), plain TCP or —
 * with TLS_CERT set — TLS. Logs each request line and Host header, then plays the
 * scenario named by the first `command` argument:
 *
 *  - fast   101 + stdout + status (exit 7) + close in ONE write, as a quick apiserver or
 *           a TLS-terminating proxy delivers them
 *  - ok     stdout, then a Success status, then close, each in its own write
 *  - drop   stdout, then the connection dies with no status
 *  - quiet  stdout, 2 s of silence, then a Success status
 *  - big    one stdout message fragmented over a binary + a continuation frame
 *  - ping   a ping; stdout says whether the client answered with a pong
 *  - reject a 403 instead of the upgrade
 */

$cert = getenv('TLS_CERT');
$transport = is_string($cert) && $cert !== '' ? 'ssl' : 'tcp';
$context = stream_context_create($transport === 'ssl' ? ['ssl' => ['local_cert' => $cert, 'verify_peer' => false]] : []);

$server = stream_socket_server("{$transport}://127.0.0.1:".getenv('PORT'), $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

if ($server === false) {
    exit(1);
}

$log = static fn (string $line) => file_put_contents((string) getenv('LOG'), $line."\n", FILE_APPEND | LOCK_EX);
$frame = static fn (string $payload, int $opcode = 2, bool $fin = true): string => chr(($fin ? 0x80 : 0) | $opcode)
    .(strlen($payload) <= 125 ? chr(strlen($payload)) : chr(126).pack('n', strlen($payload)))
    .$payload;
$status = static fn (int $code): string => $frame("\x03".json_encode($code === 0
    ? ['metadata' => [], 'status' => 'Success']
    : ['metadata' => [], 'status' => 'Failure', 'reason' => 'NonZeroExitCode', 'details' => ['causes' => [['reason' => 'ExitCode', 'message' => (string) $code]]]]));
$upgrade = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Protocol: v4.channel.k8s.io\r\n\r\n";

while (true) {
    $client = @stream_socket_accept($server, -1);

    if ($client === false) {
        continue;
    }

    $request = '';

    while (! str_contains($request, "\r\n\r\n")) {
        $chunk = fread($client, 1024);

        if ($chunk === false || $chunk === '') {
            break;
        }

        $request .= $chunk;
    }

    if (! str_contains($request, "\r\n\r\n")) {
        fclose($client);

        continue;
    }

    $lines = explode("\r\n", $request);
    $log($lines[0]);

    foreach ($lines as $line) {
        if (str_starts_with($line, 'Host: ')) {
            $log($line);
        }
    }

    preg_match('/[?&]command=([^& ]*)/', $lines[0], $command);

    switch ($command[1] ?? '') {
        case 'fast':
            fwrite($client, $upgrade.$frame("\x01hi\n").$status(7).$frame('', 8));
            break;
        case 'ok':
            fwrite($client, $upgrade);
            usleep(50_000);
            fwrite($client, $frame("\x01hi\n"));
            usleep(50_000);
            fwrite($client, $status(0));
            fwrite($client, $frame('', 8));
            break;
        case 'drop':
            fwrite($client, $upgrade);
            usleep(50_000);
            fwrite($client, $frame("\x01partial output\n"));
            usleep(50_000);
            break;
        case 'quiet':
            fwrite($client, $upgrade.$frame("\x01a"));
            sleep(2);
            @fwrite($client, $status(0).$frame('', 8));
            break;
        case 'big':
            fwrite($client, $upgrade.$frame("\x01".str_repeat('x', 200), 2, false).$frame(str_repeat('y', 200), 0).$status(0).$frame('', 8));
            break;
        case 'ping':
            fwrite($client, $upgrade.$frame('beat', 9));
            stream_set_timeout($client, 2);
            $reply = (string) fread($client, 64);
            $pong = strlen($reply) >= 2 && (ord($reply[0]) & 0x0F) === 0xA && (ord($reply[1]) & 0x80) === 0x80;
            fwrite($client, $frame("\x01".($pong ? 'pong-ok' : 'no-pong')).$status(0).$frame('', 8));
            break;
        case 'reject':
            fwrite($client, "HTTP/1.1 403 Forbidden\r\nContent-Type: application/json\r\n\r\n{\"message\":\"pods \\\"api\\\" is forbidden\"}");
            break;
    }

    usleep(50_000);
    @fclose($client);
}
