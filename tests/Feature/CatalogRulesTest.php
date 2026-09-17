<?php

use App\Models\CatalogFee;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Requirement;
use App\Models\RequirementGroup;
use App\Models\User;
use App\Services\CatalogDefinition;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

function rulesCatalogModel(string $resource): string
{
    return $resource === 'documents' ? Document::class : GovernmentId::class;
}

function reviewedCatalogPayload(): array
{
    return ['name' => 'Test catalog entry', 'agency' => 'Test agency', 'research_status' => 'verified',
        'availability_status' => 'available', 'source_url' => 'https://example.org/test-only',
        'source_checked_at' => now()->toDateString(), 'is_published' => 1, 'requirements_reviewed' => 1];
}

it('manages reusable requirement groups for both directories and preserves alternatives', function (string $resource) {
    $record = rulesCatalogModel($resource)::create(['name' => 'Test entry', 'agency' => 'Test agency', 'cost_notes' => 'Original fee notes']);
    $url = '/admin/'.$resource.'/'.$record->id;
    foreach (['all', 'any', 'at_least'] as $match) {
        $this->post($url.'/groups', ['name' => 'Test '.$match, 'match_rule' => $match, 'minimum_count' => 2])->assertSessionHasNoErrors();
        $group = $record->requirementGroups()->reorder('id', 'desc')->firstOrFail();
        foreach (['First option', 'Second option'] as $name) {
            $this->post($url.'/requirements', ['type' => 'custom', 'name' => $name, 'requirement_group_id' => $group->id])->assertSessionHasNoErrors();
        }
    }
    $this->put($url, reviewedCatalogPayload())->assertSessionHasNoErrors();
    $rules = app(CatalogDefinition::class)->rules($record->fresh());
    expect(array_column($rules, 'match'))->toBe(['all', 'any', 'at_least'])
        ->and(array_column($rules, 'minimum'))->toBe([2, 1, 2])
        ->and($record->fresh()->cost_notes)->toBe('Original fee notes');
    $this->get($url.'/edit')->assertOk()->assertSee('Requirement groups')->assertSee('Fee breakdown')->assertSee('Reviewed');
    $this->get($url)->assertOk()->assertSee('Any one of these')->assertSee('At least 2');
    $group = $record->requirementGroups()->first();
    $this->put($url.'/groups/'.$group->id, ['name' => 'Changed group', 'match_rule' => 'all'])->assertSessionHasNoErrors();
    expect($record->fresh()->requirements_reviewed)->toBeFalse();
})->with(['government-ids', 'documents']);

it('keeps legacy flat requirements untouched and blocks premature publication or review', function () {
    $record = Document::create(['name' => 'Legacy entry']);
    $requirement = Requirement::create(['name' => 'Unreviewed legacy requirement', 'type' => 'custom']);
    $record->requirements()->attach($requirement);
    $url = '/admin/documents/'.$record->id;
    expect($record->fresh()->is_published)->toBeFalse()->and($record->fresh()->requirements_reviewed)->toBeFalse();
    $this->put($url, ['name' => 'Should roll back', 'is_published' => 1])->assertSessionHasErrors('is_published');
    expect($record->fresh()->name)->toBe('Legacy entry');
    $this->put($url, reviewedCatalogPayload())->assertSessionHasErrors('requirements_reviewed');
    expect($record->requirements()->first()->requirement_group_id)->toBeNull();
    $this->get($url.'/edit')->assertSee('Ungrouped')->assertSee('Unreviewed legacy requirement');
    expect(fn () => app(CatalogDefinition::class)->rules($record->fresh()))->toThrow(DomainException::class);
});

it('rejects impossible minimums and manual conditions when approving rules', function () {
    $record = Document::create(['name' => 'Test entry']);
    $url = '/admin/documents/'.$record->id;
    $this->post($url.'/groups', ['name' => 'Test minimum', 'match_rule' => 'at_least', 'minimum_count' => 2])->assertSessionHasNoErrors();
    $group = $record->requirementGroups()->first();
    $this->post($url.'/requirements', ['type' => 'custom', 'name' => 'Only option', 'requirement_group_id' => $group->id]);
    $this->put($url, reviewedCatalogPayload())->assertSessionHasErrors('requirements_reviewed');
    $this->put($url.'/groups/'.$group->id, ['name' => 'Conditional', 'match_rule' => 'all', 'condition_notes' => 'Only for a certain applicant group']);
    $this->put($url, reviewedCatalogPayload())->assertSessionHasErrors('requirements_reviewed');
    $this->put($url.'/groups/'.$group->id, ['name' => 'Unconditional', 'match_rule' => 'all', 'condition_notes' => null]);
    $this->put($url, reviewedCatalogPayload())->assertSessionHasNoErrors();
    expect(app(CatalogDefinition::class)->rules($record->fresh()))->toHaveCount(1);
});

