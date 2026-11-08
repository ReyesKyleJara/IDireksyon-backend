<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Services\CatalogPrerequisites;
use App\Services\RoadmapPlanner;
use Illuminate\Support\Facades\DB;

function roadmapLink($record): array
{
    $type = $record instanceof Document ? 'document' : 'government_id';

    return ['type' => $type, $type.'_id' => $record->id];
}

function roadmapSteps(array $plan): array
{
    return array_map(fn ($row) => $row['type'].':'.$row['id'], $plan['steps']);
}

it('orders dependencies using catalog type and ID and updates from saved Inventory only', function () {
    $resident = User::factory()->create();
    $a = GovernmentId::create(['name' => 'Target A']);
    $b = Document::create(['name' => 'Document B', 'include_in_smart_sequencing' => false]);
    $c = GovernmentId::create(['name' => 'ID C', 'include_in_smart_sequencing' => false]);
    $unrelated = Document::create(['name' => 'ID C']);
    $save = app(CatalogPrerequisites::class);
    $save->save($a, [roadmapLink($b), ['type' => 'custom', 'custom_name' => 'Training', 'condition' => 'If required']]);
    $save->save($b, [roadmapLink($c)]);
    $this->actingAs($resident);
    $request = fn () => $this->postJson('/api/auth/roadmap-plan', [
        'target_government_id_ids' => [$a->id], 'owned_items' => ['ID C'],
        'government_id_ids' => [$c->id], 'document_ids' => [$b->id],
    ])->assertOk()->json('data');
    $plan = $request();
    expect(roadmapSteps($plan))->toBe(['government_id:'.$c->id, 'document:'.$b->id, 'government_id:'.$a->id])
        ->and($plan['target_government_id_ids'])->toBe([$a->id])->and($plan['blocked'])->toBeFalse();
    $target = $plan['steps'][2];
    expect($target['prerequisite_readiness'])->toBe(['satisfied' => 0, 'total' => 1, 'percentage' => 0, 'manual' => 1])
        ->and($target['prerequisites'][1]['status'])->toBe('manual')
        ->and($target['prerequisites'][1]['condition'])->toBe('If required');
    expect($resident->ownedGovernmentIds()->count())->toBe(0)->and($resident->ownedDocuments()->count())->toBe(0);
    $this->putJson('/api/auth/inventory', ['government_id_ids' => [$c->id], 'document_ids' => [$unrelated->id]])->assertOk();
    $plan = $request();
    expect(roadmapSteps($plan))->toBe(['document:'.$b->id, 'government_id:'.$a->id])
        ->and($plan['steps'][0]['prerequisite_readiness']['percentage'])->toBe(100);
    $this->putJson('/api/auth/inventory', ['government_id_ids' => [$c->id], 'document_ids' => [$b->id]])->assertOk();
    $plan = $request();
    expect(roadmapSteps($plan))->toBe(['government_id:'.$a->id])
        ->and($plan['steps'][0]['prerequisite_readiness']['percentage'])->toBe(100);
});

it('supports all edge types and deduplicates shared dependencies deterministically', function () {
    $resident = User::factory()->create();
    $a = GovernmentId::create(['name' => 'A']);
    $b = GovernmentId::create(['name' => 'B']);
    $c = GovernmentId::create(['name' => 'C']);
    $d = Document::create(['name' => 'D']);
    $e = Document::create(['name' => 'E']);
    $save = app(CatalogPrerequisites::class);
    $save->save($a, [roadmapLink($c), roadmapLink($d)]);
    $save->save($b, [roadmapLink($c)]);
    $save->save($d, [roadmapLink($e)]);
    $save->save($e, [roadmapLink($c)]);
    $planner = app(RoadmapPlanner::class);
    $plan = $planner->build($resident, [$a->id, $b->id]);
    // B needs fewer additional steps than A, so it comes first.
    expect(roadmapSteps($plan))->toBe(['government_id:'.$c->id, 'government_id:'.$b->id, 'document:'.$e->id, 'document:'.$d->id, 'government_id:'.$a->id])
        ->and($planner->build($resident, [$a->id, $b->id]))->toBe($plan);
    $resident->ownedGovernmentIds()->attach($c);
    $plan = $planner->build($resident, [$a->id, $b->id]);
    expect($plan['steps'][3]['prerequisite_readiness'])->toBe(['satisfied' => 1, 'total' => 2, 'percentage' => 50, 'manual' => 0]);
});

it('keeps unavailable dependencies visible and blocks their dependent paths', function (string $availability) {
    $resident = User::factory()->create();
    $a = GovernmentId::create(['name' => 'A']);
    $b = Document::create(['name' => 'B']);
    $c = GovernmentId::create(['name' => 'C', 'application_availability' => $availability]);
    app(CatalogPrerequisites::class)->save($a, [roadmapLink($b)]);
    app(CatalogPrerequisites::class)->save($b, [roadmapLink($c)]);
    $plan = app(RoadmapPlanner::class)->build($resident, [$a->id]);
    expect($plan['blocked'])->toBeTrue()->and(array_column($plan['steps'], 'blocked'))->toBe([true, true, true])
        ->and($plan['steps'][1]['prerequisite_readiness']['percentage'])->toBe(0);
    // Availability does not invalidate Inventory ownership of an already-held credential.
    $resident->ownedGovernmentIds()->attach($c);
    expect(app(RoadmapPlanner::class)->build($resident, [$a->id])['blocked'])->toBeFalse();
})->with(['temporarily_unavailable', 'discontinued']);

