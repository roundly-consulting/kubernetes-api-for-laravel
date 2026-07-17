<?php

declare(strict_types=1);

/**
 * The secret-safe `about` capture (A).
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`,
 * which returns `''` — every "does not leak" check was vacuous.
 *
 * This package holds the most dangerous credentials in the fleet: a cluster
 * bearer token and a kubeconfig are root on the cluster. The section is careful today —
 * it reports counts and switches only — and that is exactly the property worth pinning,
 * because the tempting next line ('Cluster' => $host) is the one that ends up in a
 * pasted support ticket.
 */
it('renders the section without disclosing a cluster credential or endpoint', function (): void {
    config()->set('kubernetes.rate_limits.enabled', true);
    config()->set('kubernetes.rate_limits.adaptive', true);
    config()->set('kubernetes.traefik.group', 'traefik.io/v1alpha1');

    expect('kubernetes')->toLeakNoSecrets(
        secrets: [
            // Nothing about WHERE the cluster is or HOW we authenticate to it may render.
            'https://k8s-prod.internal:6443',
            'eyJhbGciOiJSUzI1NiIsImtpZCI6',
            '/var/run/secrets/kubernetes.io/serviceaccount/token',
            '/Users/ci/.kube/config',
        ],
        mustRender: [
            'Rate limiting',
            'Adaptive throttling',
            'Registered resources',
            'Traefik group',
            // The count must render — the positive proof the resource line is REPORTING
            // rather than silently empty. 33 resources ship by default; a section that
            // rendered nothing would otherwise satisfy the leak half trivially.
            '33',
            'ENABLED',
        ],
    );
});
