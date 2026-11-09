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
    expect(array_column($plan['steps'], 'name'))->toBe(['Accepted evidence', 'Target'])
        ->and($plan['steps'][0]['manual_preparation'])->toBeTrue()
        ->and($plan['steps'][1]['prerequisite_readiness']['manual'])->toBe(3);
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

it('keeps saved request methods informational without requiring a roadmap path', function () {
    $resident = User::factory()->create();
    $target = Document::create(['name' => 'Target document']);
    journeySet($target, ['request_channel' => ['label' => 'Online channel']]);
    journeySet($target, ['request_channel' => ['label' => 'Outlet channel']]);
    $plan = app(RoadmapPlanner::class)->build($resident, [], [$target->id]);
    expect(journeyPending($plan))->toBe([])->and(array_column($plan['steps'], 'name'))->toBe(['Target document']);
    $this->getJson('/api/documents/'.$target->id)->assertOk()
        ->assertJsonPath('data.request_paths.0.label', 'Online channel')
        ->assertJsonPath('data.request_paths.1.label', 'Outlet channel');
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

// Shaped like the live catalog, where these three accept each other as alternatives.
function mutualAlternativesCatalog(): array
{
    $psa = Document::create(['name' => 'PSA Birth Certificate']);
    $sss = GovernmentId::create(['name' => 'MySSS Card', 'processing_time' => '2–4 weeks']);
    $passport = GovernmentId::create(['name' => 'Philippine Passport', 'processing_time' => '10–12 working days']);
    $philsys = GovernmentId::create(['name' => 'PhilSys National ID', 'processing_time' => 'Varies']);
    $postal = GovernmentId::create(['name' => 'Postal ID', 'processing_time' => 'Varies']);
    $set = journeySet($passport);
    journeyGroup($set, [['items' => [$psa]]], ['title' => null]);
    journeyGroup($set, [['items' => [$philsys, $postal, $sss]]], ['title' => 'Valid ID']);
    journeyGroup(journeySet($philsys), [['items' => [$psa]], ['items' => [$passport]], ['items' => [$postal]]], ['title' => 'Proof of Identity']);
    journeyGroup(journeySet($postal), [['items' => [$psa, $passport, $philsys]]], ['title' => 'Proof of Identity']);

    return compact('psa', 'sss', 'passport', 'philsys', 'postal');
}

it('reuses shared accepted evidence across combined targets without duplicate prompts', function () {
    $c = mutualAlternativesCatalog();
    $resident = User::factory()->create();
    $plan = app(RoadmapPlanner::class)->build($resident, [$c['postal']->id, $c['passport']->id, $c['philsys']->id]);
    $names = array_column($plan['steps'], 'name');
    expect(journeyPending($plan))->toBe([])->and($plan['blocked'])->toBeFalse()
        ->and(array_count_values($names)['PSA Birth Certificate'])->toBe(1);
    foreach (['PhilSys National ID', 'Postal ID', 'Philippine Passport'] as $target) {
        expect(array_search('PSA Birth Certificate', $names))->toBeLessThan(array_search($target, $names));
    }
    expect(DB::table('resident_journey_resolutions')->count())->toBe(0)
        ->and($resident->ownedDocuments()->count())->toBe(0);
});

it('automatically orders Birth Certificate then PhilSys then Passport when those complete CMS combinations apply', function () {
    $c = mutualAlternativesCatalog();
    $resident = User::factory()->create();
    $planner = app(RoadmapPlanner::class);
    $targets = [$c['passport']->id, $c['philsys']->id];
    $plan = $planner->build($resident, $targets);
    expect(array_column($plan['steps'], 'name'))->toBe(['PSA Birth Certificate', 'PhilSys National ID', 'Philippine Passport'])
        ->and(journeyPending($plan))->toBe([])->and($plan['blocked'])->toBeFalse();
    expect($planner->build($resident, array_reverse($targets))['steps'])->toBe($plan['steps']);
    $philsys = collect($plan['steps'])->firstWhere('name', 'PhilSys National ID');
    expect(array_column($philsys['prerequisites'], 'name'))->toBe(['PSA Birth Certificate']);
    $passport = collect($plan['steps'])->firstWhere('name', 'Philippine Passport');
    expect(array_column($passport['prerequisites'], 'name'))->toContain('PhilSys National ID');
});

it('preserves an explicit saved non-roadmap alternative instead of replacing it with an automatic choice', function () {
    $c = mutualAlternativesCatalog();
    $resident = User::factory()->create();
    $planner = app(RoadmapPlanner::class);
    $validId = collect(journeyPending($planner->build($resident, [$c['passport']->id])))->firstWhere('name', 'Valid ID');
    $sss = collect($validId['suggestions'])->firstWhere('name', 'MySSS Card');
    $planner->build($resident, [$c['passport']->id], [], journeyChoose($validId, $sss));
    $saved = DB::table('resident_journey_resolutions')->where('user_id', $resident->id)->value('selections');
    $keys = ['government_id:'.$c['passport']->id, 'government_id:'.$c['philsys']->id];
    sort($keys);
    DB::table('resident_journey_resolutions')->insert([
        'user_id' => $resident->id, 'journey_key' => hash('sha256', json_encode($keys)),
        'selections' => $saved, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $chosen = $planner->build($resident, [$c['passport']->id, $c['philsys']->id]);
    $names = array_column($chosen['steps'], 'name');
    expect($names)->toContain('MySSS Card')
        ->and(array_search('MySSS Card', $names))->toBeLessThan(array_search('Philippine Passport', $names));
    $passport = collect($chosen['steps'])->firstWhere('name', 'Philippine Passport');
    expect(array_column($passport['prerequisites'], 'name'))->not->toContain('PhilSys National ID');
    expect(DB::table('resident_journey_resolutions')->where('journey_key', hash('sha256', json_encode($keys)))->value('selections'))->toBe($saved);
});

it('leaves owned requirements out of the roadmap and satisfies choices from Inventory', function () {
    $c = mutualAlternativesCatalog();
    $resident = User::factory()->create();
    $resident->ownedDocuments()->attach($c['psa']);
    $resident->ownedGovernmentIds()->attach($c['sss']);

    $plan = app(RoadmapPlanner::class)->build($resident, [$c['passport']->id, $c['philsys']->id]);
    $names = array_column($plan['steps'], 'name');
    expect($names)->not->toContain('PSA Birth Certificate')->not->toContain('MySSS Card')->not->toContain('Valid ID');
});

it('marks a selected target the resident owns instead of planning its acquisition', function () {
    $c = mutualAlternativesCatalog();
    $resident = User::factory()->create();
    $resident->ownedGovernmentIds()->attach($c['philsys']);

    $plan = app(RoadmapPlanner::class)->build($resident, [$c['philsys']->id, $c['postal']->id]);
    $philsys = collect($plan['steps'])->firstWhere('name', 'PhilSys National ID');
    expect($philsys['already_owned'])->toBeTrue()->and($philsys['selected'])->toBeTrue()->and($philsys['blocked'])->toBeFalse();
    // Its own requirements are not needed, and it already satisfies Postal ID.
    expect(array_column($plan['steps'], 'name'))->toBe(['PhilSys National ID', 'Postal ID']);
});

it('uses processing time only between independent items with equal steps', function () {
    $resident = User::factory()->create();
    $unknown = GovernmentId::create(['name' => 'A Varies', 'processing_time' => 'Varies']);
    $blank = GovernmentId::create(['name' => 'B Blank']);
    $slow = GovernmentId::create(['name' => 'C Slow', 'processing_time' => '2–4 weeks']);
    $fast = GovernmentId::create(['name' => 'D Fast', 'processing_time' => 'Same day']);

    $plan = app(RoadmapPlanner::class)->build($resident, [$unknown->id, $blank->id, $slow->id, $fast->id]);
    expect(array_column($plan['steps'], 'name'))->toBe(['D Fast', 'C Slow', 'A Varies', 'B Blank'])
        ->and($plan['steps'][0]['processing_time'])->toBe('Same day');
});

it('keeps a slow dependency before the fast target that truly needs it', function () {
    $resident = User::factory()->create();
    $dependency = GovernmentId::create(['name' => 'Dependency', 'processing_time' => '1 month']);
    $dependent = GovernmentId::create(['name' => 'Dependent', 'processing_time' => 'Same day']);
    app(CatalogPrerequisites::class)->save($dependent, [['type' => 'government_id', 'government_id_id' => $dependency->id]]);

    $plan = app(RoadmapPlanner::class)->build($resident, [$dependent->id]);
    expect(array_column($plan['steps'], 'name'))->toBe(['Dependency', 'Dependent']);
});

it('orders calendar-day targets correctly while dependencies override processing time', function () {
    $resident = User::factory()->create();
    $fast = GovernmentId::create(['name' => 'Z Fast', 'processing_time' => '1 calendar day']);
    $medium = GovernmentId::create(['name' => 'Y Medium', 'processing_time' => '1–3 calendar days']);
    $slow = GovernmentId::create(['name' => 'A Slow', 'processing_time' => '10 working days']);
    $planner = app(RoadmapPlanner::class);
    expect(array_column($planner->build($resident, [$slow->id, $medium->id, $fast->id])['steps'], 'name'))
        ->toBe(['Z Fast', 'Y Medium', 'A Slow']);

    app(CatalogPrerequisites::class)->save($fast, [['type' => 'government_id', 'government_id_id' => $slow->id]]);
    expect(array_column($planner->build($resident, [$fast->id, $medium->id, $slow->id])['steps'], 'name'))
        ->toBe(['Y Medium', 'A Slow', 'Z Fast']);
});

it('ranks alternatives by requirements first and processing time only as a tie-break', function () {
    $resident = User::factory()->create();
    $needsMore = GovernmentId::create(['name' => 'A Fast but needs more', 'processing_time' => 'Same day']);
    $needed = Document::create(['name' => 'Needed']);
    app(CatalogPrerequisites::class)->save($needsMore, [['type' => 'document', 'document_id' => $needed->id]]);
    $slow = GovernmentId::create(['name' => 'B Slow', 'processing_time' => '2 weeks']);
    $quick = GovernmentId::create(['name' => 'C Quick', 'processing_time' => '3 days']);
    $unknown = GovernmentId::create(['name' => 'D Unknown']);
    $target = GovernmentId::create(['name' => 'Target']);
    journeyGroup(journeySet($target), [['items' => [$needsMore, $slow, $quick, $unknown]]]);

    $options = journeyPending(app(RoadmapPlanner::class)->build($resident, [$target->id]))[0]['suggestions'];
    expect(array_column($options, 'name'))->toBe(['C Quick', 'B Slow', 'D Unknown', 'A Fast but needs more'])
        ->and($options[0]['processing_time'])->toBe('3 days');
});


it('uses Inventory before an accepted shared roadmap acquisition', function () {
    $c = mutualAlternativesCatalog();
    $resident = User::factory()->create();
    $resident->ownedGovernmentIds()->attach($c['sss']);
    $plan = app(RoadmapPlanner::class)->build($resident, [$c['passport']->id, $c['philsys']->id]);
    $passport = collect($plan['steps'])->firstWhere('name', 'Philippine Passport');
    expect(journeyPending($plan))->toBe([])
        ->and(array_column($passport['prerequisites'], 'name'))->not->toContain('PhilSys National ID')
        ->and(array_column($plan['steps'], 'name'))->not->toContain('MySSS Card');
});

it('reuses arbitrary documents already acquired for another target and enforces completion order', function () {
    $resident = User::factory()->create();
    $a = GovernmentId::create(['name' => 'Target A']);
    $b = GovernmentId::create(['name' => 'Target B']);
    $evidence = Document::create(['name' => 'Shared proof']);
    $other = Document::create(['name' => 'Other proof']);
    journeyGroup(journeySet($a), [['items' => [$evidence]]]);
    journeyGroup(journeySet($b), [['items' => [$evidence, $other]]]);
    $snapshot = DB::table('government_id_requirement_items')->get()->toJson();
    $payload = ['target_government_id_ids' => [$b->id, $a->id]];
    $saved = $this->actingAs($resident)->postJson('/api/auth/journeys', $payload)->assertCreated()->json('data');
    $names = array_column($saved['plan']['steps'], 'name');
    expect($names)->toBe(['Shared proof', 'Target A', 'Target B']);
    $path = '/api/auth/journeys/'.$saved['id'].'/completion';
    $this->putJson($path, ['step_identity' => 'government_id:'.$b->id])->assertUnprocessable();
    $this->putJson($path, ['step_identity' => 'document:'.$evidence->id])->assertOk();
    $this->putJson($path, ['step_identity' => 'government_id:'.$b->id])->assertOk();
    expect(DB::table('government_id_requirement_items')->get()->toJson())->toBe($snapshot)
        ->and(DB::table('resident_journey_resolutions')->count())->toBe(0);
});

it('combines owned and planned distinct items within a Choose N way', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Choose two']);
    $owned = Document::create(['name' => 'Owned proof']);
    $planned = Document::create(['name' => 'Planned proof']);
    $unused = Document::create(['name' => 'Unused proof']);
    $resident->ownedDocuments()->attach($owned);
    journeyGroup(journeySet($target), [['count' => 2, 'items' => [$owned, $planned, $planned, $unused]]]);
    $plan = app(RoadmapPlanner::class)->build($resident, [$target->id], [$planned->id]);
    expect(journeyPending($plan))->toBe([])
        ->and(array_column($plan['steps'], 'name'))->toBe(['Planned proof', 'Choose two']);
});

it('does not pool planned items across OR ways or count a duplicate twice', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $a = Document::create(['name' => 'A']);
    $b = Document::create(['name' => 'B']);
    $c = Document::create(['name' => 'C']);
    $d = Document::create(['name' => 'D']);
    journeyGroup(journeySet($target), [['count' => 2, 'items' => [$a, $a, $b]], ['count' => 2, 'items' => [$c, $d]]]);
    $plan = app(RoadmapPlanner::class)->build($resident, [$target->id], [$a->id, $c->id]);
    expect(journeyPending($plan))->toHaveCount(1);
    $inJourney = array_column(array_filter(journeyPending($plan)[0]['suggestions'], fn ($option) => $option['in_journey']), 'name');
    expect($inJourney)->toBe(['A', 'C']);
});

it('never automatically chooses an included option that depends on its owner', function () {
    $resident = User::factory()->create();
    $a = GovernmentId::create(['name' => 'Target A']);
    $b = GovernmentId::create(['name' => 'Target B']);
    $proof = Document::create(['name' => 'Safe proof']);
    journeyGroup(journeySet($a), [['items' => [$b, $proof]]]);
    journeyGroup(journeySet($b), [['items' => [$a]]]);
    $plan = app(RoadmapPlanner::class)->build($resident, [$a->id, $b->id], [$proof->id]);
    expect($plan['blocked'])->toBeFalse()->and(journeyPending($plan))->toBe([])
        ->and(array_column($plan['steps'], 'name'))->toBe(['Safe proof', 'Target A', 'Target B']);
});

it('checks the graph after every automatic choice to prevent mutual cycles', function () {
    $resident = User::factory()->create();
    $a = GovernmentId::create(['name' => 'Target A']);
    $b = GovernmentId::create(['name' => 'Target B']);
    $x = Document::create(['name' => 'Extra X']);
    $y = Document::create(['name' => 'Extra Y']);
    journeyGroup(journeySet($a), [['items' => [$b, $x]]]);
    journeyGroup(journeySet($b), [['items' => [$a, $y]]]);
    $plan = app(RoadmapPlanner::class)->build($resident, [$a->id, $b->id]);
    expect($plan['blocked'])->toBeFalse()->and(journeyPending($plan))->toHaveCount(1);
    $pending = journeyPending($plan)[0];
    expect(array_column($pending['suggestions'], 'name'))->toBe(['Extra Y']);
});

it('marks shared prerequisites as already in the roadmap even when a Choose N group remains unresolved', function () {
    $resident = User::factory()->create();
    $a = GovernmentId::create(['name' => 'A']);
    $b = GovernmentId::create(['name' => 'B']);
    $e = Document::create(['name' => 'Shared proof']);
    $x = Document::create(['name' => 'Extra proof']);
    journeyGroup(journeySet($a), [['items' => [$e]]]);
    journeyGroup(journeySet($b), [['count' => 2, 'items' => [$e, $x, 'Manual evidence']]]);
    $pending = journeyPending(app(RoadmapPlanner::class)->build($resident, [$a->id, $b->id]))[0];
    $option = collect($pending['suggestions'])->firstWhere('name', 'Shared proof');
    expect($option['in_journey'])->toBeTrue()->and($option['already_owned'])->toBeFalse();
});

it('preserves the current PhilSys Birth Certificate plus manual-ID combination and avoids the Passport loop', function () {
    $c = mutualAlternativesCatalog();
    $resident = User::factory()->create();
    // The live CMS requires BOTH these items, not Birth Certificate alone.
    $c['philsys']->requirementSets()->delete();
    $group = journeyGroup(journeySet($c['philsys']), [
        ['count' => 2, 'items' => [$c['psa'], 'Government-issued photo ID with signature']],
        ['items' => [$c['passport']]], ['items' => [$c['postal']]],
    ], ['title' => 'Proof of Identity / Supporting Document']);
    $plan = app(RoadmapPlanner::class)->build($resident, [$c['philsys']->id, $c['passport']->id]);
    expect($plan['blocked'])->toBeFalse()->and(journeyPending($plan))->toHaveCount(1);
    $proof = journeyPending($plan)[0];
    expect($proof['name'])->toBe('Proof of Identity / Supporting Document')
        ->and(array_column($proof['suggestions'], 'name'))->not->toContain('Philippine Passport');
    $shared = collect($proof['suggestions'])->firstWhere('name', 'PSA Birth Certificate');
    expect($shared['in_journey'])->toBeTrue();
    $names = array_column($plan['steps'], 'name');
    expect($shared['remaining_count'])->toBe(2);
    expect(array_search('PSA Birth Certificate', $names))->toBeLessThan(array_search('Philippine Passport', $names));
    $philsys = collect($plan['steps'])->firstWhere('name', 'PhilSys National ID');
    expect(array_column($philsys['prerequisites'], 'name'))->not->toContain('PSA Birth Certificate');
    expect($proof['suggestions'])->not->toBe([]);

    // A saved partial two-item choice must not hide valid alternate ways.
    $partial = app(RoadmapPlanner::class)->build($resident, [$c['philsys']->id, $c['passport']->id], [], journeyChoose($proof, $shared));
    $remaining = journeyPending($partial)[0];
    expect($remaining['manual_preparation'])->toBeFalse()
        ->and(array_column($remaining['suggestions'], 'name'))->toContain('Postal ID');

    // Simulate the user's CMS correction in the isolated test database:
    // add a separate complete Birth Certificate-only alternative.
    $way = $group->ways()->create(['required_count' => 1, 'qualification_type' => 'none']);
    $item = $group->items()->make(['type' => 'document', 'document_id' => $c['psa']->id]);
    $item->requirement_way_id = $way->id;
    $item->save();
    $snapshot = DB::table('government_id_requirement_items')->get()->toJson();
    $updated = app(RoadmapPlanner::class)->build($resident, [$c['philsys']->id, $c['passport']->id]);
    expect(array_column($updated['steps'], 'name'))->toBe(['PSA Birth Certificate', 'PhilSys National ID', 'Philippine Passport'])
        ->and(journeyPending($updated))->toBe([])
        ->and($updated['blocked'])->toBeFalse()
        ->and(DB::table('government_id_requirement_items')->get()->toJson())->toBe($snapshot)
        ->and(DB::table('resident_journey_resolutions')->count())->toBe(1);

});
