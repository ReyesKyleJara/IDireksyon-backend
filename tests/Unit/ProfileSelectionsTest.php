<?php

use App\Support\ProfileSelections;

it('normalizes profile choices deterministically without changing their Government ID keys', function () {
    $selections = new ProfileSelections([42 => 18, 35 => 11]);
    expect($selections->all())->toBe([35 => 11, 42 => 18])
        ->and($selections->forGovernmentId(42))->toBe(18)
        ->and($selections->forGovernmentId(999))->toBeNull();
    $updated = $selections->withSelection(90, 20);
    expect($updated->all())->toBe([35 => 11, 42 => 18, 90 => 20])
        ->and($selections->all())->toBe([35 => 11, 42 => 18]);
    $copy = $selections->all();
    $copy[35] = 999;
    expect($selections->forGovernmentId(35))->toBe(11)
        ->and($selections->withSelection(35, 11)->all())->toBe($selections->all());
});

it('rejects ambiguous keys and invalid profile value types', function (array $map) {
    expect(fn () => new ProfileSelections($map))->toThrow(InvalidArgumentException::class);
})->with([
    [[0 => 11]], [[-1 => 11]], [['035' => 11]], [['+35' => 11]], [['35.0' => 11]],
    [['35e0' => 11]], [[' 35' => 11]], [['35\n' => 11]], [['not-an-id' => 11]],
    [[(string) PHP_INT_MAX.'0' => 11]], [[35 => '11']], [[35 => true]], [[35 => 11.5]],
    [[35 => null]], [[35 => 0]], [[35 => -1]], [[35 => []]],
]);

it('rejects conflicting updates and oversized maps without changing the original object', function () {
    $selections = new ProfileSelections([35 => 11]);
    expect(fn () => $selections->withSelection(35, 12))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ProfileSelections(array_fill_keys(range(1, 501), 11)))->toThrow(InvalidArgumentException::class)
        ->and($selections->all())->toBe([35 => 11]);
});
