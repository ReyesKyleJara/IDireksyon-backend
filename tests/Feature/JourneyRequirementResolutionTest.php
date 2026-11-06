<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Services\CatalogPrerequisites;
use App\Services\RoadmapPlanner;
use Illuminate\Support\Facades\DB;

function journeySet($owner, array $data = [])
{
    return $owner->requirementSets()->create([...['application_type' => 'new', 'applicant_type' => 'all'], ...$data]);
}

function journeyGroup($set, array $ways, array $data = [])
{
    $group = $set->groups()->create([...['rule' => 'all', 'title' => 'Accepted evidence'], ...$data]);
    foreach ($ways as $spec) {
        $way = $group->ways()->create(['required_count' => $spec['count'] ?? 1, 'qualification_type' => $spec['qualification'] ?? 'none']);
        foreach ($spec['items'] as $item) {
            $link = is_string($item) ? ['type' => 'custom', 'custom_name' => $item]
                : ($item instanceof Document ? ['type' => 'document', 'document_id' => $item->id] : ['type' => 'government_id', 'government_id_id' => $item->id]);
            $saved = $group->items()->make($link);
            $saved->requirement_way_id = $way->id;
            $saved->save();
        }
    }

    return $group;
}

function journeyPending(array $plan): array
{
    return array_values(array_filter($plan['steps'], fn ($step) => $step['type'] === 'unresolved_requirement'));
}

function journeyChoose(array $step, array $option): array
{
    return ['key' => $step['resolution_key'], ...array_intersect_key($option, array_flip(['type', 'id', 'way_id', 'set_id']))];
}

it('resolves arbitrary targets from CMS links without using names or fixed catalog IDs', function ($model) {
    $resident = User::factory()->create();
    $target = $model::create(['name' => 'Arbitrary target']);
    $document = Document::create(['name' => 'Evidence']);
    journeyGroup(journeySet($target), [['items' => [$document]]]);
    $plan = app(RoadmapPlanner::class)->build($resident, $target instanceof GovernmentId ? [$target->id] : [], $target instanceof Document ? [$target->id] : []);
    expect(array_column($plan['steps'], 'name'))->toBe(['Evidence', 'Arbitrary target']);
    $resident->ownedDocuments()->attach($document);
    $plan = app(RoadmapPlanner::class)->build($resident, $target instanceof GovernmentId ? [$target->id] : [], $target instanceof Document ? [$target->id] : []);
    expect(array_column($plan['steps'], 'name'))->toBe(['Arbitrary target']);
})->with([[GovernmentId::class], [Document::class]]);

it('validates the complete Passport case and persists choices without changing ownership or CMS content', function () {
    $resident = User::factory()->create();
    $passport = GovernmentId::create(['name' => 'Philippine Passport']);
    $birth = Document::create(['name' => 'PSA Birth Certificate']);
    $accepted = GovernmentId::create(['name' => 'Accepted guided ID']);
    $reference = GovernmentId::create(['name' => 'Accepted reference ID', 'guidance_mode' => 'informational_only']);
    $off = GovernmentId::create(['name' => 'Accepted excluded ID', 'include_in_smart_sequencing' => false]);
    $unavailable = GovernmentId::create(['name' => 'Accepted unavailable ID', 'application_availability' => 'temporarily_unavailable']);
    $conditional = GovernmentId::create(['name' => 'Accepted conditional ID', 'application_availability' => 'limited_conditional']);
    $unrelated = GovernmentId::create(['name' => 'Unaccepted guided ID']);
    $set = journeySet($passport);
    journeyGroup($set, [['items' => [$birth]]]);
    journeyGroup($set, [['items' => [$accepted, $reference, $off, $unavailable, $conditional, 'Manual option']]], ['title' => 'Valid ID / Supporting ID']);
    journeyGroup($set, [['items' => ['Appointment']]]);
    journeyGroup($set, [['items' => [$unrelated]]], ['condition_type' => 'spouse_surname']);
    $snapshot = DB::table('government_id_requirement_items')->get()->toJson();
    $payload = ['target_government_id_ids' => [$passport->id]];
    $this->actingAs($resident);
    $plan = $this->postJson('/api/auth/roadmap-plan', $payload)->assertOk()->json('data');
    expect(array_column($plan['steps'], 'name'))->toBe(['PSA Birth Certificate', 'Valid ID / Supporting ID', 'Philippine Passport']);
    $pending = journeyPending($plan)[0];
    expect(array_column($pending['suggestions'], 'name'))->toBe(['Accepted guided ID']);
    $invalid = ['key' => $pending['resolution_key'], 'type' => 'government_id', 'id' => $unrelated->id, 'way_id' => $pending['suggestions'][0]['way_id']];
    $this->postJson('/api/auth/roadmap-plan', [...$payload, 'resolution' => $invalid])->assertUnprocessable();
    $chosen = $this->postJson('/api/auth/roadmap-plan', [...$payload, 'resolution' => journeyChoose($pending, $pending['suggestions'][0])])->assertOk()->json('data');
    expect(journeyPending($chosen))->toBe([])->and(array_column($chosen['steps'], 'name'))->toBe(['PSA Birth Certificate', 'Accepted guided ID', 'Philippine Passport']);
    expect($this->postJson('/api/auth/roadmap-plan', $payload)->assertOk()->json('data'))->toBe($chosen);
    expect($resident->ownedGovernmentIds()->count())->toBe(0)->and($resident->ownedDocuments()->count())->toBe(0)
        ->and(DB::table('government_id_requirement_items')->get()->toJson())->toBe($snapshot)
        ->and(DB::table('resident_id_preparations')->count())->toBe(0);
    $other = User::factory()->create();
    expect(journeyPending(app(RoadmapPlanner::class)->build($other, [$passport->id])))->toHaveCount(1);
    // Exact Inventory ownership satisfies any accepted ID, including reference-only IDs.
    $resident->ownedGovernmentIds()->attach($reference);
    $resident->ownedDocuments()->attach($birth);
    $owned = $this->postJson('/api/auth/roadmap-plan', $payload)->assertOk()->json('data');
    expect(array_column($owned['steps'], 'name'))->toBe(['Philippine Passport']);
});

