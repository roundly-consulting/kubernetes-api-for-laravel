<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Tests\Fixtures;

use RuntimeException;

/**
 * A real server on a loopback port, run in a child PHP process, for the behaviour a
 * mocked HTTP client cannot show: stream read timeouts, what actually goes on the wire,
 * and the exec WebSocket. Every request line the server receives is appended to a log
 * the test reads back.
 */
final class LocalServer
{
    /** @var resource */
    private $process;

    private function __construct(
        public readonly int $port,
        public readonly string $log,
        mixed $process,
    ) {
        $this->process = $process;
    }

    /**
     * `php -S` with a router script (plain HTTP, several workers so a slow response
     * never blocks the next test).
     */
    public static function http(string $router): self
    {
        return self::start(fn (int $port): array => [PHP_BINARY, '-d', 'xdebug.mode=off', '-S', "127.0.0.1:{$port}", $router], ['PHP_CLI_SERVER_WORKERS' => '4']);
    }

    /**
     * A standalone socket server script that reads `PORT` and `LOG` from its env.
     */
    public static function script(string $script): self
    {
        return self::start(fn (int $port): array => [PHP_BINARY, '-d', 'xdebug.mode=off', $script]);
    }

    public function url(string $path = ''): string
    {
        return "http://127.0.0.1:{$this->port}{$path}";
    }

    /** @return list<string> */
    public function requests(): array
    {
        clearstatcache();

        return is_file($this->log)
            ? array_values(array_filter(explode("\n", (string) file_get_contents($this->log))))
            : [];
    }

    public function stop(): void
    {
        $status = proc_get_status($this->process);

        if ($status['running']) {
            // `php -S` forks its workers; kill the whole group through the parent.
            exec('pkill -P '.(int) $status['pid'].' 2>/dev/null');
            proc_terminate($this->process);
        }

        proc_close($this->process);
        @unlink($this->log);
    }

    /**
     * @param  callable(int): list<string>  $command
     * @param  array<string, string>  $env
     */
    private static function start(callable $command, array $env = []): self
    {
        $port = self::freePort();
        $log = (string) tempnam(sys_get_temp_dir(), 'k8s-server-log-');

        $process = proc_open(
            $command($port),
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            [...getenv(), ...$env, 'PORT' => (string) $port, 'LOG' => $log],
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start the local test server.');
        }

        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            $socket = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $error, 0.2);

            if ($socket !== false) {
                fclose($socket);

                return new self($port, $log, $process);
            }

            usleep(50_000);
        }

        proc_terminate($process);

        throw new RuntimeException("The local test server did not come up on port {$port}.");
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

        if ($socket === false) {
            throw new RuntimeException("Unable to find a free port: {$error}");
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
