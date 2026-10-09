<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Testing;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\ExecResult;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\VersionInfo;
use RoundlyConsulting\KubernetesApi\Enums\PatchType;
use RoundlyConsulting\KubernetesApi\Http\Transport;

/**
 * @internal The in-memory apiserver behind {@see KubernetesFake}: it stores objects per
 *           cluster, answers list/get/watch/logs/create/update/patch/delete/exec the way
 *           the real apiserver does (404, 409 conflict, dry run, label and field
 *           selectors, `limit`/`continue`), and records every request.
 *
 * Approximations, by design: strategic-merge and server-side-apply patches are applied
 * as JSON merge patches (lists are replaced, not merged by key), `update()` and
 * `patch()` keep the stored `status` for every kind (as the apiserver does for kinds
 * with a status subresource), deletes are immediate, and exec/logs answer for any pod —
 * seeded or not.
 */
final class FakeTransport implements Transport
{
    /** @var array<string, array<string, array<string, mixed>>> partition => key => object */
    private array $objects = [];

    /** @var array<string, array<string, string>> partition => "namespace/pod" => logs */
    private array $logs = [];

    /** @var list<RecordedRequest> */
    private array $recorded = [];

    private VersionInfo $version;

    /** @var ExecResult|Closure(RecordedRequest): ExecResult */
    private ExecResult|Closure $exec;

    private bool $unreachable = false;

    private int $resourceVersion = 0;

    /**
     * @param  Closure(): string  $defaultPartition  the default cluster's name, where ad-hoc clusters live
     */
    public function __construct(private readonly Closure $defaultPartition)
    {
        $this->version = VersionInfo::fromGitVersion('v1.34.0');
        $this->exec = new ExecResult('', '', 0);
    }

    /** @param array<string, mixed> $query */
    public function send(
        Cluster $cluster,
        string $method,
        string $path,
        array $query = [],
        string $body = '',
        ?string $contentType = null,
        bool $stream = false,
    ): Response {
        $api = ApiPath::parse($path);
        $method = strtoupper($method);
        $decoded = $body === '' ? [] : (array) json_decode($body, true);

        $request = new RecordedRequest(
            cluster: $cluster->name(),
            method: $method,
            path: $path,
            verb: $this->verbFor($method, $api, $query),
            apiVersion: $api->apiVersion,
            plural: $api->plural,
            namespace: $api->namespace,
            name: $api->name ?? ($method === 'POST' && is_string($decoded['metadata']['name'] ?? null) ? $decoded['metadata']['name'] : null),
            subresource: $api->subresource,
            query: $query,
            body: $decoded,
            contentType: $contentType,
        );

        $this->recorded[] = $request;

        $this->guardReachable($cluster);

        $partition = $this->partition($cluster);

        return match ($request->verb) {
            RequestVerb::Version => $this->json($this->versionPayload()),
            RequestVerb::List => $this->list($partition, $api, $query),
            RequestVerb::Watch => $this->watch($partition, $api, $query),
            RequestVerb::Get => $this->get($partition, $api),
            RequestVerb::Logs => $this->logs($partition, $api, $query),
            RequestVerb::Create => $this->create($partition, $api, $decoded, $request->isDryRun()),
            RequestVerb::Update => $this->update($partition, $api, $decoded, $request->isDryRun()),
            RequestVerb::Patch => $this->patch($partition, $api, $decoded, $contentType, $query, $request->isDryRun()),
            RequestVerb::Delete => $this->delete($partition, $api, $request->isDryRun()),
            default => $this->status(404, 'NotFound', "the server could not find the requested resource ({$method} {$path})"),
        };
    }

    public function exec(Cluster $cluster, string $path): ExecResult
    {
        $api = ApiPath::parse($path);
        $query = (string) parse_url($path, PHP_URL_QUERY);

        preg_match_all('/(?:^|&)command=([^&]*)/', $query, $commands);
        preg_match('/(?:^|&)container=([^&]*)/', $query, $container);

        $request = new RecordedRequest(
            cluster: $cluster->name(),
            method: 'GET',
            path: (string) parse_url($path, PHP_URL_PATH),
            verb: RequestVerb::Exec,
            apiVersion: $api->apiVersion,
            plural: $api->plural,
            namespace: $api->namespace,
            name: $api->name,
            subresource: $api->subresource,
            command: array_map(rawurldecode(...), $commands[1]),
            container: isset($container[1]) ? rawurldecode($container[1]) : null,
        );

        $this->recorded[] = $request;

        $this->guardReachable($cluster);

        return $this->exec instanceof Closure ? ($this->exec)($request) : $this->exec;
    }