it('counts Choose N distinctly and never combines matches across OR ways', function () {
    $resident = User::factory()->create();
    $target = Document::create(['name' => 'Target']);
    $a = GovernmentId::create(['name' => 'A']);
    $b = GovernmentId::create(['name' => 'B']);
    $c = Document::create(['name' => 'C']);
    $d = Document::create(['name' => 'D']);
    journeyGroup(journeySet($target), [['count' => 2, 'items' => [$a, $b, $a]], ['count' => 2, 'items' => [$c, $d]]]);
    $resident->ownedGovernmentIds()->attach($a);
    $resident->ownedDocuments()->attach($c);
    $planner = app(RoadmapPlanner::class);
    $plan = $planner->build($resident, [], [$target->id]);
    $pending = journeyPending($plan)[0];
    expect($pending['remaining_count'])->toBe(1)->and(array_column($pending['suggestions'], 'name'))->toBe(['B', 'D']);
    $chosen = $planner->build($resident, [], [$target->id], journeyChoose($pending, $pending['suggestions'][0]));
    expect(journeyPending($chosen))->toBe([])->and(array_column($chosen['steps'], 'name'))->toBe(['B', 'Target']);
    $b->update(['guidance_mode' => 'informational_only']);
    $changed = $planner->build($resident, [], [$target->id]);
    expect(journeyPending($changed))->toHaveCount(1)->and(array_column($changed['steps'], 'name'))->not->toContain('B');
});

it('keeps a Choose N placeholder until enough options are selected from one way', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Choose two target']);
    $options = collect(['A', 'B', 'C'])->map(fn ($name) => GovernmentId::create(['name' => $name]));
    journeyGroup(journeySet($target), [['count' => 2, 'items' => $options->all()]]);
    $planner = app(RoadmapPlanner::class);
    $pending = journeyPending($planner->build($resident, [$target->id]))[0];
    $plan = $planner->build($resident, [$target->id], [], journeyChoose($pending, $pending['suggestions'][0]));
    expect(journeyPending($plan)[0]['remaining_count'])->toBe(1)->and(array_column($plan['steps'], 'name'))->toBe(['A', 'Accepted evidence', 'Choose two target']);
    $pending = journeyPending($plan)[0];
    $plan = $planner->build($resident, [$target->id], [], journeyChoose($pending, $pending['suggestions'][0]));
    expect(journeyPending($plan))->toBe([])->and(array_column($plan['steps'], 'name'))->toBe(['A', 'B', 'Choose two target']);
});

