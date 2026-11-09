<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Services\RoadmapPlanner;
use Illuminate\Support\Facades\DB;

function suggestionSet($owner, array $attributes = [])
{
    return $owner->requirementSets()->create([
        'application_type' => 'new', 'applicant_type' => 'all', ...$attributes,
    ]);
}

function suggestionGroup($set, array $ways, string $title = 'Accepted evidence')
{
    $group = $set->groups()->create(['rule' => 'all', 'title' => $title]);
    foreach ($ways as $spec) {
        $way = $group->ways()->create([
            'required_count' => $spec['count'] ?? 1,
            'qualification_type' => $spec['qualification'] ?? 'none',
        ]);
        foreach ($spec['items'] as $item) {
            $link = is_string($item) ? ['type' => 'custom', 'custom_name' => $item]
                : ($item instanceof Document ? ['type' => 'document', 'document_id' => $item->id]
                    : ['type' => 'government_id', 'government_id_id' => $item->id]);
            $row = $group->items()->make($link);
            $row->requirement_way_id = $way->id;
            $row->save();
        }
    }

    return $group;
}

function suggestionPending(array $plan): array
{
    return collect($plan['steps'])->firstWhere('type', 'unresolved_requirement');
}

function suggestionChoose(array $step, array $option): array
{
    return ['key' => $step['resolution_key'], ...array_intersect_key($option, array_flip(['type', 'id', 'way_id', 'set_id']))];
}

function suggestionGuide($set): void
{
    $set->applicationSteps()->create([
        'title' => 'Submit the completed application',
        'short_description' => 'Take the listed requirements and application to the issuing office.',
    ]);
}

function suggestionFee($owner, float $amount): void
{
    $owner->fees()->create(['label' => 'Acquisition charge', 'type' => 'fixed', 'amount_min' => $amount, 'currency' => 'PHP']);
}

function suggestionSnapshot(): string
{
    $snapshot = [];
    foreach (['government_ids', 'documents', 'government_id_requirement_sets', 'document_requirement_sets',
        'government_id_requirement_groups', 'document_requirement_groups', 'government_id_requirement_ways',
        'document_requirement_ways', 'government_id_requirement_items', 'document_requirement_items'] as $table) {
        $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toArray();
    }

    return json_encode($snapshot, JSON_THROW_ON_ERROR);
}

it('prioritizes accepted acquisitions using owned or planned evidence before a faster new-ID chain', function (bool $owned) {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Main target']);
    $otherTarget = GovernmentId::create(['name' => 'Other target']);
    $evidence = Document::create(['name' => 'Reusable civil document']);
    $practical = GovernmentId::create(['name' => 'Practical accepted ID', 'processing_time' => '20 days']);
    $fast = GovernmentId::create(['name' => 'Fast but needs another ID', 'processing_time' => 'Same day']);
    $newId = GovernmentId::create(['name' => 'Additional ID']);
    suggestionGroup(suggestionSet($target), [['items' => [$fast, $practical]]]);
    suggestionGroup(suggestionSet($otherTarget), [['items' => [$evidence]]]);
    suggestionGroup(suggestionSet($practical), [['items' => [$evidence]]]);
    suggestionGroup(suggestionSet($fast), [['items' => [$newId]]]);
    if ($owned) {
        $resident->ownedDocuments()->attach($evidence);
    }
    $before = suggestionSnapshot();
    $plan = app(RoadmapPlanner::class)->build($resident, [$target->id, $otherTarget->id]);
    $options = suggestionPending($plan)['suggestions'];
    expect(array_column($options, 'name'))->toBe(['Practical accepted ID', 'Fast but needs another ID'])
        ->and($options[0]['additional_steps'])->toBe(0)
        ->and($options[0]['additional_id_steps'])->toBe(0)
        ->and($options[0]['unresolved_choices'])->toBe(0)
        ->and($options[0]['recommendation_reasons'])->toContain('No additional ID required');
    if ($owned) {
        expect($options[0]['recommendation_reasons'])->toContain('Uses documents you already have');
    } else {
        expect(implode(' ', $options[0]['recommendation_reasons']))->toContain('Roadmap');
    }
    expect(array_column($plan['steps'], 'name'))->not->toContain('Practical accepted ID')->not->toContain('Additional ID')
        ->and(suggestionSnapshot())->toBe($before)
        ->and($resident->ownedDocuments()->count())->toBe($owned ? 1 : 0)
        ->and($resident->ownedGovernmentIds()->count())->toBe(0)
        ->and(DB::table('resident_journey_resolutions')->count())->toBe(0);
})->with([[true], [false]]);

