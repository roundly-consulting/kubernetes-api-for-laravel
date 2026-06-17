# Changelog

All notable changes to `kubernetes-api-for-laravel` will be documented in this file.

## Unreleased

### Added

- **One-line cluster setup:** `Kubernetes::fromKubeConfig(path, context)` and
  `Kubernetes::inCluster()` build a configured client from a kubeconfig context or in-cluster
  service-account mounts.
- **Strongly-typed resource accessors** on the `Kubernetes` client (`pods()`, `deployments()`,
  …) returning their concrete resource class for IDE autocompletion and static analysis.
- **New resources:** ReplicaSet, StatefulSet, DaemonSet, CronJob, Ingress, Event, Endpoints,
  ServiceAccount, Role, RoleBinding, ClusterRole, ClusterRoleBinding, NetworkPolicy,
  HorizontalPodAutoscaler, ResourceQuota, LimitRange, and ReplicationController, each with
  typed helpers and registered in `config/kubernetes.php`.
- **New Traefik resources:** `TraefikTlsStore` (kind `TLSStore`, with
  `setDefaultCertificate()`), `TraefikServersTransport` (kind `ServersTransport`, with
  `setServerName()`, `insecureSkipVerify()`, root-CA and client-cert Secret refs), and
  `TraefikTlsOption` (kind `TLSOption`, with `setMinVersion()`/`setMaxVersion()`/
  `setCipherSuites()`). Their REST plurals (`tlsstores`, `serverstransports`, `tlsoptions`)
  are pinned to the real CRDs, they honour `kubernetes.traefik.group`, and they are exposed
  via the `traefikTlsStores()`, `traefikServersTransports()`, and `traefikTlsOptions()`
  cluster accessors.
- **TLS certificate Secrets:** `Secret::setType()` / `Secret::getType()` and
  `Secret::asTlsCertificate($cert, $key)`, which builds a `kubernetes.io/tls` Secret by
  base64-encoding the PEM certificate and key into `data['tls.crt']` and `data['tls.key']`.
- **Operational verbs:** `patch()` (strategic-merge / merge / JSON Patch / server-side apply),
  `dryRun()`, `scale()` (via the `/scale` subresource), `rolloutRestart()`, and `watch()`.
- **Pod logs and exec:** `Pod::logs()` / `Pod::streamLogs()` and `Pod::exec()` — the latter over
  an isolated WebSocket transport speaking the `v4.channel.k8s.io` subprotocol, returning
  stdout, stderr, and the exit code.
- **Listing ergonomics:** label/field selectors (`whereLabel`, `whereLabelIn`,
  `whereLabelExists`, `whereField`, …), pagination (`limit`, `continueFrom`, `getPage`),
  `allNamespaces()`, and lazy iteration (`lazy()` / `each()`) that auto-follows continue tokens.
- **New DTOs:** `KubeConfig`, `PodLogOptions`, `ResourcePage`, `WatchEvent`, `KubernetesPatch`
  (with a `PatchType` enum), and `ExecResult`.
- **Commands:** `kubernetes:install` (publish config + next steps) and `kubernetes:ping`
  (connectivity diagnostic).
- **Configurable Traefik API group** via `kubernetes.traefik.group`, defaulting to
  `traefik.io/v1alpha1`.
- **`missingOnCluster()`**, an explicit `$plural` override and a `getPluralKind()` method,
  robust subresource/all-namespaces path resolution, and `#[SensitiveParameter]` on token
  setters.
- **Guarded OrbStack integration suite** (`composer test-integration`, opt-in via
  `K8S_INTEGRATION=1`) with an `IntegrationGuard` that refuses any non-OrbStack/non-loopback
  target, plus a manual `integration-tests` workflow.

### Changed

- **Behavioural fix — JSON encoding (WI-0a):** the global `": []" → ": {}"` string replacement
  applied to every payload has been removed. Empty Kubernetes objects are now emitted with an
  explicit `EmptyObject` marker, so legitimately-empty lists (`"finalizers": []`) and string
  values containing `": []"` are no longer corrupted. Payloads that previously relied on the
  blanket replacement to send `{}` should set the relevant node to a
  `RoundlyConsulting\KubernetesApi\Resources\Types\EmptyObject`.
- `updateOrCreate()` now carries the server's current `resourceVersion` into the update,
  preventing lost updates from concurrent writes (surfacing a typed 409 instead).

### Fixed

- **`Deployment` now targets the correct `apps/v1` API group.** It previously inherited the
  default `v1`, building the core-group path `/api/v1/namespaces/{ns}/deployments` and sending
  `apiVersion: v1` — which 404s against a real cluster, because the core group has no
  `deployments`. The resource now declares `apps/v1`, so it resolves
  `/apis/apps/v1/namespaces/{ns}/deployments` and serialises `apiVersion: apps/v1`. No code
  change is needed in consuming apps; remove any manual `->setVersion('apps/v1')` workaround.
- **`TraefikMiddleware` now resolves the correct `middlewares` REST path.** "Middleware" is
  uncountable, so the naive pluraliser left it unchanged and built
  `/apis/traefik.io/v1alpha1/namespaces/{ns}/middleware` — a 404 against a real cluster. The
  resource now declares the explicit plural `middlewares`, matching the CRD. No code change is
  needed in consuming apps.
- **Traefik resources are now covered by the live integration suite.** `IngressRoute`,
  `Middleware`, `TLSStore`, `ServersTransport`, and `TLSOption` create/read/delete against a
  real Traefik v3 install on the OrbStack cluster, using a valid `services` reference on each
  route as the CRD requires.
- **Empty / select-all selectors now serialise as `{}`.** `NetworkPolicy::setPodSelector([])`,
  `Service::setSelectors([])`, and the workload pod selectors (`Deployment`, `ReplicaSet`,
  `StatefulSet`, `DaemonSet`, `ReplicationController`) previously emitted an empty matchLabels
  map as `[]`, which the apiserver rejects. An empty selector is now routed through the
  `EmptyObject` marker so it serialises as the empty object `{}` (the "select all" / "match
  all" semantics), while the getters still return `[]`.

### Deprecated

- `getPlurarKind()` → use `getPluralKind()` (the misspelled alias is kept until the next major).
- `Job::getSuccededPodsCount()` → use `getSucceededPodsCount()` (alias kept until the next
  major).
