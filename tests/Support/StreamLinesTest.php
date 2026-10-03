<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use RoundlyConsulting\KubernetesApi\Exceptions\ClusterConfigurationException;
use RoundlyConsulting\KubernetesApi\Http\HttpTransport;
use RoundlyConsulting\KubernetesApi\Http\StreamLines;

/**
 * A body that hands out the given chunks, then fails the next read — as a socket does
 * when its read timeout passes (`timed_out`) or the connection drops.
 *
 * @param  list<string>  $chunks
 */
function failingBody(array $chunks, bool $timedOut): FnStream
{
    return FnStream::decorate(Utils::streamFor(''), [
        'eof' => fn (): bool => false,
        'read' => function () use (&$chunks): string {
            if ($chunks === []) {
                throw new RuntimeException('Unable to read from stream');
            }

            return array_shift($chunks);
        },
        'getMetadata' => fn ($key = null): mixed => $key === 'timed_out' ? $timedOut : null,
    ]);
}

it('splits a body into lines, with or without the trailing partial line', function (): void {
    expect(iterator_to_array(StreamLines::of(Utils::streamFor("a\nb\nc")), false))->toBe(['a', 'b', 'c'])
        ->and(iterator_to_array(StreamLines::of(Utils::streamFor("a\nb\nc"), withTrailing: false), false))->toBe(['a', 'b']);
});

it('ends cleanly when the idle read timeout passes', function (): void {
    expect(iterator_to_array(StreamLines::of(failingBody(["one\ntw"], timedOut: true)), false))->toBe(['one', 'tw']);
});

it('throws a connection exception when the stream breaks off', function (): void {
    iterator_to_array(StreamLines::of(failingBody(["one\n"], timedOut: false)));
})->throws(ConnectionException::class, 'The stream from the apiserver broke off: Unable to read from stream');

it('reads the stream timeout from config, env strings included', function (mixed $value, int $expected): void {
    config()->set('kubernetes.client.stream_timeout', $value);

    expect(HttpTransport::streamTimeout())->toBe($expected);
})->with([
    'unset' => [null, 0],
    'zero' => [0, 0],
    'int' => [300, 300],
    'env string' => ['45', 45],
]);

it('refuses a malformed stream timeout', function (mixed $value): void {
    config()->set('kubernetes.client.stream_timeout', $value);

    HttpTransport::streamTimeout();
})->with(['soon', -1, 86_401])->throws(ClusterConfigurationException::class, 'kubernetes.client.stream_timeout');

it('refuses an empty env stream timeout instead of waiting forever (strict config)', function (): void {
    config()->set('kubernetes.client.stream_timeout', '');

    expect(fn () => HttpTransport::streamTimeout())->toThrow(
        ClusterConfigurationException::class,
        "Configuration value [kubernetes.client.stream_timeout] must be an integer, [''] given.",
    );
});