it('evaluates an accepted candidate alternative using shared Roadmap evidence without adding the candidate during ranking', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Main target']);
    $otherTarget = GovernmentId::create(['name' => 'Evidence owner']);
    $candidate = GovernmentId::create(['name' => 'Flexible accepted ID']);
    $needy = GovernmentId::create(['name' => 'Needs another ID']);
    $additionalId = GovernmentId::create(['name' => 'Additional identity document']);
    $shared = Document::create(['name' => 'Shared supporting document']);
    $alternative = Document::create(['name' => 'Other supporting document']);
    suggestionGroup(suggestionSet($target), [['items' => [$candidate, $needy]]]);
    suggestionGroup(suggestionSet($otherTarget), [['items' => [$shared]]]);
    suggestionGroup(suggestionSet($candidate), [['items' => [$shared, $alternative]]]);
    suggestionGroup(suggestionSet($needy), [['items' => [$additionalId]]]);
    $before = suggestionSnapshot();
    $planner = app(RoadmapPlanner::class);
    $plan = $planner->build($resident, [$target->id, $otherTarget->id]);
    $pending = suggestionPending($plan);
    $option = collect($pending['suggestions'])->firstWhere('name', 'Flexible accepted ID');
    expect($pending['suggestions'][0]['name'])->toBe('Flexible accepted ID')
        ->and($option['additional_steps'])->toBe(0)
        ->and($option['unresolved_choices'])->toBe(0)
        ->and(array_column($plan['steps'], 'name'))->not->toContain('Flexible accepted ID')
        ->and(suggestionSnapshot())->toBe($before)
        ->and(DB::table('resident_journey_resolutions')->count())->toBe(0);
    $chosen = $planner->build($resident, [$target->id, $otherTarget->id], [], suggestionChoose($pending, $option));
    $names = array_column($chosen['steps'], 'name');
    expect($names)->toContain('Flexible accepted ID')->not->toContain('Other supporting document')->not->toContain('Accepted evidence')
        ->and(array_count_values($names)['Shared supporting document'])->toBe(1)
        ->and(array_search('Shared supporting document', $names))->toBeLessThan(array_search('Flexible accepted ID', $names))
        ->and(array_search('Flexible accepted ID', $names))->toBeLessThan(array_search('Main target', $names));
});

it('does not describe a further unresolved ID choice as requiring no additional ID', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $paradox = GovernmentId::create(['name' => 'Fast unresolved identity route', 'processing_time' => 'Same day']);
    $starter = Document::create(['name' => 'Direct supporting document', 'processing_time' => '10 days', 'application_process' => 'Documented steps']);
    $first = GovernmentId::create(['name' => 'First additional ID']);
    $second = GovernmentId::create(['name' => 'Second additional ID']);
    suggestionGroup(suggestionSet($target), [['items' => [$paradox, $starter]]]);
    suggestionGroup(suggestionSet($paradox), [['items' => [$first, $second]]]);
    $options = suggestionPending(app(RoadmapPlanner::class)->build($resident, [$target->id]))['suggestions'];
    $risky = collect($options)->firstWhere('name', 'Fast unresolved identity route');
    expect($options[0]['name'])->toBe('Direct supporting document')
        ->and($risky['unresolved_choices'])->toBeGreaterThan(0)
        ->and($risky['recommendation_reasons'])->not->toContain('No additional ID required');
});