it('isolates group and fee ownership and prevents duplicate requirements and invalid dependencies', function (string $resource) {
    $record = rulesCatalogModel($resource)::create(['name' => 'Test entry', 'agency' => 'Test agency']);
    $other = rulesCatalogModel($resource)::create(['name' => 'Other entry', 'agency' => 'Test agency']);
    $group = $other->requirementGroups()->create(['name' => 'Foreign group', 'match_rule' => 'all']);
    $fee = $other->fees()->create(['name' => 'Foreign fee', 'kind' => 'required']);
    $url = '/admin/'.$resource.'/'.$record->id;
    $this->post($url.'/requirements', ['type' => 'custom', 'name' => 'Option', 'requirement_group_id' => $group->id])->assertSessionHasErrorsIn('requirement', ['requirement_group_id']);
    $this->put($url.'/groups/'.$group->id, ['name' => 'Stolen', 'match_rule' => 'all'])->assertNotFound();
    $this->delete($url.'/groups/'.$group->id)->assertNotFound();
    $this->put($url.'/fees/'.$fee->id, ['name' => 'Stolen', 'kind' => 'required'])->assertNotFound();
    $this->delete($url.'/fees/'.$fee->id)->assertNotFound();
    $this->post($url.'/requirements', ['type' => 'custom', 'name' => 'Appearance', 'is_dependency' => 1])->assertSessionHasErrorsIn('requirement', ['is_dependency']);
    $this->post($url.'/requirements', ['type' => 'custom', 'name' => 'Appearance'])->assertSessionHasNoErrors();
    $this->post($url.'/requirements', ['type' => 'custom', 'name' => 'appearance'])->assertSessionHasErrorsIn('requirement', ['requirement_group_id']);
    expect($record->requirements()->count())->toBe(1);
})->with(['government-ids', 'documents']);

it('edits legacy shared requirements without changing other applications and clears review on removal', function () {
    $record = Document::create(['name' => 'First']);
    $other = Document::create(['name' => 'Second']);
    $shared = Requirement::create(['name' => 'Shared item', 'type' => 'custom', 'description' => 'Original notes']);
    $record->requirements()->attach($shared);
    $other->requirements()->attach($shared);
    $group = $record->requirementGroups()->create(['name' => 'Basics', 'match_rule' => 'all']);
    $url = '/admin/documents/'.$record->id;
    $this->put($url.'/requirements/'.$shared->id, ['name' => 'New item name', 'description' => 'New notes', 'requirement_group_id' => $group->id])->assertSessionHasNoErrors();
    expect($other->requirements()->first()->description)->toBe('Original notes')
        ->and($record->requirements()->first()->description)->toBe('New notes');
    $this->put($url, reviewedCatalogPayload())->assertSessionHasNoErrors();
    $this->delete($url.'/groups/'.$group->id)->assertSessionHas('error');
    expect(RequirementGroup::find($group->id))->not->toBeNull();
    $this->delete($url.'/requirements/'.$record->requirements()->first()->id)->assertSessionHasNoErrors();
    expect($record->fresh()->requirements_reviewed)->toBeFalse();
    $this->delete($url.'/groups/'.$group->id)->assertSessionHasNoErrors();
    expect(RequirementGroup::find($group->id))->toBeNull()->and($other->requirements()->count())->toBe(1);
});

it('exports linked prerequisites by stable reference rather than name', function () {
    $record = GovernmentId::create(['name' => 'Test ID', 'agency' => 'Test agency']);
    $document = Document::create(['name' => 'Test prerequisite']);
    $group = $record->requirementGroups()->create(['name' => 'Basics', 'match_rule' => 'all']);
    $url = '/admin/government-ids/'.$record->id;
    $this->post($url.'/requirements', ['type' => 'document', 'referenced_document_id' => $document->id, 'requirement_group_id' => $group->id, 'is_dependency' => 1])->assertSessionHasNoErrors();
    $this->put($url, reviewedCatalogPayload())->assertSessionHasNoErrors();
    $document->update(['name' => 'Renamed prerequisite']);
    $option = app(CatalogDefinition::class)->rules($record->fresh())[0]['options'][0];
    expect($option['reference_id'])->toBe($document->id)->and($option['obtain_first'])->toBeTrue()->and($option['name'])->toBe('Renamed prerequisite');
});

