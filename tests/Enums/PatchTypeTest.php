<?php

declare(strict_types=1);

use RoundlyConsulting\Enums\Helpers;
use RoundlyConsulting\KubernetesApi\Enums\PatchType;

it('adopts the shared enum helpers trait', function () {
    expect(class_uses(PatchType::class))->toContain(Helpers::class);
});

it('keeps the wire content-type on the backing value', function () {
    expect(PatchType::StrategicMerge->value)->toBe('application/strategic-merge-patch+json')
        ->and(PatchType::Merge->value)->toBe('application/merge-patch+json')
        ->and(PatchType::Json->value)->toBe('application/json-patch+json')
        ->and(PatchType::Apply->value)->toBe('application/apply-patch+yaml');
});

it('returns the raw content-type per case', function (PatchType $type, string $expected) {
    expect($type->contentType())->toBe($expected);
})->with([
    [PatchType::StrategicMerge, 'application/strategic-merge-patch+json'],
    [PatchType::Merge, 'application/merge-patch+json'],
    [PatchType::Json, 'application/json-patch+json'],
    [PatchType::Apply, 'application/apply-patch+yaml'],
]);

it('exposes every backing value through values()', function () {
    expect(PatchType::values()->all())->toBe([
        'application/strategic-merge-patch+json',
        'application/merge-patch+json',
        'application/json-patch+json',
        'application/apply-patch+yaml',
    ]);
});

it('builds a validation rule from the wire values', function () {
    expect(PatchType::validationRule())->toBe(
        'in:application/strategic-merge-patch+json,application/merge-patch+json,application/json-patch+json,application/apply-patch+yaml'
    );
});

it('labels cases from the name, not the mime value', function (PatchType $type, string $label) {
    expect($type->readable())->toBe($label)
        ->and($type->label())->toBe($label);
})->with([
    [PatchType::StrategicMerge, 'Strategic Merge'],
    [PatchType::Merge, 'Merge'],
    [PatchType::Json, 'Json'],
    [PatchType::Apply, 'Apply'],
]);

it('lists clean name-based labels', function () {
    expect(PatchType::labels()->all())->toBe(['Strategic Merge', 'Merge', 'Json', 'Apply']);
});

it('maps wire values to clean labels for select inputs', function () {
    expect(PatchType::toOptions()->all())->toBe([
        'application/strategic-merge-patch+json' => 'Strategic Merge',
        'application/merge-patch+json' => 'Merge',
        'application/json-patch+json' => 'Json',
        'application/apply-patch+yaml' => 'Apply',
    ]);
});

it('exposes value/label/name option dtos', function () {
    $first = PatchType::options()->first();

    expect($first->value)->toBe('application/strategic-merge-patch+json')
        ->and($first->label)->toBe('Strategic Merge')
        ->and($first->name)->toBe('StrategicMerge');
});

it('resolves cases by name and clean label', function () {
    expect(PatchType::fromName('Merge'))->toBe(PatchType::Merge)
        ->and(PatchType::tryFromName('Nope'))->toBeNull()
        ->and(PatchType::fromLabel('Strategic Merge'))->toBe(PatchType::StrategicMerge);
});