it('excludes an impossible indirect alternative cycle instead of presenting it as an easy option', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Dependent target']);
    $cyclic = GovernmentId::create(['name' => 'Circular candidate']);
    $support = Document::create(['name' => 'Circular supporting document']);
    $unavailable = GovernmentId::create(['name' => 'Unavailable exit', 'application_availability' => 'temporarily_unavailable']);
    $valid = Document::create(['name' => 'Independent accepted document']);
    suggestionGroup(suggestionSet($target), [['items' => [$cyclic, $valid]]]);
    suggestionGroup(suggestionSet($cyclic), [['items' => [$support]]]);
    suggestionGroup(suggestionSet($support), [['items' => [$target, $unavailable]]]);
    $options = suggestionPending(app(RoadmapPlanner::class)->build($resident, [$target->id]))['suggestions'];
    expect(array_column($options, 'name'))->toBe(['Independent accepted document']);
});

it('does not invent processing-time or cost comparisons when the CMS facts are unknown', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $a = Document::create(['name' => 'A option', 'processing_time' => 'Varies']);
    $b = Document::create(['name' => 'B option']);
    suggestionGroup(suggestionSet($target), [['items' => [$a, $b]]]);
    $options = suggestionPending(app(RoadmapPlanner::class)->build($resident, [$target->id]))['suggestions'];
    foreach ($options as $option) {
        expect($option['known_cost'])->toBeNull()
            ->and($option['estimated_days'])->toBeNull()
            ->and($option['recommendation_reasons'])->not->toContain('Lower known cost')->not->toContain('Shorter known processing time');
    }
});

it('uses shorter known processing time before lower known cost when practical requirements tie', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $slow = Document::create(['name' => 'Cheap slow option', 'processing_time' => '10 days', 'application_process' => 'Documented steps']);
    $fast = Document::create(['name' => 'Costly fast option', 'processing_time' => '2 days', 'application_process' => 'Documented steps']);
    suggestionFee($slow, 10);
    suggestionFee($fast, 100);
    suggestionGroup(suggestionSet($target), [['items' => [$slow, $fast]]]);
    $options = suggestionPending(app(RoadmapPlanner::class)->build($resident, [$target->id]))['suggestions'];
    expect(array_column($options, 'name'))->toBe(['Costly fast option', 'Cheap slow option'])
        ->and($options[0]['recommendation_reasons'])->toContain('Shorter known processing time');
});

it('uses lower known same-currency cost as a tie-break without treating an unknown cost as free', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $expensive = Document::create(['name' => 'A expensive option', 'processing_time' => '3 days', 'application_process' => 'Documented steps']);
    $cheap = Document::create(['name' => 'Z cheaper option', 'processing_time' => '3 days', 'application_process' => 'Documented steps']);
    $unknown = Document::create(['name' => 'B unknown fee option', 'processing_time' => '3 days', 'application_process' => 'Documented steps']);
    suggestionFee($expensive, 100);
    suggestionFee($cheap, 50);
    suggestionGroup(suggestionSet($target), [['items' => [$expensive, $unknown, $cheap]]]);
    $options = suggestionPending(app(RoadmapPlanner::class)->build($resident, [$target->id]))['suggestions'];
    $cheaper = collect($options)->firstWhere('name', 'Z cheaper option');
    $missing = collect($options)->firstWhere('name', 'B unknown fee option');
    expect(array_search('Z cheaper option', array_column($options, 'name')))->toBeLessThan(array_search('A expensive option', array_column($options, 'name')))
        ->and($cheaper['known_cost'])->toBe(['min' => 50.0, 'max' => 50.0, 'currency' => 'PHP'])
        ->and($cheaper['recommendation_reasons'])->toContain('Lower known cost')
        ->and($missing['known_cost'])->toBeNull()
        ->and($missing['recommendation_reasons'])->not->toContain('Lower known cost');
});

it('uses the presence of applicable guided instructions only after time and cost tie', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $withoutGuide = Document::create(['name' => 'A incomplete flow', 'processing_time' => '3 days']);
    $withGuide = Document::create(['name' => 'Z guided flow', 'processing_time' => '3 days']);
    suggestionFee($withoutGuide, 50);
    suggestionFee($withGuide, 50);
    suggestionSet($withoutGuide);
    suggestionGuide(suggestionSet($withGuide));
    suggestionGroup(suggestionSet($target), [['items' => [$withoutGuide, $withGuide]]]);
    $options = suggestionPending(app(RoadmapPlanner::class)->build($resident, [$target->id]))['suggestions'];
    expect(array_column($options, 'name'))->toBe(['Z guided flow', 'A incomplete flow'])
        ->and($options[0]['acquisition_flow_complete'])->toBeTrue()
        ->and($options[1]['acquisition_flow_complete'])->toBeFalse();
});