it('requires and persists a request path without merging alternative checklists', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $a = Document::create(['name' => 'First-time evidence']);
    $b = Document::create(['name' => 'Renewal evidence']);
    $first = journeySet($target);
    $renewal = journeySet($target, ['application_type' => 'renewal']);
    journeyGroup($first, [['items' => [$a]]]);
    journeyGroup($renewal, [['items' => [$b]]]);
    $planner = app(RoadmapPlanner::class);
    $plan = $planner->build($resident, [$target->id]);
    $pending = journeyPending($plan)[0];
    expect($pending['kind'])->toBe('path')->and($pending['suggestions'])->toHaveCount(2);
    $plan = $planner->build($resident, [$target->id], [], journeyChoose($pending, $pending['suggestions'][1]));
    expect(array_column($plan['steps'], 'name'))->toBe(['Renewal evidence', 'Target']);
    expect($planner->build($resident, [$target->id]))->toBe($plan);
    $renewal->groups()->delete();
    $renewal->delete();
    expect(array_column($planner->build($resident, [$target->id])['steps'], 'name'))->toBe(['First-time evidence', 'Target']);
});

it('uses verifiable age conditions and leaves unknown conditions qualifications and custom items manual', function () {
    $resident = User::factory()->create(['date_of_birth' => now()->subYears(20)->format('Y-m-d')]);
    $target = GovernmentId::create(['name' => 'Target']);
    $adult = journeySet($target, ['applicant_type' => 'adult']);
    $minor = journeySet($target, ['applicant_type' => 'minor']);
    $evidence = Document::create(['name' => 'Age evidence']);
    journeyGroup($minor, [['items' => [$evidence]]]);
    journeyGroup($adult, [['items' => [$evidence]]], ['condition_type' => 'below_18']);
    journeyGroup($adult, [['items' => [$evidence]]], ['condition_type' => 'custom', 'condition_custom' => 'If applicable']);
    journeyGroup($adult, [['qualification' => 'unexpired', 'items' => [$evidence]]]);
    journeyGroup($adult, [['items' => ['Printed form']]]);
    $resident->ownedDocuments()->attach($evidence);
    $plan = app(RoadmapPlanner::class)->build($resident, [$target->id]);
    expect(array_column($plan['steps'], 'name'))->toBe(['Target'])->and($plan['steps'][0]['prerequisite_readiness']['manual'])->toBe(3);
    $unknown = User::factory()->create(['date_of_birth' => null]);
    expect(journeyPending(app(RoadmapPlanner::class)->build($unknown, [$target->id]))[0]['kind'])->toBe('path');
});

it('ranks actual accepted options by missing prerequisites depth and distinct additional steps and excludes cycles', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $simple = GovernmentId::create(['name' => 'Z simple']);
    $shallow = GovernmentId::create(['name' => 'B shallow']);
    $deep = GovernmentId::create(['name' => 'A deep']);
    $wide = GovernmentId::create(['name' => 'A wide']);
    $cycle = GovernmentId::create(['name' => 'Cycle']);
    $a = Document::create(['name' => 'Dependency A']);
    $b = Document::create(['name' => 'Dependency B']);
    $c = Document::create(['name' => 'Dependency C']);
    app(CatalogPrerequisites::class)->save($shallow, [['type' => 'document', 'document_id' => $a->id]]);
    app(CatalogPrerequisites::class)->save($deep, [['type' => 'document', 'document_id' => $b->id]]);
    app(CatalogPrerequisites::class)->save($b, [['type' => 'document', 'document_id' => $c->id]]);
    app(CatalogPrerequisites::class)->save($wide, [['type' => 'document', 'document_id' => $a->id], ['type' => 'document', 'document_id' => $c->id]]);
    app(CatalogPrerequisites::class)->save($cycle, [['type' => 'government_id', 'government_id_id' => $target->id]]);
    journeyGroup(journeySet($target), [['items' => [$cycle, $deep, $wide, $shallow, $simple]]]);
    $pending = journeyPending(app(RoadmapPlanner::class)->build($resident, [$target->id]))[0];
    expect(array_column($pending['suggestions'], 'name'))->toBe(['Z simple', 'B shallow', 'A deep', 'A wide']);
    $chosen = app(RoadmapPlanner::class)->build($resident, [$target->id], [], journeyChoose($pending, $pending['suggestions'][2]));
    expect(array_column($chosen['steps'], 'name'))->toBe(['Dependency C', 'Dependency B', 'A deep', 'Target']);
});

