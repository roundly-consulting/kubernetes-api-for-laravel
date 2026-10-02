<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\DataTransferObjects\WatchEvent;
use RoundlyConsulting\KubernetesApi\Facades\Kubernetes;

/*
 * The apiserver sends empty structs as `{}` (`resources`, `securityContext`, `emptyDir`,
 * `status`, …). Decoded into PHP arrays they became `[]`, so `find()->…->update()` sent
 * `"resources": []` back and a real apiserver refused the whole object with a 400
 * ("cannot unmarshal array into … v1.ResourceRequirements").
 */

const SERVER_DEPLOYMENT = '{"apiVersion":"apps/v1","kind":"Deployment","metadata":{"name":"checkout","namespace":"shop","resourceVersion":"7"},'
    .'"spec":{"replicas":1,"selector":{"matchLabels":{"app":"checkout"}},"strategy":{},"template":{"metadata":{"creationTimestamp":null,"labels":{"app":"checkout"}},'
    .'"spec":{"containers":[{"name":"app","image":"nginx","resources":{},"args":[]}],"securityContext":{},"volumes":[{"name":"cache","emptyDir":{}}]}}},"status":{}}';

beforeEach(function (): void {
    Http::preventStrayRequests();
    config()->set('kubernetes.clusters.default.url', 'https://k8s.example');
});

function sentBody(string $method): string
{
    $body = '';

    Http::assertSent(function (Request $request) use ($method, &$body): bool {
        if ($request->method() === $method) {
            $body = $request->body();
        }

        return true;
    });

    return $body;
}

it('sends the empty objects of a found resource back as objects on update', function (): void {
    Http::fake(['*' => Http::response(SERVER_DEPLOYMENT, 200, ['Content-Type' => 'application/json'])]);

    $deployment = Kubernetes::namespace('shop')->deployments()->withName('checkout')->find();

    expect($deployment->getSpec('template.spec.containers.0.resources'))->toBe([])
        ->and($deployment->getSpec('strategy', 'none'))->toBe([])
        ->and($deployment->getReplicas())->toBe(1);

    $deployment->setReplicas(5)->update();

    $sent = json_decode(sentBody('PUT'));

    expect($sent->spec->replicas)->toBe(5)
        ->and($sent->spec->strategy)->toEqual(new stdClass)
        ->and($sent->spec->template->spec->containers[0]->resources)->toEqual(new stdClass)
        ->and($sent->spec->template->spec->containers[0]->args)->toBe([])
        ->and($sent->spec->template->spec->securityContext)->toEqual(new stdClass)
        ->and($sent->spec->template->spec->volumes[0]->emptyDir)->toEqual(new stdClass)
        ->and($sent->status)->toEqual(new stdClass);
});

it('keeps the empty objects of listed and watched resources', function (): void {
    Http::fake(['*' => Http::sequence()
        ->push('{"kind":"DeploymentList","metadata":{},"items":['.SERVER_DEPLOYMENT.']}', 200, ['Content-Type' => 'application/json'])
        ->push('{"type":"ADDED","object":'.SERVER_DEPLOYMENT."}\n", 200, ['Content-Type' => 'application/json'])
        ->push(SERVER_DEPLOYMENT, 200, ['Content-Type' => 'application/json'])
        ->push(SERVER_DEPLOYMENT, 200, ['Content-Type' => 'application/json'])]);

    $listed = Kubernetes::namespace('shop')->deployments()->get()->first();

    $watched = null;
    Kubernetes::namespace('shop')->deployments()->watch(function (WatchEvent $event) use (&$watched): void {
        $watched = $event->object;
    });

    expect($listed->toJson())->toContain('"resources":{}')
        ->and($watched->toJson())->toContain('"resources":{}')
        ->and($listed->setReplicas(2)->update()->toJson())->toContain('"emptyDir":{}');
});