it('retains a valid fourth option and choosing it replaces the generic unresolved step with the actual acquisition', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $accepted = [];
    foreach (range(1, 4) as $days) {
        $accepted[] = Document::create(['name' => 'Accepted option '.$days, 'processing_time' => $days.' days', 'application_process' => 'Documented steps']);
    }
    suggestionGroup(suggestionSet($target), [['items' => $accepted]]);
    $before = suggestionSnapshot();
    $planner = app(RoadmapPlanner::class);
    $pending = suggestionPending($planner->build($resident, [$target->id]));
    expect($pending['suggestions'])->toHaveCount(4)
        ->and(array_column($pending['suggestions'], 'recommended'))->toBe([true, true, true, false])
        ->and(suggestionSnapshot())->toBe($before)
        ->and(DB::table('resident_journey_resolutions')->count())->toBe(0);
    $chosen = $planner->build($resident, [$target->id], [], suggestionChoose($pending, $pending['suggestions'][3]));
    expect(array_column($chosen['steps'], 'name'))->toBe(['Accepted option 4', 'Target'])
        ->and(DB::table('resident_journey_resolutions')->count())->toBe(1)
        ->and($resident->ownedGovernmentIds()->count())->toBe(0)
        ->and($resident->ownedDocuments()->count())->toBe(0)
        ->and(suggestionSnapshot())->toBe($before);
});

it('reranks after Inventory changes without altering CMS data or fabricating ownership', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $a = GovernmentId::create(['name' => 'A accepted ID', 'processing_time' => '5 days']);
    $b = GovernmentId::create(['name' => 'B accepted ID', 'processing_time' => '5 days']);
    $first = Document::create(['name' => 'First evidence']);
    $second = Document::create(['name' => 'Second evidence']);
    suggestionGroup(suggestionSet($target), [['items' => [$a, $b]]]);
    suggestionGroup(suggestionSet($a), [['items' => [$first]]]);
    suggestionGroup(suggestionSet($b), [['items' => [$second]]]);
    $before = suggestionSnapshot();
    $planner = app(RoadmapPlanner::class);
    expect(suggestionPending($planner->build($resident, [$target->id]))['suggestions'][0]['name'])->toBe('A accepted ID');
    $resident->ownedDocuments()->attach($second);
    $options = suggestionPending($planner->build($resident, [$target->id]))['suggestions'];
    expect($options[0]['name'])->toBe('B accepted ID')
        ->and($options[0]['additional_steps'])->toBe(0)
        ->and($options[0]['recommendation_reasons'])->toContain('Uses documents you already have')
        ->and($resident->ownedDocuments()->pluck('documents.id')->all())->toBe([$second->id])
        ->and($resident->ownedGovernmentIds()->count())->toBe(0)
        ->and(DB::table('resident_journey_resolutions')->count())->toBe(0)
        ->and(suggestionSnapshot())->toBe($before);
});

it('does not treat an unavailable or sequencing-excluded linked ID as a manual escape from a cyclic alternative', function (array $attributes) {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Dependent target']);
    $candidate = GovernmentId::create(['name' => 'Incomplete circular candidate']);
    $unavailable = GovernmentId::create(['name' => 'Unusable linked ID', ...$attributes]);
    $safeDocument = Document::create(['name' => 'Available half of a combination']);
    $fallback = Document::create(['name' => 'Independent accepted document']);
    suggestionGroup(suggestionSet($target), [['items' => [$candidate, $fallback]]]);
    suggestionGroup(suggestionSet($candidate), [
        ['count' => 2, 'items' => [$safeDocument, $unavailable]],
        ['items' => [$target]],
    ]);
    $options = suggestionPending(app(RoadmapPlanner::class)->build($resident, [$target->id]))['suggestions'];
    expect(array_column($options, 'name'))->toBe(['Independent accepted document']);
})->with([
    'unavailable ID' => [['application_availability' => 'temporarily_unavailable']],
    'excluded ID' => [['include_in_smart_sequencing' => false]],
]);

