<?php

use App\Support\ApplicantContext;

it('normalizes owned references without confusing IDs and documents', function () {
    $context = new ApplicantContext(18, 'new', [50, 36, 50], [7]);
    expect($context->governmentIdIds)->toBe([36, 50])
        ->and($context->hasGovernmentId(36))->toBeTrue()
        ->and($context->hasDocument(36))->toBeFalse()
        ->and($context->hasDocument(7))->toBeTrue();
});

it('preserves unknown applicant facts', function () {
    $context = new ApplicantContext;
    expect($context->age)->toBeNull()->and($context->applicationType)->toBeNull();
});

it('rejects invalid context values', function (array $arguments) {
    expect(fn () => new ApplicantContext(...$arguments))->toThrow(InvalidArgumentException::class);
})->with([
    [[-1]], [[65536]], [[18, 'invented']],
    [[18, 'new', [0]]], [[18, 'new', ['36']]], [[18, 'new', [], [-1]]],
]);
