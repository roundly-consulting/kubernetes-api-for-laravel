<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;
use RoundlyConsulting\KubernetesApi\Resources\Deployment;
use RoundlyConsulting\KubernetesApi\Resources\Pod;
use RoundlyConsulting\KubernetesApi\Tests\Fixtures\CustomPod;
use RoundlyConsulting\KubernetesApi\Tests\Fixtures\SwappedResourceTestCase;

/**
 * The swap proof for the `kubernetes.resources.*` registry seam.
 *
 * `toHonourModelSwap` is deliberately NOT used: it requires an Eloquent model carrying
 * the `CountsCreations` trait, and asserts a `created` model event. This package ships
 * no Eloquent model at all — `Resource implements Arrayable, Jsonable` — so the
 * expectation has nothing to bind to. The seam is real regardless, and it is the same
 * bug class the expectation exists for: a documented config key inviting a host to
 * point a name at its own class, which the package must then actually resolve.
 *
 * The swap is applied before boot by {@see SwappedResourceTestCase}, which this
 * directory is bound to — Pest binds a test case per directory, not per file: a host
 * sets the key in its published config, so the proof sets it the same way. (The
 * accessors read the map at call time, so a runtime registration works too — pinned in
 * FacadeTest.)
 */
it('resolves the host resource class through the typed accessor', function (): void {
    $cluster = new Cluster;

    $pod = $cluster->pods();

    // The CONCRETE class, not `instanceof`: CustomPod extends Pod, so an instanceof
    // check against Pod would pass for the packaged class and prove nothing.
    expect($pod::class)->toBe(CustomPod::class)
        ->and($pod->customMarker())->toBe('host-owned-pod');
});

/**
 * The swap must survive the facade, which is how a host actually reaches the registry.
 */
it('resolves the host resource class through the facade', function (): void {
    $pod = Kubernetes::pods();

    expect($pod::class)->toBe(CustomPod::class);
});

/**
 * A resource the host did NOT swap must still resolve to the packaged class — the proof
 * that the swap is per-name rather than a blanket that would silently redirect
 * everything.
 */
it('leaves an unswapped resource on the packaged class', function (): void {
    $cluster = new Cluster;

    expect($cluster->deployments()::class)
        ->toBe(Deployment::class);
});

/**
 * The config default really is the packaged Pod — asserted here against the base
 * suite's own config rather than this directory's, so the seam cannot rot in the other
 * direction (a key that "works" because it was never really pointed at Pod to begin
 * with).
 */
it('defaults the pods key to the packaged resource', function (): void {
    $default = require __DIR__.'/../../config/kubernetes.php';

    expect($default['resources']['pods'])->toBe(Pod::class);
});
