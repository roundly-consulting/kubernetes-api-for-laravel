<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\KubernetesManager;
use RoundlyConsulting\KubernetesApi\Resources\Resource;
use RoundlyConsulting\KubernetesApi\Resources\Types\Type;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * Kubernetes-api shipped with no architecture test at all, so every preset here is a
 * new guard rather than a replacement.
 */
ArchPresets::strictTypes('RoundlyConsulting\KubernetesApi');

/**
 * The `Resources` namespace is exempt as a NAMESPACE, and that is a deliberate
 * statement rather than a shortcut: every class in it is a registered, config-swappable
 * API resource. `config('kubernetes.resources')` maps a name to a class and the provider
 * registers each one by name, so a host repoints `pods` at its own `Pod` subclass —
 * `final` on any of the 45 would be a fatal error the moment it did. They are the
 * package's documented extension surface, not classes that escaped a rule.
 *
 * `KubernetesManager` is the facade root and stays open because `Testing\KubernetesFake`
 * extends it — the fake must be a subtype of the accessor so an injected manager gets
 * the fake too. The exception hierarchy is extended by hosts catching package errors
 * uniformly. The facade itself is final (pinned by `toDocumentItsRoot()`).
 *
 * Note that these resources are NOT Eloquent models and sit behind no `*_model` key
 * (`Resource implements Arrayable, Jsonable` — there is no Model in this package at
 * all), so `swappableModelsAreNotFinal` and `modelsResolveThroughSeam` are not adopted:
 * both are aimed at an Eloquent seam this package does not have, and both halves would
 * be inert. The reason those 45 classes must stay non-final is pinned instead by
 * tests/ResourceSwap/ResourceRegistrySwapTest.php, which drives a real host subclass
 * through the registry before boot — and which found that the seam did not work at all.
 *
 * The list moved to the `$ignoring` PARAMETER, joining the one `noDebuggingLeftovers`
 * already carries below — two lists in one file, which only works as of
 * testing-for-laravel 25f6cc1 (each pin is keyed by its own preset's description; a
 * fixed one made the second list a hard TestAlreadyExist). The parameter buys both
 * guarantees the fluent form cannot: the entries are rot-checked, and the prefix SHADOW
 * is recovered.
 *
 * The shadow is not hypothetical here. The manager used to be `Kubernetes::class`, which
 * also silenced `KubernetesApiServiceProvider` — Pest matches exemptions by string
 * prefix, not class identity (pest-plugin-arch Blueprint.php:103), and the provider's
 * FQCN started with the manager's. The parameter form applies the rule by reflection, so
 * no prefix can swallow a neighbour again.
 */
ArchPresets::finalByDefault('RoundlyConsulting\KubernetesApi', [
    // Namespace-form, deliberate subtree exclusions — the shadow guard leaves these
    // alone on purpose: what a namespace exemption reaches IS the point.
    'RoundlyConsulting\KubernetesApi\Resources',
    'RoundlyConsulting\KubernetesApi\Exceptions',
    KubernetesManager::class,
]);

/**
 * Model convenience methods and traits must reach behaviour through the manager so the
 * fake sees every call. This package has no `Models` and no `Actions` (it is a
 * remote-API client exposing resource objects), but it does ship `Concerns` and
 * `Traits` — the guard keeps it that way if an action ever lands.
 */
ArchPresets::modelsGoThroughTheFacade('RoundlyConsulting\KubernetesApi');

/**
 * Scoped to the namespaces where a primitive would MEAN cryptography, rather than
 * applied package-wide and then relaxed with `->ignoring()`. Pest's `->ignoring()` is
 * CLASS-scoped, so exempting `Resources\Secret` for its legitimate base64 would blind
 * that whole class — the single most security-sensitive class in the package — to all
 * nineteen primitives. Scoping the ban instead of the exemption is what keeps the
 * coverage where it is worth having.
 *
 * The primitives this package uses are protocol, not cryptography, and belong where
 * they are:
 *  - `Resources\Secret` / `Support\KubeConfigLoader` base64 Secret data and kubeconfig
 *    blobs, because the Kubernetes API defines those fields AS base64 on the wire;
 *  - `WebSocket\ExecConnection` builds `Sec-WebSocket-Key` and `WebSocketFrame` builds
 *    a masking key from `random_bytes`, both required verbatim by RFC 6455.
 * None is a re-implementation of something crypto-for-laravel offers.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\KubernetesApi\Http');
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\KubernetesApi\Concerns');
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\KubernetesApi\Commands');

/**
 * The package-wide half: no keyed/hashing/signing primitive anywhere, including in the
 * protocol namespaces above. base64 and `random_bytes` are deliberately absent from
 * this list — they are covered, where they mean crypto, by the scoped presets — but
 * there is no legitimate reason for this package to hash, HMAC, or sign anything
 * itself. If a token or signature scheme ever appears here, it comes from
 * crypto-for-laravel.
 */
arch('no keyed or hashing primitive is re-implemented locally')
    ->expect('RoundlyConsulting\KubernetesApi')
    ->not->toUse([
        'hash',
        'hash_hmac',
        'hash_pbkdf2',
        'openssl_encrypt',
        'openssl_decrypt',
        'openssl_sign',
        'openssl_verify',
        'openssl_pkey_new',
        'openssl_pkey_get_private',
        'openssl_pkey_get_public',
        'sodium_crypto_sign',
        'sodium_crypto_generichash',
    ]);

/**
 * The Dependency Policy as a test — the assertion that caught bug #6 fleet-wide, where
 * CI installed testbench into `require` before the suite ran. No `alsoAllow`: this
 * package's `require` ships only php/illuminate/symfony/roundly, and the workflow
 * installs test tooling with `--dev`. If it goes red the graph is wrong; never widen
 * the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

/**
 * A stray `dd`/`dump`/`ray` shipped to production — pointed at `src` explicitly so the
 * rule cannot go quietly vacuous if the suite is run from another working directory.
 *
 * `Resource` and `Types\Type` are exempt because their `dd()`/`dump()` are not
 * leftovers: they are deliberate public API — `$pod->dump()` — mirroring Laravel's own
 * `Collection::dump()` and `Model::dd()`. The call inside each is the method's entire
 * purpose.
 *
 * These go through the `$ignoring` PARAMETER rather than Pest's `->ignoring()` on
 * purpose: the parameter is pinned by `exemptionsExist()`, so if either class is ever
 * renamed or its helper deleted, this list fails as stale instead of silently
 * exempting nothing. That matters here — a two-entry allow-list against a ban this
 * broad is precisely the thing that rots into a hole nobody can see.
 */
ArchPresets::noDebuggingLeftovers([
    RoundlyConsulting\KubernetesApi\Resources\Resource::class,
    Type::class,
], __DIR__.'/../src');