it('stores unknown and zero fees distinctly and keeps alternative charges separate', function (string $resource) {
    $record = rulesCatalogModel($resource)::create(['name' => 'Test entry', 'agency' => 'Test agency']);
    $url = '/admin/'.$resource.'/'.$record->id;
    foreach ([
        ['name' => 'Unresearched', 'kind' => 'required', 'amount' => null],
        ['name' => 'Confirmed free', 'kind' => 'required', 'amount' => 0],
        ['name' => 'Delivery', 'kind' => 'optional', 'amount' => '10.00'],
        ['name' => 'Choice A', 'kind' => 'alternative', 'amount' => '20.50', 'choice_group' => 'Processing'],
        ['name' => 'Choice B', 'kind' => 'alternative', 'amount' => '30.50', 'choice_group' => 'Processing'],
    ] as $payload) {
        $this->post($url.'/fees', $payload)->assertSessionHasNoErrors();
    }
    $fees = app(CatalogDefinition::class)->feeChoices($record->fresh());
    expect($fees['required'][0]['amount'])->toBeNull()->and($fees['required'][1]['amount'])->toBe('0.00')
        ->and($fees['optional'])->toHaveCount(1)->and($fees['choose_one'])->toHaveCount(1)
        ->and($fees['choose_one'][0]['options'])->toHaveCount(2)->and($fees)->not->toHaveKey('total');
    $this->post($url.'/fees', ['name' => 'Invalid', 'kind' => 'alternative', 'amount' => -1])->assertSessionHasErrorsIn('fees', ['amount', 'choice_group']);
    $this->get($url)->assertOk()->assertSee('Unknown')->assertSee('₱0.00')->assertSee('Processing');
    $fee = $record->fees()->first();
    $this->put($url.'/fees/'.$fee->id, ['name' => 'Now researched', 'kind' => 'optional', 'amount' => 12])->assertSessionHasNoErrors();
    expect($fee->fresh()->amount)->toBe('12.00');
    $this->delete($url.'/fees/'.$fee->id)->assertSessionHasNoErrors();
    expect(CatalogFee::find($fee->id))->toBeNull();
})->with(['government-ids', 'documents']);

it('requires explicit approval and distinguishes directory visibility from new issuance', function () {
    $record = Document::create(['name' => 'Draft']);
    $url = '/admin/documents/'.$record->id;
    expect(Document::forDirectory()->count())->toBe(0);
    $this->put($url, array_merge(reviewedCatalogPayload(), ['requirements_reviewed' => 0, 'availability_status' => 'legacy']))->assertSessionHasNoErrors();
    expect(Document::forDirectory()->count())->toBe(1);
    expect(fn () => app(CatalogDefinition::class)->rules($record->fresh()))->toThrow(DomainException::class);
    $this->put($url, reviewedCatalogPayload())->assertSessionHasNoErrors();
    // Empty sets are usable only after an explicit, sourced confirmation; never inferred from no rows.
    expect(app(CatalogDefinition::class)->rules($record->fresh()))->toBe([]);
    $this->put($url, array_merge(reviewedCatalogPayload(), ['is_published' => 0, 'requirements_reviewed' => 0]))->assertSessionHasNoErrors();
    expect(Document::forDirectory()->count())->toBe(0);
});

it('protects all new writes from resident and unauthenticated access', function () {
    $record = Document::create(['name' => 'Test entry']);
    $this->actingAs(User::factory()->create(['role' => 'resident']));
    foreach (['groups' => ['name' => 'Blocked', 'match_rule' => 'all'], 'fees' => ['name' => 'Blocked', 'kind' => 'required']] as $path => $payload) {
        $this->post('/admin/documents/'.$record->id.'/'.$path, $payload)->assertForbidden();
    }
    expect(RequirementGroup::count())->toBe(0)->and(CatalogFee::count())->toBe(0);
    auth()->logout();
    $this->post('/admin/documents/'.$record->id.'/groups', ['name' => 'Blocked', 'match_rule' => 'all'])->assertRedirect('/login');
});
