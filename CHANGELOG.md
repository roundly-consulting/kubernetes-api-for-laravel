# Changelog

All notable changes to `kubernetes-api-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

### Added

- `Cluster::withTokenFile()` / `getTokenFile()` and `KubeConfig::$tokenFile`: a bearer-token
  file read again for every request, so a rotated token is picked up. `withToken()` replaces
  a token file.
- Kubeconfig users with a `tokenFile` (relative to the kubeconfig's directory, like the
  certificate paths). The file is read again for every request.

### Fixed

- `Volume::emptyDirectory()` without options now sends `emptyDir: {}`. It used to send the
  string `"{}"`, which the apiserver rejected (400) for every pod built that way.
- `PersistentVolume::setSource()` / `getSource()` now use the inline volume-source fields
  (`spec.nfs`, `spec.csi`, …) the apiserver expects. They used to read and write a
  non-existent `spec.source.<type>`, so no PersistentVolume built with `setSource()` was
  valid and `getSource()` never read a real one. `setSource()` now replaces any other source,
  and `getSource()` without a name returns the source keyed by its type (`['csi' => [...]]`).
- The dynamic `getX()` / `setX()` / `withX()` / `addToX()` / `removeX()` accessors now strip
  only the leading verb. `getTargetPort()` used to read `tarPort` and `setSubsets()` wrote
  `subs`, because every occurrence of the verb in the name was removed.
- `RoleBinding::addSubject()` / `ClusterRoleBinding::addSubject()` no longer give a
  `ServiceAccount` subject the rbac `apiGroup` when no namespace is passed (the apiserver
  answered 422). Behaviour change: `ClusterRoleBinding::addSubject('ServiceAccount', …)`
  without a namespace now throws `InvalidResourceException`, since a cluster-scoped binding
  cannot default it.
- `TraefikService::to()` now accepts `int|string` and sends a numeric port (`80` or `'80'`)
  as an integer. A quoted `"80"` was read by Traefik as a port name, which no service has,
  so the route never served. Named ports (`'http'`) are unchanged.
- `TraefikRoute::hostRule()` and `pathRule()` now validate their input and throw
  `InvalidResourceException` for anything that is not an RFC 1123 hostname, or a path
  starting with `/` free of backticks and whitespace. A value such as
  ``a.test`) || Host(`victim.test`` used to inject extra matchers into the rule. Raw rules
  still go through `matchRule()`.
- The label selector builders (`whereLabel()`, `whereLabelNot()`, `whereLabelIn()`,
  `whereLabelNotIn()`, `whereLabelExists()`, `whereLabelMissing()`) now validate keys and
  values against the Kubernetes label grammar and throw `InvalidResourceException` before
  any request. `whereLabelIn('tenant', ['acme,globex'])` used to widen a tenant filter to
  both tenants.
- In-cluster clusters (`source: in-cluster`, `Kubernetes::inCluster()`) now re-read the
  service-account token file for every HTTP request and exec. The token used to be read
  once and kept for the life of the process, so long-running workers got 401s after the
  kubelet rotated it.
- Kubeconfig users that authenticate with an `exec` plugin, an `auth-provider` or a
  username/password now throw `KubeConfigException` naming the user and the method. They used
  to load with no credentials, so every request went out anonymous (401/403) with no hint why.
- `lazy()` and `each()` no longer leave the last `continue` token on the builder. A second
  `lazy()` or a `get()` on the same builder used to return only the last page.
- Resources returned by `get()`, `lazy()`, `find()`, `create()`, `update()`, `patch()`,
  `delete()` and `watch()` now keep a generic `Resource`'s runtime kind, apiVersion,
  `setPlural()` and `usingNamespaces()`. A listed generic resource (list items carry no
  kind/apiVersion) could not be written back, and a namespaced one wrote to the
  cluster-scoped path.
- ConfigMap `data` / `binaryData`, Secret `data` / `stringData` and `metadata.labels` /
  `annotations` now always encode as JSON objects. Keys that are numeric strings (`"0"`,
  `"1"`) used to turn the map into a JSON list, whether it was decoded from the apiserver or set
  with `setData(['0' => …])`, and the apiserver answered 400.
- `ConfigMap::setData([])` and `Secret::setData([])` now remove `data`, and `setLabels([])` /
  `setAnnotations([])` (and removing the last label or annotation) send `{}`. All four used to
  send the JSON list `[]`, which the apiserver answered with a 400.
- `toArray()`, `toJson()` and `dump()` no longer write `kind` / `apiVersion` into the resource,
  so serialising a listed item no longer makes `isDirty()` report true. The output still
  carries both.
- `getName()` now returns `?string` (null for an unnamed resource) instead of throwing a
  `TypeError`. `Volume::fromSecret()` / `fromConfigMap()`, `Job::podsSelectors()` and
  `Service::getClusterDns()` throw `InvalidResourceException` for an unnamed resource instead.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- A fluent, Eloquent-style Kubernetes API client behind the `Kubernetes` facade, built on
  Laravel's HTTP client. The facade root is the injectable `KubernetesManager`; the facade is
  `final` and documents every method.
- Named clusters from config (`default` + `clusters`, each with a `url`, `kubeconfig` or
  `in-cluster` source, a default `namespace` and a `manager` name) or registered in code with
  `Kubernetes::registerCluster()`; `Kubernetes::cluster()`, `hasCluster()` and `clusters()`.
- Ad-hoc clusters with `Kubernetes::url()`, `connect(KubeConfig)`, `fromKubeConfig()` and
  `inCluster()`. Clusters (`RoundlyConsulting\KubernetesApi\Cluster`) are immutable: every
  `url()` / `with*()` / `without*()` returns a new instance.
- `Kubernetes::namespace('shop')` — a namespace scope that pins every resource it hands out and
  throws `NamespaceScopeException` on any attempt to leave it.
- `Kubernetes::ping()` and `Kubernetes::version()` (a `VersionInfo` DTO), on any cluster too.
- `Kubernetes::fake()` — an in-memory apiserver for tests (`seed`, `seedLogs`, `stubExec`,
  `stubVersion`, `unreachable`) that records every request, with `assertCreated/Updated/Patched/
  Scaled/Restarted/Deleted/Executed/Sent` and an `assertNothing*` for each.
- A raw `Cluster::request()` escape hatch that is authenticated, rate limited and faked like the
  typed resources.
- First-class resources for the core API — pods, deployments, services, secrets, jobs, ingresses,
  RBAC, storage and more — plus Traefik routes, middlewares, TLS and transport CRDs.
- Your own custom resources (CRDs), registered in config or with `Kubernetes::registerResource()`.
- Create, find, update, `updateOrCreate()` (conflict-safe) and delete, with label and field
  selectors and paginated lists.
- `patch()`, `scale()`, `rolloutRestart()`, `dryRun()` and long-lived `watch()` streams.
- Pod `logs()` and `exec()`.
- Typed value objects (`Container`, `Port`, `Probe`, `Volume`, `VolumeMount`) for building
  resources, and a TLS helper for `kubernetes.io/tls` secrets.
- Per-cluster client-side rate limiting that honours the apiserver's `Retry-After`, with an
  optional fail-fast `max_wait`.
- `php artisan kubernetes:ping` to check connectivity to a cluster (the default one when no
  name is given).
- Typed accessors for all 33 packaged resources on the facade and on every cluster (previously
  17 of them were untyped macros).

### Changed

- The client class `RoundlyConsulting\KubernetesApi\Kubernetes` is split into the
  `KubernetesManager` facade root and the immutable `Cluster` client.
- `setManagerName()` is now `withManagerName()` and returns a copy.
- `registerCluster()` closures must return the configured `Cluster`; the generated
  `Kubernetes::get<Name>Cluster()` accessors are gone (use `Kubernetes::cluster('<name>')`).
- The Namespace resource class is `Resources\KubernetesNamespace` (was `Resources\Namespaces`);
  the accessor is still `namespaces()`.
- An unknown cluster throws `ClusterNotFoundException` (was `BadMethodCallException`); a resource
  used without a cluster throws `ClusterConfigurationException`.
- Rate limiting builds its limiter through the `RateLimits` facade of
  http-client-rate-limits-for-laravel.

### Removed

- The misspelled aliases `getPlurarKind()` and `Job::getSuccededPodsCount()` (use
  `getPluralKind()` and `getSucceededPodsCount()`).

### Fixed

- `Kubernetes::url(...)->withToken(...)` mutated the facade's shared instance, so a token or URL
  set by one caller leaked into every later `Kubernetes::…()` call.
- A typo in `KUBERNETES_VERIFY_SSL` turned TLS verification off, `KUBERNETES_RATELIMIT_ENABLED=off`
  kept throttling on, and `KUBERNETES_RATELIMIT_ADAPTIVE=1` left adaptive off. All three switches
  now take the usual env spellings and throw on anything else.
- A kubeconfig's `insecure-skip-tls-verify` was cast with `(bool)`, so a quoted `"false"` skipped
  TLS verification. It is read as a boolean now and anything else throws `KubeConfigException`.
- Junk config fell back silently: `(int)` turned `KUBERNETES_RATELIMIT=five` into a broken budget,
  a junk `max_wait` / `jitter` was dropped, a `timespan` typo became a minute, a non-numeric
  `client.options.timeout` was dropped from streams (and an env-string timeout was refused by
  Guzzle), and non-string cluster settings, `default`, `traefik.group` or a non-array `clusters` /
  `resources` map quietly used defaults. Every key is read strictly now and throws naming it; a
  key that is not set keeps its default.
- Blank means not set: a blank value (a host's `KEY=`, empty or whitespace only) reads exactly
  like an absent key and takes its default. `KUBERNETES_TOKEN=` sends no token,
  `KUBERNETES_SOURCE=` is `url`, `KUBERNETES_NAMESPACE=` is `default`, `KUBERNETES_CLUSTER=` is the
  `default` cluster, a blank `KUBERNETES_STREAM_TIMEOUT` is the default 0, and a blank
  `max_wait` / `jitter` is unset (a blank `max_wait` used to become a 0 ms fail-fast ceiling).
- A `resources` entry that is not a `Resource` subclass failed with an undefined-method error;
  it throws `InvalidResourceException`.
