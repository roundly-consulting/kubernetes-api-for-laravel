<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use RoundlyConsulting\KubernetesApi\Cluster;
use RoundlyConsulting\KubernetesApi\Resources\Endpoints;
use RoundlyConsulting\KubernetesApi\Resources\Pod;
use RoundlyConsulting\KubernetesApi\Resources\Types\Port;
use RoundlyConsulting\KubernetesApi\Resources\Types\Type;
use RoundlyConsulting\KubernetesApi\Traits\Resource\HasAttributes;

it('stores attributes', function () {
    $instance = new class
    {
        use HasAttributes;
    };

    $instance->setAttribute('name', 'ahoy');

    expect($instance->getAttribute('name'))->toBe('ahoy');
});

it('returns default value when no attribute is found by name', function () {
    $instance = new class
    {
        use HasAttributes;
    };

    expect($instance->getAttribute('name', 'default'))->toBe('default');
});

it('adds value to attribute', function () {
    $instance = new class(['spec' => ['one']])
    {
        use HasAttributes;
    };

    $instance->addToAttribute('spec', 'two');

    expect($instance->getAttribute('spec'))->toBe(['one', 'two']);
});

it('removes attribute', function () {
    $instance = new class(['spec' => ['one']])
    {
        use HasAttributes;
    };

    expect($instance->getAttribute('spec'))->toBe(['one']);

    $instance->removeAttribute('spec');

    expect($instance->getAttribute('spec'))->toBeNull();
});

it('sets attributes', function () {
    $instance = new class(['spec' => ['one']])
    {
        use HasAttributes;
    };

    $instance->setAttributes(['metadata' => ['name' => 'test']]);

    expect($instance->getAttribute('metadata'))
        ->toBe(['name' => 'test'])
        ->and($instance->getAttribute('spec'))
        ->toBeNull();
});

it('removes all attributes', function () {
    $instance = new class(['spec' => ['one']])
    {
        use HasAttributes;
    };

    $instance->removeAttributes();

    expect($instance->getAttribute('spec'))
        ->toBeNull();
});

it('it checks whether instance has dirty attributes', function () {
    $instance = new class(['name' => 'test'])
    {
        use HasAttributes;
    };

    expect($instance)->isDirty()->toBeFalse();

    $instance->setAttribute('name', 'okay');

    expect($instance)->isDirty()->toBeTrue();
});

it('sync dirty attributes to original values', function () {
    $instance = new class(['name' => 'test'])
    {
        use HasAttributes;
    };

    $instance->setAttribute('name', 'okay');

    expect($instance)->isDirty()->toBeTrue();

    $instance->sync();

    expect($instance)->isDirty()->toBeFalse();
});

it('discards changes', function () {
    $instance = new class(['name' => 'test'])
    {
        use HasAttributes;
    };

    $instance->setAttribute('name', 'okay');

    expect($instance)
        ->isDirty()
        ->toBeTrue()
        ->getAttribute('name')
        ->toBe('okay');

    $instance->discardChanges();

    expect($instance)
        ->isDirty()
        ->toBeFalse()
        ->getAttribute('name')
        ->toBe('test');
});

it('returns original values', function () {
    $instance = new class(['name' => 'test'])
    {
        use HasAttributes;
    };

    $instance->setAttribute('name', 'okay');

    expect($instance)
        ->getAttribute('name')
        ->toBe('okay')
        ->and($instance)
        ->getOriginal('name')
        ->toBe('test')
        ->getOriginal()
        ->toBe(['name' => 'test'])
        ->getOriginal('not-existing')
        ->toBeNull();
});

it('has macros to manipulate attributes', function () {
    $instance = new class(['name' => 'Bob'])
    {
        use HasAttributes;
    };

    $instance->setName('John')
        ->setAddresses(['one'])
        ->addToAddresses('two')
        ->withReplicas(2);

    expect($instance)
        ->getName()
        ->toBe('John')
        ->getAddresses()
        ->toBe(['one', 'two'])
        ->getOriginalName()
        ->toBe('Bob')
        ->getOriginalAge(20)
        ->toBe(20)
        ->getReplicas()
        ->toBe(2);

    $instance->removeAddresses();

    expect($instance)->getAddresses()->toBeNull();
});

it('can use custom macros', function () {
    $instance = new class(['name' => 'Bob'])
    {
        use HasAttributes;
    };

    $instance::macro('something', fn () => 'Hi');

    expect($instance->something())->toBe('Hi');
});

it('strips only the leading verb from a magic accessor name', function () {
    // `getTargetPort` used to read `tarPort`, `setSubsets` wrote `subs` and every `with`
    // in a name was rewritten: the verb is only ever the prefix.
    expect(Port::http(8080)->getTargetPort())->toBe(8080)
        ->and(Endpoints::make()->setSubsets([['addresses' => []]])->toArray())
        ->toHaveKey('subsets')
        ->not->toHaveKey('subs')
        ->and(Type::make()->withTargetPort(1)->getAttribute('targetPort'))->toBe(1)
        ->and(Type::make()->withWithdrawalDelay(5)->getAttribute('withdrawalDelay'))->toBe(5)
        ->and(Type::make(['unremovable' => 1])->removeUnremovable()->toArray())->toBe([])
        ->and(Type::make()->addToAddToList('a')->getAttribute('addToList'))->toBe(['a'])
        ->and(Type::make(['targetPort' => 1])->setTargetPort(2)->getOriginalTargetPort())->toBe(1);
});

it('serialises a resource without making it dirty', function () {
    // toArray() used to write kind and apiVersion into the resource itself, so a list
    // item (which carries neither) turned dirty from merely being serialised.
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['items' => [['metadata' => ['name' => 'web', 'namespace' => 'default']]]])]);

    $pod = Pod::make()->setCluster(Cluster::make()->url('https://localhost'))->get()->first();

    expect($pod->isDirty())->toBeFalse();

    $array = $pod->toArray();
    $pod->toJson();

    expect($pod->isDirty())->toBeFalse()
        ->and($array['kind'])->toBe('Pod')
        ->and($array['apiVersion'])->toBe('v1')
        ->and($pod->getAttribute('kind'))->toBeNull();
});