    /**
     * Store an object as though it had been created on the cluster.
     *
     * @param  array<string, mixed>  $object
     */
    public function put(?string $cluster, string $apiVersion, string $plural, ?string $namespace, string $name, array $object): void
    {
        $partition = $cluster ?? ($this->defaultPartition)();

        $this->objects[$partition][$this->key($apiVersion, $plural, $namespace, $name)] = $this->stamp($object);
    }

    public function putLogs(?string $cluster, string $namespace, string $pod, string $logs): void
    {
        $this->logs[$cluster ?? ($this->defaultPartition)()]["{$namespace}/{$pod}"] = $logs;
    }

    public function answerVersion(VersionInfo $version): void
    {
        $this->version = $version;
    }

    /** @param ExecResult|Closure(RecordedRequest): ExecResult $result */
    public function answerExec(ExecResult|Closure $result): void
    {
        $this->exec = $result;
    }

    public function setUnreachable(bool $unreachable): void
    {
        $this->unreachable = $unreachable;
    }

    /** @return list<RecordedRequest> */
    public function recorded(): array
    {
        return $this->recorded;
    }

    /** @param array<string, mixed> $query */
    private function verbFor(string $method, ApiPath $api, array $query): RequestVerb
    {
        if ($api->version) {
            return RequestVerb::Version;
        }

        if (! $api->isResource()) {
            return RequestVerb::Unknown;
        }

        return match ($method) {
            'GET' => match (true) {
                $api->name === null => filter_var($query['watch'] ?? false, FILTER_VALIDATE_BOOL) ? RequestVerb::Watch : RequestVerb::List,
                $api->subresource === 'log' => RequestVerb::Logs,
                default => RequestVerb::Get,
            },
            'POST' => $api->name === null ? RequestVerb::Create : RequestVerb::Unknown,
            'PUT' => $api->name === null ? RequestVerb::Unknown : RequestVerb::Update,
            'PATCH' => $api->name === null ? RequestVerb::Unknown : RequestVerb::Patch,
            'DELETE' => $api->name === null ? RequestVerb::Unknown : RequestVerb::Delete,
            default => RequestVerb::Unknown,
        };
    }

    private function guardReachable(Cluster $cluster): void
    {
        if ($this->unreachable) {
            throw new ConnectionException(sprintf(
                'Kubernetes fake: cluster %s is unreachable.',
                $cluster->name() ?? 'ad-hoc',
            ));
        }
    }

    private function partition(Cluster $cluster): string
    {
        return $cluster->name() ?? ($this->defaultPartition)();
    }

    /** @param array<string, mixed> $query */
    private function list(string $partition, ApiPath $api, array $query): Response
    {
        $items = $this->matching($partition, $api, $query);

        $offset = max(0, (int) ($query['continue'] ?? 0));
        $limit = isset($query['limit']) ? max(1, (int) $query['limit']) : null;
        $page = array_slice($items, $offset, $limit);

        $metadata = ['resourceVersion' => (string) $this->resourceVersion];

        if ($limit !== null && $offset + $limit < count($items)) {
            $metadata['continue'] = (string) ($offset + $limit);
            $metadata['remainingItemCount'] = count($items) - $offset - $limit;
        }

        return $this->json([
            'apiVersion' => $api->apiVersion,
            'kind' => 'List',
            'metadata' => $metadata,
            'items' => $page,
        ]);
    }

    /** @param array<string, mixed> $query */
    private function watch(string $partition, ApiPath $api, array $query): Response
    {
        $lines = array_map(
            static fn (array $object): string => json_encode(['type' => 'ADDED', 'object' => $object], JSON_THROW_ON_ERROR),
            $this->matching($partition, $api, $query),
        );

        return $this->response($lines === [] ? '' : implode("\n", $lines)."\n", 200, ['Content-Type' => 'application/json']);
    }

    private function get(string $partition, ApiPath $api): Response
    {
        $object = $this->find($partition, $api);

        if ($object === null) {
            return $this->notFound($api);
        }

        return $this->json($api->subresource === 'scale' ? $this->scaleOf($object, $api) : $object);
    }

    /** @param array<string, mixed> $query */
    private function logs(string $partition, ApiPath $api, array $query): Response
    {
        $logs = $this->logs[$partition][($api->namespace ?? 'default').'/'.$api->name] ?? '';

        if (isset($query['tailLines']) && is_numeric($query['tailLines'])) {
            $lines = explode("\n", rtrim($logs, "\n"));
            $logs = implode("\n", array_slice($lines, -max(0, (int) $query['tailLines'])));
        }

        return $this->response($logs, 200, ['Content-Type' => 'text/plain']);
    }

