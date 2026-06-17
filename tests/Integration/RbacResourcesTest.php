<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\ClusterRole;
use RoundlyConsulting\KubernetesApi\Resources\ClusterRoleBinding;
use RoundlyConsulting\KubernetesApi\Resources\Role;
use RoundlyConsulting\KubernetesApi\Resources\RoleBinding;
use RoundlyConsulting\KubernetesApi\Tests\Integration\ClusterFactory;

uses()->group('integration');

beforeEach(function () {
    if (! ClusterFactory::shouldRun()) {
        $this->markTestSkipped('Set K8S_INTEGRATION=1 to run the live OrbStack integration suite.');
    }

    $this->cluster = ClusterFactory::make();
    $this->ns = ClusterFactory::namespace();

    // Unique suffix for the cluster-scoped objects so parallel runs don't clash.
    $this->suffix = substr($this->ns, strrpos($this->ns, '-') + 1);

    ClusterFactory::createNamespace($this->cluster, $this->ns);
});

afterEach(function () {
    if (isset($this->cluster, $this->suffix)) {
        foreach ([
            ClusterRole::make()->setCluster($this->cluster)->setName("k8s-it-cr-{$this->suffix}"),
            ClusterRoleBinding::make()->setCluster($this->cluster)->setName("k8s-it-crb-{$this->suffix}"),
        ] as $resource) {
            try {
                $resource->delete();
            } catch (Throwable) {
                // best-effort cleanup
            }
        }
    }

    if (isset($this->cluster, $this->ns)) {
        ClusterFactory::deleteNamespace($this->cluster, $this->ns);
    }

    ClusterFactory::cleanup();
});

it('creates, reads and deletes a namespaced role', function () {
    $role = Role::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('reader')
        ->addRule([''], ['pods'], ['get', 'list']);

    expect($role->create()->wasRecentlyCreated())->toBeTrue();

    $found = Role::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('reader')->find();
    expect($found->getRules())->toHaveCount(1);

    $found->delete();
});

it('creates, reads and deletes a role binding', function () {
    Role::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('reader')
        ->addRule([''], ['pods'], ['get'])->create();

    $binding = RoleBinding::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('reader-binding')
        ->setRoleRef('reader')
        ->addSubject('ServiceAccount', 'default', $this->ns);

    expect($binding->create()->wasRecentlyCreated())->toBeTrue();

    $found = RoleBinding::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('reader-binding')->find();
    expect($found->getRoleRef()['name'])->toBe('reader');

    $found->delete();
});

it('creates, reads and deletes a cluster role', function () {
    $name = "k8s-it-cr-{$this->suffix}";

    $role = ClusterRole::make()->setCluster($this->cluster)->setName($name)
        ->addRule([''], ['nodes'], ['get', 'list']);

    expect($role->create()->wasRecentlyCreated())->toBeTrue();

    $found = ClusterRole::make()->setCluster($this->cluster)->setName($name)->find();
    expect($found->getRules())->toHaveCount(1);

    $found->delete();
});

it('creates, reads and deletes a cluster role binding', function () {
    $roleName = "k8s-it-cr-{$this->suffix}";
    $bindingName = "k8s-it-crb-{$this->suffix}";

    ClusterRole::make()->setCluster($this->cluster)->setName($roleName)
        ->addRule([''], ['nodes'], ['get'])->create();

    $binding = ClusterRoleBinding::make()->setCluster($this->cluster)->setName($bindingName)
        ->setRoleRef($roleName)
        ->addSubject('ServiceAccount', 'default', $this->ns);

    expect($binding->create()->wasRecentlyCreated())->toBeTrue();

    $found = ClusterRoleBinding::make()->setCluster($this->cluster)->setName($bindingName)->find();
    expect($found->getRoleRef()['name'])->toBe($roleName);

    $found->delete();
});
