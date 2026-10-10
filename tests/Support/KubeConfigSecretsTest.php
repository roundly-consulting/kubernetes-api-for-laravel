<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use RoundlyConsulting\KubernetesApi\Exceptions\KubeConfigException;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Support\KubeConfigLoader;
use RoundlyConsulting\KubernetesApi\Support\TemporaryPemFiles;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/*
 * A kubeconfig user entry holds the credentials: the bearer token, the client key, a
 * password, an exec plugin's env, an auth provider's tokens. A cluster entry may hold a
 * proxy URL with a password in it. When a kubeconfig is malformed, none of them may reach
 * the message, the trace string, any frame's arguments or a previous exception. Every
 * fixture is a temp file: nothing here reads ~/.kube or talks to a cluster.
 */

beforeEach(function (): void {
    // Prove the frames are clean WITH their arguments captured: what a development box
    // or an error tracker sees, whatever production strips.
    $this->ignoreArgs = ini_get('zend.exception_ignore_args');
    ini_set('zend.exception_ignore_args', '0');
    $this->kubeconfigEnv = getenv('KUBECONFIG');
    $this->dir = sys_get_temp_dir().'/k8s-kubeconfig-secrets-'.bin2hex(random_bytes(6));
    mkdir($this->dir, 0700);
});

afterEach(function (): void {
    ini_set('zend.exception_ignore_args', (string) $this->ignoreArgs);
    putenv($this->kubeconfigEnv === false ? 'KUBECONFIG' : 'KUBECONFIG='.$this->kubeconfigEnv);
    File::deleteDirectory($this->dir);
});

/**
 * Every secret a fixture carries, by the name a leak report shows. Strings stay at 14
 * characters or fewer where a frame could take them as a plain argument: a trace string
 * prints string arguments only up to 15.
 *
 * @return array<string, string>
 */
function kcSecrets(): array
{
    return [
        'token' => 'kc-T0KEN-s3crt',
        'client-key-data' => base64_encode("-----BEGIN EC PRIVATE KEY-----\nkc-KEY-s3cr3t\n-----END EC PRIVATE KEY-----\n"),
        'decoded client key' => 'kc-KEY-s3cr3t',
        'malformed client key' => 'kc-BADKEY-s3c!',
        'password' => 'kc-PASSW0RD-x',
        'auth-provider token' => 'kc-0IDC-tok3n',
        'exec env secret' => 'kc-EXEC-s3crt',
        'proxy password' => 'kc-PR0XY-pw',
    ];
}

/**
 * A valid kubeconfig whose one user authenticates with a token AND a client certificate,
 * and whose one cluster goes through an authenticated proxy.
 *
 * @return array<string, mixed>
 */
function kcConfig(): array
{
    $secrets = kcSecrets();

    return [
        'apiVersion' => 'v1',
        'current-context' => 'a',
        'clusters' => [['name' => 'c', 'cluster' => [
            'server' => 'https://k8s.example:6443',
            'proxy-url' => "http://ops:{$secrets['proxy password']}@proxy.example:3128",
        ]]],
        'users' => [['name' => 'u', 'user' => [
            'token' => $secrets['token'],
            'client-certificate-data' => base64_encode("-----BEGIN CERTIFICATE-----\nkc-public-cert\n-----END CERTIFICATE-----\n"),
            'client-key-data' => $secrets['client-key-data'],
        ]]],
        'contexts' => [['name' => 'a', 'context' => ['cluster' => 'c', 'user' => 'u']]],
    ];
}

/** @param array<string, mixed> $config */
function kcYaml(array $config): string
{
    return Yaml::dump($config, 10, 2);
}

/**
 * Writes the case's kubeconfig into `$dir` (for the KUBECONFIG case: two files, named by
 * the env) and returns the path and context to load and the message the loader throws.
 * The dataset passes only the case name: a test closure called with a secret would hold
 * it in its own frame, and that frame is part of the trace under test.
 *
 * @return array{0: ?string, 1: ?string, 2: string}
 */