    /** @param array<array-key, mixed> $body */
    private function create(string $partition, ApiPath $api, array $body, bool $dryRun): Response
    {
        $name = $body['metadata']['name'] ?? null;

        if (! is_string($name) || $name === '') {
            return $this->status(422, 'Invalid', "{$api->label()} is invalid: metadata.name: Required value: name or generateName is required");
        }

        $key = $this->key((string) $api->apiVersion, (string) $api->plural, $api->namespace, $name);

        if (isset($this->objects[$partition][$key])) {
            return $this->status(409, 'AlreadyExists', "{$api->label()} \"{$name}\" already exists");
        }

        if ($api->namespace !== null) {
            $body['metadata']['namespace'] = $api->namespace;
        }

        /** @var array<string, mixed> $body */
        $object = $this->stamp($body);

        if (! $dryRun) {
            $this->objects[$partition][$key] = $object;
        }

        return $this->json($object, 201);
    }

    /** @param array<array-key, mixed> $body */
    private function update(string $partition, ApiPath $api, array $body, bool $dryRun): Response
    {
        $current = $this->find($partition, $api);

        if ($current === null) {
            return $this->notFound($api);
        }

        $sent = $body['metadata']['resourceVersion'] ?? null;

        if ($sent !== null && $sent !== ($current['metadata']['resourceVersion'] ?? null)) {
            return $this->status(409, 'Conflict', "Operation cannot be fulfilled on {$api->label()} \"{$api->name}\": the object has been modified; please apply your changes to the latest version and try again");
        }

        $body['metadata']['uid'] = $current['metadata']['uid'] ?? null;
        $body['metadata']['creationTimestamp'] = $current['metadata']['creationTimestamp'] ?? null;

        /** @var array<string, mixed> $body */
        return $this->store($partition, $api, $this->keepStatus($body, $current), $dryRun);
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @param  array<string, mixed>  $query
     */
    private function patch(string $partition, ApiPath $api, array $body, ?string $contentType, array $query, bool $dryRun): Response
    {
        // The apiserver validates PatchOptions before it looks the object up.
        $apply = $contentType === PatchType::Apply->contentType();

        if ($apply && in_array($query['fieldManager'] ?? null, [null, ''], true)) {
            return $this->status(422, 'Invalid', 'PatchOptions.meta.k8s.io "" is invalid: fieldManager: Required value: is required for apply patch');
        }

        if (! $apply && array_key_exists('force', $query)) {
            return $this->status(422, 'Invalid', 'PatchOptions.meta.k8s.io "" is invalid: force: Forbidden: may not be specified for non-apply patch');
        }

        $current = $this->find($partition, $api);

        if ($current === null) {
            return $this->notFound($api);
        }

        if ($api->subresource === 'scale') {
            $current['spec']['replicas'] = Arr::get($body, 'spec.replicas', $current['spec']['replicas'] ?? 1);

            /** @var array<string, mixed> $current */
            $stored = $this->store($partition, $api, $current, $dryRun)->json();

            return $this->json($this->scaleOf((array) $stored, $api));
        }

        $patched = $contentType === PatchType::Json->contentType()
            ? $this->jsonPatch($current, $body)
            : $this->mergePatch($current, $body);

        if ($patched === null) {
            return $this->status(422, 'Invalid', "the JSON patch for {$api->label()} \"{$api->name}\" could not be applied");
        }

        return $this->store($partition, $api, $this->keepStatus($patched, $current), $dryRun);
    }

    /**
     * A write to the main resource leaves `status` as stored, the way the apiserver
     * treats every kind with a status subresource: only the controller (through
     * `/status`) changes it.
     *
     * @param  array<string, mixed>  $object
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function keepStatus(array $object, array $current): array
    {
        unset($object['status']);

        if (array_key_exists('status', $current)) {
            $object['status'] = $current['status'];
        }

        return $object;
    }

    private function delete(string $partition, ApiPath $api, bool $dryRun): Response
    {
        $object = $this->find($partition, $api);

        if ($object === null) {
            return $this->notFound($api);
        }

        if (! $dryRun) {
            unset($this->objects[$partition][$this->key((string) $api->apiVersion, (string) $api->plural, $api->namespace, (string) $api->name)]);
        }

        return $this->json($object);
    }

    /** @param array<string, mixed> $object */
    private function store(string $partition, ApiPath $api, array $object, bool $dryRun): Response
    {
        $object['metadata']['name'] = $api->name;
        $object['metadata']['resourceVersion'] = (string) ($this->resourceVersion + 1);

        if ($api->namespace !== null) {
            $object['metadata']['namespace'] = $api->namespace;
        }

        if (! $dryRun) {
            $this->resourceVersion++;
            $this->objects[$partition][$this->key((string) $api->apiVersion, (string) $api->plural, $api->namespace, (string) $api->name)] = $object;
        }

        return $this->json($object);
    }

    /** @return array<string, mixed>|null */
    private function find(string $partition, ApiPath $api): ?array
    {
        return $this->objects[$partition][$this->key((string) $api->apiVersion, (string) $api->plural, $api->namespace, (string) $api->name)] ?? null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function matching(string $partition, ApiPath $api, array $query): array
    {
        $prefix = "{$api->apiVersion}|{$api->plural}|";
        $objects = $this->objects[$partition] ?? [];
        ksort($objects);

        $matches = [];

        foreach ($objects as $key => $object) {
            if (! str_starts_with($key, $prefix)) {
                continue;
            }

            if ($api->namespace !== null && ($object['metadata']['namespace'] ?? null) !== $api->namespace) {
                continue;
            }

            if (! $this->matchesLabels($object, (string) ($query['labelSelector'] ?? ''))) {
                continue;
            }

            if (! $this->matchesFields($object, (string) ($query['fieldSelector'] ?? ''))) {
                continue;
            }

            $matches[] = $object;
        }

        return $matches;
    }

    /** @param array<string, mixed> $object */
    private function matchesLabels(array $object, string $selector): bool
    {
        /** @var array<string, string> $labels */
        $labels = (array) ($object['metadata']['labels'] ?? []);

        foreach ($this->requirements($selector) as $requirement) {
            if (preg_match('/^([^\s!=]+)\s+(in|notin)\s+\((.*)\)$/', $requirement, $set) === 1) {
                $values = array_map(trim(...), explode(',', $set[3]));
                $present = array_key_exists($set[1], $labels) && in_array($labels[$set[1]], $values, true);

                if ($set[2] === 'in' ? ! $present : $present) {
                    return false;
                }

                continue;
            }

            if (! $this->matchesEquality($requirement, static fn (string $key): ?string => $labels[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $object */
    private function matchesFields(array $object, string $selector): bool
    {
        foreach ($this->requirements($selector) as $requirement) {
            $read = static function (string $path) use ($object): ?string {
                $value = Arr::get($object, $path);

                return is_scalar($value) ? (string) $value : null;
            };

            if (! $this->matchesEquality($requirement, $read)) {
                return false;
            }
        }

        return true;
    }

    /**
     * `key=value`, `key==value`, `key!=value`, `key` (exists) and `!key` (missing).
     *
     * @param  Closure(string): ?string  $read
     */
    private function matchesEquality(string $requirement, Closure $read): bool
    {
        if (preg_match('/^([^!=]+)(!=|==|=)(.*)$/', $requirement, $parts) === 1) {
            $actual = $read(trim($parts[1]));

            return $parts[2] === '!=' ? $actual !== trim($parts[3]) : $actual === trim($parts[3]);
        }

        if (str_starts_with($requirement, '!')) {
            return $read(substr($requirement, 1)) === null;
        }

        return $read($requirement) !== null;
    }

    /** @return list<string> */
    private function requirements(string $selector): array
    {
        $parts = preg_split('/,(?![^(]*\))/', $selector) ?: [];

        return array_values(array_filter(array_map(trim(...), $parts), static fn (string $part): bool => $part !== ''));
    }

    /**
     * RFC 7386: objects merge recursively, `null` deletes, everything else replaces.
     *
     * @param  array<array-key, mixed>  $target
     * @param  array<array-key, mixed>  $patch
     * @return array<string, mixed>
     */
    private function mergePatch(array $target, array $patch): array
    {
        foreach ($patch as $key => $value) {
            if ($value === null) {
                unset($target[$key]);
            } elseif (is_array($value) && $value !== [] && ! array_is_list($value)) {
                $existing = $target[$key] ?? [];
                $target[$key] = $this->mergePatch(is_array($existing) && ! array_is_list($existing) ? $existing : [], $value);
            } else {
                $target[$key] = $value;
            }
        }

        /** @var array<string, mixed> $target */
        return $target;
    }

    /**
     * RFC 6902 `add`, `replace`, `remove` and `test`; null when an operation fails.
     *
     * @param  array<string, mixed>  $document
     * @param  array<array-key, mixed>  $operations
     * @return array<string, mixed>|null
     */
    private function jsonPatch(array $document, array $operations): ?array
    {
        foreach ($operations as $operation) {
            if (! is_array($operation) || ! is_string($operation['path'] ?? null)) {
                return null;
            }

            $pointer = array_map(
                static fn (string $segment): string => str_replace(['~1', '~0'], ['/', '~'], $segment),
                explode('/', ltrim($operation['path'], '/')),
            );

            switch ($operation['op'] ?? null) {
                case 'add':
                case 'replace':
                    $document = $this->pointerSet($document, $pointer, $operation['value'] ?? null, $operation['op'] === 'add');
                    break;
                case 'remove':
                    $document = $this->pointerRemove($document, $pointer);
                    break;
                case 'test':
                    if (Arr::get($document, implode('.', $pointer)) !== ($operation['value'] ?? null)) {
                        return null;
                    }
                    break;
                default:
                    return null;
            }
        }

        /** @var array<string, mixed> $document */
        return $document;
    }

    /**
     * @param  array<array-key, mixed>  $document
     * @param  list<string>  $pointer
     * @return array<array-key, mixed>
     */
    private function pointerSet(array $document, array $pointer, mixed $value, bool $insert): array
    {
        $segment = array_shift($pointer);

        if ($pointer === []) {
            if ($segment === '-') {
                $document[] = $value;
            } elseif ($insert && array_is_list($document) && ctype_digit((string) $segment)) {
                array_splice($document, (int) $segment, 0, [$value]);
            } else {
                $document[(string) $segment] = $value;
            }

            return $document;
        }

        $child = $document[(string) $segment] ?? [];
        $document[(string) $segment] = $this->pointerSet(is_array($child) ? $child : [], $pointer, $value, $insert);

        return $document;
    }

    /**
     * @param  array<array-key, mixed>  $document
     * @param  list<string>  $pointer
     * @return array<array-key, mixed>
     */
    private function pointerRemove(array $document, array $pointer): array
    {
        $segment = (string) array_shift($pointer);

        if ($pointer === []) {
            $wasList = array_is_list($document);
            unset($document[$segment]);

            return $wasList ? array_values($document) : $document;
        }

        if (is_array($document[$segment] ?? null)) {
            $document[$segment] = $this->pointerRemove($document[$segment], $pointer);
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function scaleOf(array $object, ApiPath $api): array
    {
        $replicas = $object['spec']['replicas'] ?? 1;

        return [
            'apiVersion' => 'autoscaling/v1',
            'kind' => 'Scale',
            'metadata' => array_filter([
                'name' => $api->name,
                'namespace' => $api->namespace,
                'resourceVersion' => $object['metadata']['resourceVersion'] ?? null,
            ]),
            'spec' => ['replicas' => $replicas],
            'status' => ['replicas' => $replicas],
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function stamp(array $object): array
    {
        $object['metadata']['uid'] ??= (string) Str::uuid();
        $object['metadata']['creationTimestamp'] ??= Date::now()->toIso8601ZuluString();
        $object['metadata']['resourceVersion'] = (string) ++$this->resourceVersion;

        return $object;
    }

    private function key(string $apiVersion, string $plural, ?string $namespace, string $name): string
    {
        return "{$apiVersion}|{$plural}|".($namespace ?? '')."|{$name}";
    }

    /** @return array<string, string> */
    private function versionPayload(): array
    {
        return [
            'major' => $this->version->major,
            'minor' => $this->version->minor,
            'gitVersion' => $this->version->gitVersion,
            'gitCommit' => $this->version->gitCommit,
            'gitTreeState' => $this->version->gitTreeState,
            'buildDate' => $this->version->buildDate,
            'goVersion' => $this->version->goVersion,
            'compiler' => $this->version->compiler,
            'platform' => $this->version->platform,
        ];
    }

    private function notFound(ApiPath $api): Response
    {
        return $this->status(404, 'NotFound', "{$api->label()} \"{$api->name}\" not found");
    }

    private function status(int $code, string $reason, string $message): Response
    {
        return $this->json([
            'kind' => 'Status',
            'apiVersion' => 'v1',
            'metadata' => [],
            'status' => 'Failure',
            'message' => $message,
            'reason' => $reason,
            'code' => $code,
        ], $code);
    }

    /** @param array<array-key, mixed> $payload */
    private function json(array $payload, int $status = 200): Response
    {
        return $this->response(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $status, ['Content-Type' => 'application/json']);
    }

    /** @param array<string, string> $headers */
    private function response(string $body, int $status, array $headers): Response
    {
        $psr = Factory::response($body, $status, $headers)->wait();

        assert($psr instanceof ResponseInterface);

        return new Response($psr);
    }
}
