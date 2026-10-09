<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Resources\ClusterRole;
use RoundlyConsulting\KubernetesApi\Resources\ClusterRoleBinding;
use RoundlyConsulting\KubernetesApi\Resources\Role;
use RoundlyConsulting\KubernetesApi\Resources\RoleBinding;

it('configures a namespaced role with rules', function () {
    $role = Role::make()->addRule([''], ['pods'], ['get', 'list']);

    expect($role->getKind())->toBe('Role')
        ->and($role->getVersion())->toBe('rbac.authorization.k8s.io/v1')
        ->and($role->usesNamespaces())->toBeTrue()
        ->and($role->getRules())->toBe([
            ['apiGroups' => [''], 'resources' => ['pods'], 'verbs' => ['get', 'list']],
        ]);
});

it('configures a cluster role with rules', function () {
    $role = ClusterRole::make()->addRule(['apps'], ['deployments'], ['*']);

    expect($role->getKind())->toBe('ClusterRole')
        ->and($role->usesNamespaces())->toBeFalse()
        ->and($role->getRules())->toHaveCount(1);
});

it('configures a role binding with a service account subject', function () {
    $binding = RoleBinding::make()
        ->setRoleRef('pod-reader')
        ->addSubject('ServiceAccount', 'default', 'production')
        ->addSubject('User', 'jane');

    expect($binding->getKind())->toBe('RoleBinding')
        ->and($binding->getRoleRef())->toBe([
            'apiGroup' => 'rbac.authorization.k8s.io',
            'kind' => 'Role',
            'name' => 'pod-reader',
        ])
        ->and($binding->getSubjects())->toBe([
            ['kind' => 'ServiceAccount', 'name' => 'default', 'namespace' => 'production'],
            ['kind' => 'User', 'name' => 'jane', 'apiGroup' => 'rbac.authorization.k8s.io'],
        ]);
});

it('binds a role binding to a cluster role', function () {
    $binding = RoleBinding::make()->setRoleRef('admin', 'ClusterRole');

    expect($binding->getRoleRef()['kind'])->toBe('ClusterRole');
});

it('configures a cluster role binding', function () {
    $binding = ClusterRoleBinding::make()
        ->setRoleRef('cluster-admin')
        ->addSubject('ServiceAccount', 'deployer', 'ci')
        ->addSubject('Group', 'admins');

    expect($binding->getKind())->toBe('ClusterRoleBinding')
        ->and($binding->usesNamespaces())->toBeFalse()
        ->and($binding->getRoleRef())->toBe([
            'apiGroup' => 'rbac.authorization.k8s.io',
            'kind' => 'ClusterRole',
            'name' => 'cluster-admin',
        ])
        ->and($binding->getSubjects())->toBe([
            ['kind' => 'ServiceAccount', 'name' => 'deployer', 'namespace' => 'ci'],
            ['kind' => 'Group', 'name' => 'admins', 'apiGroup' => 'rbac.authorization.k8s.io'],
        ]);
});

it('never gives a service account subject an api group', function () {
    // The apiserver requires apiGroup "" for a ServiceAccount subject (422 otherwise);
    // on a RoleBinding the namespace may be left out — it defaults to the binding's.
    $binding = RoleBinding::make()
        ->addSubject('ServiceAccount', 'builder')
        ->addSubject('User', 'jane');

    expect($binding->getSubjects())->toBe([
        ['kind' => 'ServiceAccount', 'name' => 'builder'],
        ['kind' => 'User', 'name' => 'jane', 'apiGroup' => 'rbac.authorization.k8s.io'],
    ]);
});

it('requires a namespace for a cluster role binding service account subject', function () {
    ClusterRoleBinding::make()->addSubject('ServiceAccount', 'builder');
})->throws(InvalidArgumentException::class, "The ServiceAccount subject 'builder' needs a namespace on a ClusterRoleBinding.");

it('keeps the api group on cluster role binding user and group subjects', function () {
    expect(ClusterRoleBinding::make()->addSubject('User', 'jane')->getSubjects())->toBe([
        ['kind' => 'User', 'name' => 'jane', 'apiGroup' => 'rbac.authorization.k8s.io'],
    ]);
});
