<?php

declare(strict_types=1);

/*
 * An apiserver stand-in that answers like the real one does: over TLS (with TLS_CERT set),
 * `Transfer-Encoding: chunked`, one chunk per event or log line, flushed as it happens.
 * PHP's https stream decodes that through a filter, and a plain `read(8192)` there waits
 * for 8 KiB before it returns, so a quiet stream would hold its events back. Logs each
 * request line, then:
 *  - a watch (`watch=1`) sends one event, waits 2 s, sends a second;
 *  - a log follow (`/log`) does the same with two log lines.
 */

$cert = getenv('TLS_CERT');
$transport = is_string($cert) && $cert !== '' ? 'ssl' : 'tcp';
$context = stream_context_create($transport === 'ssl' ? ['ssl' => ['local_cert' => $cert, 'verify_peer' => false]] : []);

$server = stream_socket_server("{$transport}://127.0.0.1:".getenv('PORT'), $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

if ($server === false) {
    exit(1);
}

$log = static fn (string $line) => file_put_contents((string) getenv('LOG'), $line."\n", FILE_APPEND | LOCK_EX);
$chunk = static fn (string $data): string => dechex(strlen($data))."\r\n{$data}\r\n";
$event = static fn (string $type): string => json_encode(['type' => $type, 'object' => ['kind' => 'Pod', 'metadata' => ['name' => 'a']]])."\n";

while (true) {
    $client = @stream_socket_accept($server, -1);

    if ($client === false) {
        continue;
    }

    $request = '';

    while (! str_contains($request, "\r\n\r\n")) {
        $read = fread($client, 1024);

        if ($read === false || $read === '') {
            break;
        }

        $request .= $read;
    }

    // LocalServer's readiness probe connects and hangs up without a request.
    if (! str_contains($request, "\r\n\r\n")) {
        fclose($client);

        continue;
    }

    $line = explode("\r\n", $request)[0];
    $log($line);

    [$first, $second] = str_contains($line, 'watch=1') ? [$event('ADDED'), $event('MODIFIED')] : ["first\n", "second\n"];

    // Headers first, then each chunk on its own, as the apiserver flushes them.
    fwrite($client, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nTransfer-Encoding: chunked\r\nConnection: close\r\n\r\n");
    usleep(100_000);
    fwrite($client, $chunk($first));
    sleep(2);
    @fwrite($client, $chunk($second)."0\r\n\r\n");

    usleep(50_000);
    @fclose($client);
}
