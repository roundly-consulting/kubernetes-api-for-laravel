<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Support;

use RoundlyConsulting\KubernetesApi\Exceptions\KubeConfigException;
use SensitiveParameter;

/**
 * @internal The temp files inline kubeconfig `*-data` PEMs (client private keys
 *           included) are written to, because curl and PHP's TLS streams only take
 *           certificate paths.
 *
 * Each distinct PEM gets one file per process (mode 0600), reused by every later
 * load — a long-running worker resolving the same cluster again does not pile up
 * key files — and every file is removed when the process exits.
 */
final class TemporaryPemFiles
{
    /** @var array<string, string> suffix + PEM contents => temp file path */
    private static array $files = [];

    private static bool $cleanupRegistered = false;

    /**
     * The temp file holding these PEM contents, written on first use.
     *
     * @throws KubeConfigException
     */
    public static function for(#[SensitiveParameter] string $contents, string $suffix): string
    {
        $key = "{$suffix}\0{$contents}";

        if (isset(self::$files[$key]) && is_file(self::$files[$key])) {
            return self::$files[$key];
        }

        $path = tempnam(sys_get_temp_dir(), "k8s-{$suffix}-");

        if ($path === false) {
            throw new KubeConfigException('Unable to create temp file for kubeconfig PEM.');
        }

        chmod($path, 0600);

        if (file_put_contents($path, $contents) === false) {
            @unlink($path);

            throw new KubeConfigException('Unable to write temp file for kubeconfig PEM.');
        }

        if (! self::$cleanupRegistered) {
            register_shutdown_function(self::removeAll(...));
            self::$cleanupRegistered = true;
        }

        return self::$files[$key] = $path;
    }

    /**
     * Delete every temp PEM file this process wrote. Runs at shutdown.
     */
    public static function removeAll(): void
    {
        foreach (self::$files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        self::$files = [];
    }
}
