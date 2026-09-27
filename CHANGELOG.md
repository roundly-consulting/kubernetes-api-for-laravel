# Changelog

All notable changes to `kubernetes-api-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- A fluent, Eloquent-style Kubernetes API client behind the `Kubernetes` facade, built on
  Laravel's HTTP client (so `Http::fake()` works in tests).
- Clusters configured on the fly, from a kubeconfig (`Kubernetes::fromKubeConfig()`), from
  in-pod credentials (`Kubernetes::inCluster()`), or registered by name.
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
- `php artisan kubernetes:ping` to check connectivity to a registered cluster.