it('does not infer any Passport dependency without structured links', function () {
    $passport = GovernmentId::forceCreate(['id' => 55, 'name' => 'Philippine Passport']);
    Document::forceCreate(['id' => 28, 'name' => 'PSA Birth Certificate']);
    $plan = app(RoadmapPlanner::class)->build(User::factory()->create(), [$passport->id]);
    expect(array_column($plan['steps'], 'name'))->toBe(['Philippine Passport']);
});

it('uses saved channel labels to distinguish request paths with identical application labels', function () {
    $resident = User::factory()->create();
    $target = Document::create(['name' => 'Target document']);
    journeySet($target, ['request_channel' => ['label' => 'Online channel']]);
    journeySet($target, ['request_channel' => ['label' => 'Outlet channel']]);
    $pending = journeyPending(app(RoadmapPlanner::class)->build($resident, [], [$target->id]))[0];
    expect($pending['name'])->toBe('Choose a request path for Target document')
        ->and(array_column($pending['suggestions'], 'name'))->toBe([
            'All Applicants • First-Time Application — Online channel',
            'All Applicants • First-Time Application — Outlet channel',
        ]);
});

it('does not retain extra acquisitions when the CMS reduces the current Choose N count', function () {
    $resident = User::factory()->create();
    $target = Document::create(['name' => 'Target']);
    $items = collect(['A', 'B', 'C'])->map(fn ($name) => GovernmentId::create(['name' => $name]));
    $group = journeyGroup(journeySet($target), [['count' => 2, 'items' => $items->all()]]);
    $planner = app(RoadmapPlanner::class);
    $plan = $planner->build($resident, [], [$target->id]);
    for ($i = 0; $i < 2; $i++) {
        $pending = journeyPending($plan)[0];
        $plan = $planner->build($resident, [], [$target->id], journeyChoose($pending, $pending['suggestions'][0]));
    }
    $group->ways()->sole()->update(['required_count' => 1]);
    expect(array_column($planner->build($resident, [], [$target->id])['steps'], 'name'))->toBe(['A', 'Target']);
});

it('breaks ranking ties by additional distinct acquisitions after missing count and depth', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $smaller = GovernmentId::create(['name' => 'Z smaller']);
    $larger = GovernmentId::create(['name' => 'A larger']);
    $one = Document::create(['name' => 'One']);
    $two = Document::create(['name' => 'Two']);
    $three = Document::create(['name' => 'Three']);
    $four = Document::create(['name' => 'Four']);
    $link = fn ($document) => ['type' => 'document', 'document_id' => $document->id];
    app(CatalogPrerequisites::class)->save($smaller, [$link($one)]);
    app(CatalogPrerequisites::class)->save($larger, [$link($two)]);
    app(CatalogPrerequisites::class)->save($one, [$link($three)]);
    app(CatalogPrerequisites::class)->save($two, [$link($three), $link($four)]);
    journeyGroup(journeySet($target), [['items' => [$larger, $smaller]]]);
    $suggestions = journeyPending(app(RoadmapPlanner::class)->build($resident, [$target->id]))[0]['suggestions'];
    expect(array_column($suggestions, 'name'))->toBe(['Z smaller', 'A larger'])
        ->and(array_column($suggestions, 'dependency_depth'))->toBe([2, 2])
        ->and(array_column($suggestions, 'additional_steps'))->toBe([2, 3]);
});

it('supports older direct-group Choose 1 items and persists a selection with no way ID', function () {
    $resident = User::factory()->create();
    $target = Document::create(['name' => 'Older target']);
    $group = journeySet($target)->groups()->create(['rule' => 'choose_one', 'title' => 'Legacy accepted IDs']);
    foreach (['A', 'B'] as $name) {
        $option = GovernmentId::create(['name' => $name]);
        $group->items()->create(['type' => 'government_id', 'government_id_id' => $option->id]);
    }
    $planner = app(RoadmapPlanner::class);
    $pending = journeyPending($planner->build($resident, [], [$target->id]))[0];
    expect($pending['suggestions'][0]['way_id'])->toBeNull();
    $this->actingAs($resident)->postJson('/api/auth/roadmap-plan', [
        'target_document_ids' => [$target->id], 'resolution' => journeyChoose($pending, $pending['suggestions'][0]),
    ])->assertOk()->assertJsonPath('data.steps.0.name', 'A');
    expect(array_column($planner->build($resident, [], [$target->id])['steps'], 'name'))->toBe(['A', 'Older target']);
});