it('excludes an accepted candidate when every encoded application path loops back to the owner target', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Dependent target']);
    $candidate = GovernmentId::create(['name' => 'All paths are circular']);
    $safe = Document::create(['name' => 'Independent accepted document']);
    suggestionGroup(suggestionSet($target), [['items' => [$candidate, $safe]]]);
    suggestionGroup(suggestionSet($candidate, ['application_type' => 'new']), [['items' => [$target]]]);
    suggestionGroup(suggestionSet($candidate, ['application_type' => 'renewal']), [['items' => [$target]]]);
    $options = suggestionPending(app(RoadmapPlanner::class)->build($resident, [$target->id]))['suggestions'];
    expect(array_column($options, 'name'))->toBe(['Independent accepted document']);
});

it('does not compare incomplete chain subtotals while further ID acquisition choices have unknown cost or time', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $a = GovernmentId::create(['name' => 'A own fees and time are lower', 'processing_time' => '2 days']);
    $b = GovernmentId::create(['name' => 'B own fees and time are higher', 'processing_time' => '5 days']);
    $first = GovernmentId::create(['name' => 'First additional ID with unknown facts']);
    $second = GovernmentId::create(['name' => 'Second additional ID with unknown facts']);
    suggestionFee($a, 100);
    suggestionFee($b, 200);
    suggestionGroup(suggestionSet($target), [['items' => [$a, $b]]]);
    suggestionGroup(suggestionSet($a), [['items' => [$first, $second]]]);
    suggestionGroup(suggestionSet($b), [['items' => [$first, $second]]]);
    $options = suggestionPending(app(RoadmapPlanner::class)->build($resident, [$target->id]))['suggestions'];
    expect($options)->toHaveCount(2);
    foreach ($options as $option) {
        expect($option['unresolved_choices'])->toBeGreaterThan(0)
            ->and($option['estimated_days'])->toBeNull()
            ->and($option['known_cost'])->toBeNull()
            ->and($option['recommendation_reasons'])->not->toContain('Shorter known processing time')->not->toContain('Lower known cost');
    }
});


it('does not advertise a partial accepted combination as needing no additional ID', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $other = GovernmentId::create(['name' => 'Other target']);
    $shared = Document::create(['name' => 'Shared evidence', 'processing_time' => '1 day']);
    $complete = Document::create(['name' => 'Complete accepted option', 'processing_time' => '3 days']);
    suggestionFee($shared, 10);
    suggestionGroup(suggestionSet($other), [['items' => [$shared]]]);
    suggestionGroup(suggestionSet($target), [
        ['count' => 2, 'items' => [$shared, 'Additional government-issued photo ID']],
        ['items' => [$complete]],
    ]);
    $options = suggestionPending(app(RoadmapPlanner::class)->build($resident, [$target->id, $other->id]))['suggestions'];
    expect($options[0]['name'])->toBe('Complete accepted option');
    $partial = collect($options)->firstWhere('name', 'Shared evidence');
    expect($partial['combination_remaining'])->toBe(1)
        ->and($partial['known_cost'])->toBeNull()
        ->and($partial['estimated_days'])->toBeNull()
        ->and($partial['recommendation_reasons'])->not->toContain('No additional ID required');
});

it('shortlists distinct acquisitions while retaining alternative combinations for the same record', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target']);
    $a = Document::create(['name' => 'A', 'application_process' => 'Documented steps']);
    $b = Document::create(['name' => 'B', 'application_process' => 'Documented steps']);
    $c = Document::create(['name' => 'C', 'application_process' => 'Documented steps']);
    $d = Document::create(['name' => 'D', 'application_process' => 'Documented steps']);
    suggestionGroup(suggestionSet($target), [
        ['items' => [$a, $b, $c, $d]], ['items' => [$a, $b]],
    ]);
    $options = suggestionPending(app(RoadmapPlanner::class)->build($resident, [$target->id]))['suggestions'];
    $recommended = array_values(array_filter($options, fn ($option) => $option['recommended']));
    expect($options)->toHaveCount(6)
        ->and(array_column($recommended, 'name'))->toBe(['A', 'B', 'C']);
});
