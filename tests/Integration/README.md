# Integration tests (live OrbStack)

These tests run the real CRUD / exec / listing matrix against a **local OrbStack**
Kubernetes cluster. They are **opt-in**, isolated to a throwaway namespace, and **excluded
from the default suite** (`composer test`) so CI without a cluster stays green.

## Safety

An `IntegrationGuard` hard-refuses to run unless **all** of these hold, so a production
cluster (e.g. an `*-aks` context) can never be targeted:

- `K8S_INTEGRATION=1` is set, **and**
- the active context is exactly `orbstack`, **and**
- the apiserver host is loopback (`127.0.0.1` / `localhost` / `::1`) or `*.orb.local`.

Every run creates a unique `k8s-it-<random>` namespace and deletes it afterwards (even on
failure). The user's global kube context is never switched — every call pins `--context`.

## Running

```bash
# Requires OrbStack running with the `orbstack` kube context available.
K8S_INTEGRATION=1 composer test-integration
```

## Traefik CRDs

The Traefik cases in `TraefikResourcesTest` (`IngressRoute`, `Middleware`) run **only** when
the Traefik CRDs (`traefik.io` API group) are installed on the cluster; otherwise they skip
gracefully via `ClusterFactory::hasApiGroup($cluster, 'traefik.io')`. Install Traefik on the
local OrbStack cluster with Helm to make them run:

```bash
helm repo add traefik https://traefik.github.io/charts
helm repo update
helm install traefik traefik/traefik \
  --namespace traefik --create-namespace \
  --kube-context orbstack --wait
```

This installs the `ingressroutes.traefik.io` and `middlewares.traefik.io` CRDs (group
`traefik.io/v1alpha1`) and the Traefik controller. The package ships no first-class resource
for the standalone `TraefikService` CRD, so that case is intentionally skipped.

## Environment variables

| Variable | Default | Purpose |
|---|---|---|
| `K8S_INTEGRATION` | _(unset)_ | Set to `1` to enable the suite. Absent → every test skips. |
| `K8S_INTEGRATION_CONTEXT` | `orbstack` | The kube context to extract credentials from. Must be `orbstack`. |
| `K8S_INTEGRATION_NAMESPACE` | random `k8s-it-…` | Override the throwaway namespace name. |
