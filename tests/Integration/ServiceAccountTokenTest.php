<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\Resources\ServiceAccount;
use RoundlyConsulting\KubernetesApi\Tests\Integration\ClusterFactory;

uses()->group('integration');

beforeEach(function () {
    if (! ClusterFactory::shouldRun()) {
        $this->markTestSkipped('Set K8S_INTEGRATION=1 to run the live OrbStack integration suite.');
    }

    $this->cluster = ClusterFactory::make();
    $this->ns = ClusterFactory::namespace();

    ClusterFactory::createNamespace($this->cluster, $this->ns);
});

afterEach(function () {
    if (isset($this->cluster, $this->ns)) {
        ClusterFactory::deleteNamespace($this->cluster, $this->ns);
    }

    ClusterFactory::cleanup();
});

it('mints a service-account token that authenticates against the apiserver', function () {
    $account = ServiceAccount::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('minted');
    $account->create();

    $token = ServiceAccount::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('minted')->requestToken(600);

    expect($token->token)->not->toBe('')
        ->and($token->isExpired())->toBeFalse()
        ->and($token->expiresAt->isFuture())->toBeTrue();

    // A client that carries only the minted token, and the cluster's CA.
    $client = Cluster::make()->url($this->cluster->getUrl())->withToken($token->token);
    $client = $this->cluster->hasPathToCaCertificate()
        ? $client->withCaCertificate($this->cluster->getPathToCaCertificate())
        : $client->withoutSslVerification();

    expect($client->version()->gitVersion)->toStartWith('v');

    $review = $client->request(
        'POST',
        '/apis/authentication.k8s.io/v1/selfsubjectreviews',
        body: '{"apiVersion":"authentication.k8s.io/v1","kind":"SelfSubjectReview"}',
        contentType: 'application/json',
    );

    expect($review->json('status.userInfo.username'))->toBe("system:serviceaccount:{$this->ns}:minted");

    ServiceAccount::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('minted')->delete();

    // Deletion is asynchronous on a busy apiserver: wait for it, as the other integration tests do.
    retry(20, function (): void {
        $gone = ServiceAccount::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('minted')->missingOnCluster();
        throw_unless($gone, new RuntimeException('service account still present'));
    }, 250);

    expect(ServiceAccount::make()->setCluster($this->cluster)->setNamespace($this->ns)->setName('minted')->existsOnCluster())->toBeFalse();
});
