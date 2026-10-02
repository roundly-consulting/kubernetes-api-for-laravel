<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Http;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * @internal Splits a streamed response body (a watch, a log follow) into lines as they
 *           arrive. When the idle read timeout (`kubernetes.client.stream_timeout`)
 *           passes, the stream ends cleanly — just as when the apiserver closes a
 *           watch — while a connection that breaks off throws Laravel's
 *           {@see ConnectionException}.
 */
final class StreamLines
{
    /**
     * @param  bool  $withTrailing  also yield a final line that has no newline yet
     * @return Generator<int, string>
     *
     * @throws ConnectionException
     */
    public static function of(StreamInterface $body, bool $withTrailing = true): Generator
    {
        $buffer = '';

        while (! $body->eof()) {
            try {
                $chunk = self::next($body);
            } catch (RuntimeException $exception) {
                if ($body->getMetadata('timed_out') === true) {
                    break;
                }

                throw new ConnectionException('The stream from the apiserver broke off: '.$exception->getMessage(), 0, $exception);
            }

            // A blocking socket only comes back empty at its end or when the idle timeout passed.
            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;

            while (($newline = strpos($buffer, "\n")) !== false) {
                yield substr($buffer, 0, $newline);
                $buffer = substr($buffer, $newline + 1);
            }
        }

        if ($withTrailing && $buffer !== '') {
            yield $buffer;
        }
    }

    /**
     * Whatever has arrived, as soon as anything has. The apiserver streams with
     * `Transfer-Encoding: chunked`, which PHP's http stream decodes through a filter —
     * and a filtered `read(8192)` waits until 8 KiB have been decoded, holding a quiet
     * watch's events back until more traffic (or the idle timeout) comes. So one byte
     * is read, which returns as soon as a chunk lands, and the rest of that chunk is
     * taken from PHP's read buffer (`unread_bytes`). A seekable body (an in-memory or
     * faked response) is already complete and is read in blocks.
     */
    private static function next(StreamInterface $body): string
    {
        if ($body->isSeekable()) {
            return $body->read(8192);
        }

        $chunk = $body->read(1);
        $pending = $body->getMetadata('unread_bytes');

        return is_int($pending) && $pending > 0 ? $chunk.$body->read($pending) : $chunk;
    }
}
