# Changelog

All notable changes to `kubernetes-api-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

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
