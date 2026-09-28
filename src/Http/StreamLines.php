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
                $buffer .= $body->read(8192);
            } catch (RuntimeException $exception) {
                if ($body->getMetadata('timed_out') === true) {
                    break;
                }

                throw new ConnectionException('The stream from the apiserver broke off: '.$exception->getMessage(), 0, $exception);
            }

            while (($newline = strpos($buffer, "\n")) !== false) {
                yield substr($buffer, 0, $newline);
                $buffer = substr($buffer, $newline + 1);
            }
        }

        if ($withTrailing && $buffer !== '') {
            yield $buffer;
        }
    }
}