function kcWriteCase(string $case, string $dir): array
{
    $path = "{$dir}/kubeconfig";
    $config = kcConfig();
    $token = kcSecrets()['token'];
    $context = null;

    if (str_starts_with($case, 'yaml')) {
        $yaml = kcYaml($config);
        $yaml = match ($case) {
            'yaml: the token line' => str_replace("token: {$token}", "token: \"{$token}", $yaml),
            'yaml: after the credentials' => $yaml."broken: [\n",
            'yaml: invalid UTF-8' => $yaml."# \xFF\n",
        };
        file_put_contents($path, $yaml);

        // The line is the parser's own: the loader passes it through, nothing more.
        try {
            Yaml::parse($yaml);
            $line = 0;
        } catch (ParseException $e) {
            $line = $e->getParsedLine();
        }

        return [$path, null, "Kubeconfig at {$path} is not valid YAML".($line > 0 ? " (line {$line})" : '').'.'];
    }

    $unsupported = 'which is not supported: use a token, a tokenFile or a client certificate.';

    switch ($case) {
        case 'no current-context':
            unset($config['current-context']);
            $message = 'No context specified and kubeconfig has no current-context.';
            break;
        case 'context not found':
            $context = 'ghost';
            $message = "No 'ghost' entry found under 'contexts' in kubeconfig.";
            break;
        case 'cluster not found':
            data_set($config, 'contexts.0.context.cluster', 'ghost');
            $message = "No 'ghost' entry found under 'clusters' in kubeconfig.";
            break;
        case 'user not found':
        case 'user not found in merged KUBECONFIG files':
            data_set($config, 'contexts.0.context.user', 'ghost');
            $message = "No 'ghost' entry found under 'users' in kubeconfig.";
            break;
        case 'cluster without server':
            unset($config['clusters'][0]['cluster']['server']);
            $message = "Cluster 'c' has no server URL.";
            break;
        case 'exec credential plugin':
            data_set($config, 'users.0.user.exec', [
                'apiVersion' => 'client.authentication.k8s.io/v1',
                'command' => 'aws',
                'env' => [['name' => 'AWS_SECRET_ACCESS_KEY', 'value' => kcSecrets()['exec env secret']]],
            ]);
            $message = "User 'u' authenticates with an exec credential plugin, {$unsupported}";
            break;
        case 'auth provider':
            data_set($config, 'users.0.user.auth-provider', [
                'name' => 'oidc',
                'config' => ['id-token' => kcSecrets()['auth-provider token'], 'idp-issuer-url' => 'https://idp.example'],
            ]);
            $message = "User 'u' authenticates with an auth provider, {$unsupported}";
            break;
        case 'username and password':
            data_set($config, 'users.0.user.username', 'admin');
            data_set($config, 'users.0.user.password', kcSecrets()['password']);
            $message = "User 'u' authenticates with a username and password, {$unsupported}";
            break;
        case 'unreadable insecure-skip-tls-verify':
            data_set($config, 'clusters.0.cluster.insecure-skip-tls-verify', 'sometimes');
            $message = "Cluster 'c' has an invalid insecure-skip-tls-verify value [sometimes]: use true or false.";
            break;
        case 'malformed client-certificate-data':
            data_set($config, 'users.0.user.client-certificate-data', '!!not-base64!!');
            $message = "Invalid base64 in kubeconfig 'client-certificate-data'.";
            break;
        case 'malformed client-key-data':
            data_set($config, 'users.0.user.client-key-data', kcSecrets()['malformed client key']);
            $message = "Invalid base64 in kubeconfig 'client-key-data'.";
            break;
        case 'malformed certificate-authority-data':
            data_set($config, 'clusters.0.cluster.certificate-authority-data', '!!not-base64!!');
            $message = "Invalid base64 in kubeconfig 'certificate-authority-data'.";
            break;
        case 'unreadable tokenFile':
            unset($config['users'][0]['user']['token']);
            data_set($config, 'users.0.user.tokenFile', 'missing-token');
            $message = "User 'u' has a tokenFile that cannot be read: {$dir}/missing-token.";
            break;
        default:
            throw new InvalidArgumentException("Unknown case [{$case}].");
    }

    if ($case === 'user not found in merged KUBECONFIG files') {
        // The first file's context points at a user neither file defines; both files hold
        // users with credentials, and the merged config they form must not ride along.
        $second = kcConfig();
        data_set($second, 'users.0.name', 'v');
        file_put_contents($path, kcYaml($config));
        file_put_contents("{$dir}/second", kcYaml($second));
        putenv('KUBECONFIG='.$path.PATH_SEPARATOR."{$dir}/second");

        return [null, null, $message];
    }

    file_put_contents($path, kcYaml($config));

    return [$path, $context, $message];
}