it('fails safely on cyclic legacy data without dropping selected targets', function () {
    $resident = User::factory()->create();
    $a = GovernmentId::create(['name' => 'A']);
    $b = Document::create(['name' => 'B']);
    app(CatalogPrerequisites::class)->save($a, [roadmapLink($b)]);
    DB::table('catalog_document_prerequisites')->insert([
        'owner_id' => $b->id, 'type' => 'government_id', 'government_id_id' => $a->id, 'sort_order' => 0,
    ]);
    $plan = app(RoadmapPlanner::class)->build($resident, [$a->id]);
    expect($plan['blocked'])->toBeTrue()->and($plan['steps'])->toHaveCount(2)
        ->and($plan['target_government_id_ids'])->toBe([$a->id])
        ->and(implode(' ', $plan['steps'][1]['issues']))->toContain('Circular prerequisite');
});

it('excludes manual prerequisites from numeric readiness and handles empty prerequisites', function () {
    $resident = User::factory()->create();
    $a = GovernmentId::create(['name' => 'A']);
    $planner = app(RoadmapPlanner::class);
    expect($planner->build($resident, [$a->id])['steps'][0]['prerequisite_readiness']['percentage'])->toBeNull();
    app(CatalogPrerequisites::class)->save($a, [['type' => 'custom', 'custom_name' => 'Complete course']]);
    $plan = $planner->build($resident, [$a->id]);
    expect($plan['steps'])->toHaveCount(1)
        ->and($plan['steps'][0]['prerequisite_readiness'])->toBe(['satisfied' => 0, 'total' => 0, 'percentage' => null, 'manual' => 1]);
});

it('protects the resident plan API and validates selected Government IDs', function () {
    $this->postJson('/api/auth/roadmap-plan', [])->assertUnauthorized();
    foreach ([['role' => 'researcher'], ['role' => 'resident', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes))->postJson('/api/auth/roadmap-plan', [])->assertForbidden();
    }
    $this->actingAs(User::factory()->create());
    foreach ([[], [999999], [1, 1], ['wrong']] as $targets) {
        $this->postJson('/api/auth/roadmap-plan', ['target_government_id_ids' => $targets])->assertUnprocessable();
    }
});

it('accepts document targets and keeps same-number government IDs distinct', function () {
    $resident = User::factory()->create();
    $id = GovernmentId::create(['name' => 'ID target']);
    $document = Document::forceCreate(['id' => $id->id, 'name' => 'Document target']);
    $this->actingAs($resident);
    $plan = $this->postJson('/api/auth/roadmap-plan', [
        'target_document_ids' => [$document->id],
    ])->assertOk()->json('data');
    expect($plan['target_government_id_ids'])->toBe([])
        ->and($plan['target_document_ids'])->toBe([$document->id])
        ->and(roadmapSteps($plan))->toBe(['document:'.$document->id])
        ->and($plan['steps'][0]['selected'])->toBeTrue();
    $mixed = $this->postJson('/api/auth/roadmap-plan', [
        'target_government_id_ids' => [$id->id],
        'target_document_ids' => [$document->id],
    ])->assertOk()->json('data');
    expect(roadmapSteps($mixed))->toBe(['document:'.$document->id, 'government_id:'.$id->id]);
});

it('rejects empty or invalid document targets', function () {
    $this->actingAs(User::factory()->create());
    foreach ([[], ['target_government_id_ids' => [], 'target_document_ids' => []],
        ['target_document_ids' => [999999]], ['target_document_ids' => ['invalid']]] as $payload) {
        $this->postJson('/api/auth/roadmap-plan', $payload)->assertUnprocessable();
    }
});

it('derives the Passport to PSA Birth Certificate sequence from structured Requirements', function () {
    $resident = User::factory()->create();
    $passport = GovernmentId::forceCreate(['id' => 55, 'name' => 'Philippine Passport']);
    $birth = Document::forceCreate(['id' => 28, 'name' => 'PSA Birth Certificate']);
    $set = $passport->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $group = $set->groups()->create(['rule' => 'all']);
    $way = $group->ways()->create(['required_count' => 1]);
    $item = $group->items()->make(['type' => 'document', 'document_id' => $birth->id]);
    $item->requirement_way_id = $way->id;
    $item->save();
    $this->actingAs($resident);
    $payload = ['target_government_id_ids' => [55]];
    $plan = $this->postJson('/api/auth/roadmap-plan', $payload)->assertOk()->json('data');
    expect(roadmapSteps($plan))->toBe(['document:28', 'government_id:55'])
        ->and($plan['blocked'])->toBeFalse();
    $resident->ownedDocuments()->attach($birth);
    $owned = $this->postJson('/api/auth/roadmap-plan', $payload)->assertOk()->json('data');
    expect(roadmapSteps($owned))->toBe(['government_id:55'])
        ->and($owned['steps'][0]['prerequisites'][0]['status'])->toBe('satisfied');
});
