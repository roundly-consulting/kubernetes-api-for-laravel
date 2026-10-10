<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Exceptions\ClusterConfigurationException;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\KubernetesManager;

/*
 * A cluster's bearer token is a credential. A misconfigured one (an int, an array, an
 * object) must reach no message, no trace string, no frame's arguments and no previous
 * exception: error trackers and trace loggers serialise every one of them. The same holds
 * for a valid token while ANOTHER setting of its cluster fails: the cluster definition the
 * token sits in must not ride along in a frame.
 */

const K8S_SECRET = 'S3CRET-k8s-tok'; // 14 chars: a trace string prints string arguments up to 15

beforeEach(function (): void {
    // Production often runs with arguments stripped from traces; prove the frames are
    // clean with them captured, which is what a development box or a tracker sees.
    $this->ignoreArgs = ini_get('zend.exception_ignore_args');
    ini_set('zend.exception_ignore_args', '0');
});

afterEach(function (): void {
    ini_set('zend.exception_ignore_args', (string) $this->ignoreArgs);
});

function k8sFailure(): Throwable
{
    try {
        Kubernetes::cluster();
    } catch (Throwable $e) {
        return $e;
    }

    throw new RuntimeException('Expected the cluster to be refused.');
}

function k8sExpectNoLeak(Throwable $e, string $secret): void
{
    // Not vacuous: the manager's frames are there, with their arguments captured.
    $managerFrames = array_filter(
        $e->getTrace(),
        static fn (array $frame): bool => ($frame['class'] ?? null) === KubernetesManager::class && ($frame['args'] ?? []) !== [],
    );

    expect($e->getTrace())->not->toBeEmpty()
        ->and($managerFrames)->not->toBeEmpty()
        ->and(k8sLeaks($e, ['token' => $secret]))->toBe([]);
}

/**
 * A wrong-typed token, the text a leak would show and the type the message names. The
 * dataset passes only the case name: a test closure called with the secret would hold
 * it in its own frame, and that frame is part of the trace under test.
 *
 * @return array{0: mixed, 1: string, 2: string}
 */
function k8sWrongTypedToken(string $case): array
{
    return match ($case) {
        'int' => [4815162342424242, '4815162342424242', 'int'],
        'float' => [4815162342.25, '4815162342', 'float'],
        'list' => [[K8S_SECRET], K8S_SECRET, 'array'],
        'map' => [['value' => K8S_SECRET], K8S_SECRET, 'array'],
        'stringable' => [new class
        {
            public function __construct(public string $token = K8S_SECRET) {}

            public function __toString(): string
            {
                return $this->token;
            }
        }, K8S_SECRET, 'class@anonymous'],
    };
}

it('keeps a wrong-typed token out of the message, the trace and every frame', function (string $case): void {
    [$token, $secret, $type] = k8sWrongTypedToken($case);
    config()->set('kubernetes.clusters.default', ['url' => 'https://k8s.example', 'token' => $token]);
    unset($token);

    $e = k8sFailure();

    k8sExpectNoLeak($e, $secret);

    expect($e)->toBeInstanceOf(ClusterConfigurationException::class)
        ->and($e->getMessage())->toBe("Configuration value [kubernetes.clusters.default.token] must be a non-empty string, [{$type}] given.");
})->with(['int', 'float', 'list', 'map', 'stringable']);

it('keeps a valid token out of every frame when another cluster setting fails', function (array $broken, string $message): void {
    config()->set('kubernetes.clusters.default', ['url' => 'https://k8s.example', 'token' => K8S_SECRET, ...$broken]);

    $e = k8sFailure();

    k8sExpectNoLeak($e, K8S_SECRET);

    expect($e->getMessage())->toContain($message);
})->with([
    'url int' => [['url' => 6443], 'Configuration value [kubernetes.clusters.default.url] must be a non-empty string, [6443] given.'],
    'verify typo' => [['verify' => 'ture'], 'Configuration value [kubernetes.clusters.default.verify] must be a boolean'],
    'namespace int' => [['namespace' => 7], 'Configuration value [kubernetes.clusters.default.namespace] must be a non-empty string, [7] given.'],
    'redact_errors typo' => [['redact_errors' => 'maybe'], 'Configuration value [kubernetes.clusters.default.redact_errors] must be a boolean'],
    'unknown source' => [['source' => 'carrier-pigeon'], "unknown source 'carrier-pigeon'"],
    'missing kubeconfig' => [['source' => 'kubeconfig', 'kubeconfig' => '/nonexistent/kubeconfig'], 'Kubeconfig not found at /nonexistent/kubeconfig.'],
]);

it('still accepts a token exactly as given, and a blank one as not set', function (): void {
    config()->set('kubernetes.clusters.default', ['url' => 'https://k8s.example', 'token' => ' '.K8S_SECRET.' ']);
    config()->set('kubernetes.clusters.blank', ['url' => 'https://k8s.example', 'token' => '   ']);

    expect(Kubernetes::cluster()->getToken())->toBe(' '.K8S_SECRET.' ')
        ->and(Kubernetes::cluster('blank')->hasToken())->toBeFalse();
});
