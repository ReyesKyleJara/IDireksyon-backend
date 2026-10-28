<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Services\CatalogPrerequisites;
use App\Services\RoadmapPlanner;

it('keeps informational linked items usable in Inventory and represents missing ones as reference prerequisites', function (string $model) {
    $resident = User::factory()->create();
    $owner = GovernmentId::create(['name' => 'Guided target']);
    $reference = $model::create(['name' => 'Professional credential', 'guidance_mode' => 'informational_only', 'include_in_smart_sequencing' => true]);
    $type = $reference instanceof Document ? 'document' : 'government_id';
    $deeper = GovernmentId::create(['name' => 'Do not insert through reference-only record']);
    $save = app(CatalogPrerequisites::class);
    $save->save($reference, [['type' => 'government_id', 'government_id_id' => $deeper->id]]);
    $save->save($owner, [['type' => $type, $type.'_id' => $reference->id, 'condition' => 'If required']]);
    $group = $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all'])
        ->groups()->create(['title' => 'Accepted credential', 'rule' => 'all', 'condition_type' => 'always']);
    $item = $group->items()->create(['type' => $type, $type.'_id' => $reference->id, 'submission_format' => 'original']);
    $planner = app(RoadmapPlanner::class);
    $plan = $planner->build($resident, [$owner->id]);
    expect(array_column($plan['steps'], 'id'))->toBe([$owner->id])
        ->and($plan['blocked'])->toBeFalse()
        ->and($plan['steps'][0]['prerequisites'][0]['status'])->toBe('missing')
        ->and($plan['steps'][0]['prerequisites'][0]['resolution'])->toBe('reference_only')
        ->and($plan['steps'][0]['prerequisites'][0]['message'])->toContain('issuing institution')
        ->and($plan['steps'][0]['prerequisites'][0]['condition'])->toBe('If required')
        ->and($plan['steps'][0]['prerequisite_readiness']['percentage'])->toBe(0);
    $this->actingAs($resident)->putJson('/api/auth/inventory', [
        'government_id_ids' => $type === 'government_id' ? [$reference->id] : [],
        'document_ids' => $type === 'document' ? [$reference->id] : [],
    ])->assertOk();
    $plan = $planner->build($resident, [$owner->id]);
    expect($plan['steps'][0]['prerequisites'][0]['status'])->toBe('satisfied')
        ->and($plan['steps'][0]['prerequisite_readiness']['percentage'])->toBe(100)
        ->and($item->fresh()->{$type.'_id'})->toBe($reference->id)
        ->and($reference->fresh()->include_in_smart_sequencing)->toBeTrue();
    $this->getJson('/api/government-ids/'.$owner->id)->assertOk()->assertSee('Professional credential');
    $this->getJson('/api/auth/inventory')->assertOk()->assertSee('Professional credential');
    // Switching back retains the dependency relationship and restores guided insertion.
    $resident->ownedGovernmentIds()->detach(); $resident->ownedDocuments()->detach();
    $reference->update(['guidance_mode' => 'guided']);
    $plan = $planner->build($resident, [$owner->id]);
    expect(array_column($plan['steps'], 'name'))->toBe([$deeper->name, $reference->name, $owner->name]);
})->with([[GovernmentId::class], [Document::class]]);

it('does not let reference-only presentation bypass unavailable dependency protection', function (string $availability) {
    $resident = User::factory()->create();
    $owner = GovernmentId::create(['name' => 'Guided target']);
    $reference = Document::create(['name' => 'Reference prerequisite', 'guidance_mode' => 'informational_only', 'application_availability' => $availability]);
    app(CatalogPrerequisites::class)->save($owner, [['type' => 'document', 'document_id' => $reference->id]]);
    $plan = app(RoadmapPlanner::class)->build($resident, [$owner->id]);
    expect($plan['steps'])->toHaveCount(1)->and($plan['blocked'])->toBeTrue()
        ->and($plan['steps'][0]['prerequisites'][0]['status'])->toBe('missing');
    $resident->ownedDocuments()->attach($reference);
    expect(app(RoadmapPlanner::class)->build($resident, [$owner->id])['blocked'])->toBeFalse();
})->with(['temporarily_unavailable', 'discontinued']);

it('handles a previously selected target changed to informational without guiding its acquisition', function () {
    $resident = User::factory()->create();
    $record = GovernmentId::create(['name' => 'Previously selected', 'guidance_mode' => 'informational_only']);
    app(CatalogPrerequisites::class)->save($record, [['type' => 'custom', 'custom_name' => 'Retained prerequisite']]);
    $plan = app(RoadmapPlanner::class)->build($resident, [$record->id]);
    expect($plan['blocked'])->toBeTrue()->and($plan['steps'])->toHaveCount(1)
        ->and($plan['steps'][0]['guidance_mode'])->toBe('informational_only')
        ->and($record->fresh()->prerequisites()->count())->toBe(1);
});

it('retains the sequencing toggle and complete catalog API content across guidance mode changes', function () {
    $staff = User::factory()->create(['role' => 'researcher']);
    $record = GovernmentId::create(['name' => 'Retained record', 'description' => 'Factual description', 'requirements' => 'Retained requirement',
        'application_process' => 'Retained guide', 'fee' => 'Retained fee', 'include_in_smart_sequencing' => true]);
    app(CatalogPrerequisites::class)->save($record, [['type' => 'custom', 'custom_name' => 'Retained preparation']]);
    $before = $this->getJson('/api/government-ids/'.$record->id)->assertOk()->json('data');
    foreach (['informational_only', 'guided'] as $mode) {
        $this->actingAs($staff)->post(route('admin.government-ids.edit-wizard.save', [$record, 'basic']),
            ['name' => $record->name, 'guidance_mode' => $mode])->assertSessionHasNoErrors();
        $after = $this->getJson('/api/government-ids/'.$record->id)->assertOk()->json('data');
        unset($after['guidance_mode']); $expected = $before; unset($expected['guidance_mode']);
        expect($after)->toBe($expected)->and($record->fresh()->include_in_smart_sequencing)->toBeTrue();
        $this->get(route('admin.government-ids.edit-wizard.show', [$record, 'requirements']))->assertOk()->assertSee('Retained preparation');
    }
});
