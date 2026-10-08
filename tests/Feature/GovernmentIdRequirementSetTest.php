<?php

use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementSet;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

it('keeps sets separate by ID and preserves existing API fields', function () {
    $id = GovernmentId::create([
        'name' => 'Example ID', 'requirements' => 'Existing researched requirements.',
        'prerequisite_notes' => 'Existing guidance.',
    ]);
    $other = GovernmentId::create(['name' => 'Other ID']);
    $beforeList = $this->getJson('/api/government-ids')->assertOk()->json();
    $beforeDetail = \Illuminate\Support\Arr::except($this->getJson('/api/government-ids/'.$id->id)->assertOk()->json('data'), ['requirement_sets']);
    expect($id->requirementSets()->count())->toBe(0);

    $adult = $id->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $id->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'minor']);

    expect($id->requirementSets()->count())->toBe(2)
        ->and($other->requirementSets()->count())->toBe(0)
        ->and($adult->governmentId->is($id))->toBeTrue()
        ->and($adult->display_label)->toBe('Adult • First-Time Application');
    expect($this->getJson('/api/government-ids')->assertOk()->json())->toBe($beforeList)
        ->and(\Illuminate\Support\Arr::except($this->getJson('/api/government-ids/'.$id->id)->assertOk()->json('data'), ['requirement_sets']))->toBe($beforeDetail);
});

it('normalizes preset ages and clears stale custom labels on updates', function () {
    $id = GovernmentId::create(['name' => 'Example ID']);
    $set = $id->requirementSets()->create([
        'application_type' => 'custom', 'application_type_custom' => ' Special application ',
        'applicant_type' => 'custom', 'applicant_type_custom' => ' Students ',
        'min_age' => 12, 'max_age' => 21,
    ]);
    expect($set->fresh()->display_label)->toBe('Students (Ages 12–21) • Special application');

    $set->update(['application_type' => 'new', 'applicant_type' => 'adult', 'min_age' => 3, 'max_age' => 15]);
    $set->refresh();
    expect($set->min_age)->toBe(18)->and($set->max_age)->toBeNull()
        ->and($set->application_type_custom)->toBeNull()->and($set->applicant_type_custom)->toBeNull();

    $set->update(['applicant_type' => 'minor']);
    expect($set->fresh()->min_age)->toBeNull()->and($set->fresh()->max_age)->toBe(17);
    $set->update(['applicant_type' => 'all']);
    expect($set->fresh()->min_age)->toBeNull()->and($set->fresh()->max_age)->toBeNull();
});

it('supports custom age boundaries without requiring a custom label', function ($min, $max, string $label) {
    $id = GovernmentId::create(['name' => 'Example ID']);
    $set = $id->requirementSets()->create([
        'application_type' => 'replacement', 'applicant_type' => 'custom',
        'min_age' => $min, 'max_age' => $max,
    ])->fresh();
    expect($set->min_age)->toBe($min)->and($set->max_age)->toBe($max)
        ->and($set->display_label)->toBe($label.' • Replacement');
})->with([
    [null, null, 'No age restriction'],
    [0, 0, 'Ages 0–0'],
    [21, null, 'Age 21 and above'],
    [null, 12, 'Age 12 and below'],
    [12, 17, 'Ages 12–17'],
]);

it('rejects invalid set data before persistence', function (array $fields) {
    $id = GovernmentId::create(['name' => 'Example ID']);
    expect(fn () => $id->requirementSets()->create(array_replace([
        'application_type' => 'new', 'applicant_type' => 'custom',
    ], $fields)))->toThrow(ValidationException::class);
    expect(GovernmentIdRequirementSet::count())->toBe(0);
})->with([
    [['application_type' => 'invalid']],
    [['applicant_type' => 'invalid']],
    [['application_type' => 'custom', 'application_type_custom' => '   ']],
    [['application_type' => null]],
    [['applicant_type' => null]],
    [['min_age' => -1]],
    [['max_age' => -1]],
    [['min_age' => 18.5]],
    [['min_age' => '18 years old']],
    [['min_age' => 25, 'max_age' => 18]],
    [['max_age' => 65536]],
]);

it('leaves a saved set unchanged when an invalid update is rejected', function () {
    $id = GovernmentId::create(['name' => 'Example ID']);
    $set = $id->requirementSets()->create([
        'application_type' => 'new', 'applicant_type' => 'custom', 'min_age' => 18,
    ]);
    expect(fn () => $set->update(['max_age' => 12]))->toThrow(ValidationException::class);
    expect($set->fresh()->min_age)->toBe(18)->and($set->fresh()->max_age)->toBeNull();
});

it('requires an existing parent ID', function () {
    expect(fn () => GovernmentIdRequirementSet::create([
        'government_id_id' => 999999, 'application_type' => 'new', 'applicant_type' => 'all',
    ]))->toThrow(QueryException::class);
});

it('deletes only a selected set and cascades sets when their parent is deleted', function () {
    $id = GovernmentId::create(['name' => 'Example ID']);
    $other = GovernmentId::create(['name' => 'Other ID']);
    $first = $id->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $second = $id->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'adult']);
    $unrelated = $other->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $first->delete();
    $this->assertModelExists($id);
    $this->assertModelExists($second);
    $id->delete();
    $this->assertModelMissing($second);
    $this->assertModelExists($other);
    $this->assertModelExists($unrelated);
});