it('keeps the kubeconfig credentials out of every load failure', function (string $case): void {
    [$path, $context, $message] = kcWriteCase($case, $this->dir);

    try {
        Kubernetes::fromKubeConfig($path, $context);
        $e = null;
    } catch (Throwable $caught) {
        $e = $caught;
    }

    // Not vacuous: the load failed, and the loader's frames carry their arguments.
    $loaderFrames = array_filter(
        $e?->getTrace() ?? [],
        static fn (array $frame): bool => ($frame['class'] ?? null) === KubeConfigLoader::class
            && array_filter($frame['args'] ?? [], static fn (mixed $arg): bool => ! $arg instanceof SensitiveParameterValue) !== [],
    );

    expect(ini_get('zend.exception_ignore_args'))->toBe('0')
        ->and($e)->toBeInstanceOf(Throwable::class)
        ->and($loaderFrames)->not->toBeEmpty()
        ->and(k8sLeaks($e, kcSecrets()))->toBe([])
        ->and($e)->toBeInstanceOf(KubeConfigException::class)
        ->and($e->getMessage())->toBe($message);
})->with([
    'yaml: the token line',
    'yaml: after the credentials',
    'yaml: invalid UTF-8',
    'no current-context',
    'context not found',
    'cluster not found',
    'user not found',
    'user not found in merged KUBECONFIG files',
    'cluster without server',
    'exec credential plugin',
    'auth provider',
    'username and password',
    'unreadable insecure-skip-tls-verify',
    'malformed client-certificate-data',
    'malformed client-key-data',
    'malformed certificate-authority-data',
    'unreadable tokenFile',
]);

it('still loads the same kubeconfig once it is well-formed', function (): void {
    $path = "{$this->dir}/kubeconfig";
    file_put_contents($path, kcYaml(kcConfig()));

    $config = (new KubeConfigLoader)->load($path);

    expect($config->token)->toBe(kcSecrets()['token'])
        ->and(file_get_contents((string) $config->clientKeyPath))->toContain(kcSecrets()['decoded client key'])
        ->and($config->server)->toBe('https://k8s.example:6443');
});

it('marks every kubeconfig fragment and decoded PEM parameter sensitive, even where no failure reaches it', function (): void {
    // Merging, token resolution and the temp-file writer have no malformed-input failure a
    // test can provoke, so the attribute itself is pinned: every array the loader passes
    // around is a slice of the kubeconfig, and the PEM writer takes decoded key material.
    $unmarked = [];
    $arrays = 0;

    foreach ((new ReflectionClass(KubeConfigLoader::class))->getMethods() as $method) {
        foreach ($method->getParameters() as $parameter) {
            if ((string) $parameter->getType() === 'array') {
                $arrays++;

                if ($parameter->getAttributes(SensitiveParameter::class) === []) {
                    $unmarked[] = "{$method->getName()}(\${$parameter->getName()})";
                }
            }
        }
    }

    $pem = new ReflectionParameter([TemporaryPemFiles::class, 'for'], 'contents');

    expect($arrays)->toBe(6)
        ->and($unmarked)->toBe([])
        ->and($pem->getAttributes(SensitiveParameter::class))->toHaveCount(1);
});
