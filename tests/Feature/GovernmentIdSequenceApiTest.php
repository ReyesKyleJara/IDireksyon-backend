<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdPrerequisite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->target = GovernmentId::create(['name' => 'Target license', 'requirements' => 'Preserved legacy evidence']);
    $this->permit = GovernmentId::create(['name' => 'Student permit']);
    $this->document = Document::create(['name' => 'Birth certificate']);
    $this->adult = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $this->minor = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'minor']);
    $this->renewal = $this->target->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'all']);
    $this->url = '/api/government-ids/'.$this->target->id.'/sequence';
});

function sequenceApiLink(GovernmentId $owner, GovernmentId|Document $record, array $attributes = []): GovernmentIdPrerequisite
{
    return $owner->prerequisites()->create([
        $record instanceof Document ? 'prerequisite_document_id' : 'prerequisite_government_id_id' => $record->id,
        ...$attributes,
    ]);
}

it('returns a public target-only ready plan without asking about irrelevant profiles', function () {
    $this->postJson($this->url, [])->assertOk()->assertExactJson(['data' => [
        'government_id' => ['id' => $this->target->id, 'name' => $this->target->name],
        'applicant_context' => ['age' => null, 'application_type' => null, 'owned_government_id_ids' => [], 'owned_document_ids' => []],
        'status' => 'ready', 'plan_is_complete' => true,
        'plan' => [[
            'type' => 'government_id', 'id' => $this->target->id, 'name' => $this->target->name,
            'status' => 'target', 'order' => 1, 'depth' => 0, 'is_target' => true,
        ]],
        'satisfied' => [], 'dependencies' => [], 'applicability' => null, 'pending_applicability' => [],
        'pending_prerequisites' => [], 'missing_context' => [], 'error' => null,
    ]]);
    $this->getJson($this->url)->assertMethodNotAllowed();
});

it('orders missing Everyone prerequisites before the target', function (string $type) {
    $record = $type === 'government_id' ? $this->permit : $this->document;
    sequenceApiLink($this->target, $record);
    $this->postJson($this->url, [])->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.plan.0', ['type' => $type, 'id' => $record->id, 'name' => $record->name, 'status' => 'missing', 'order' => 1, 'depth' => 1, 'is_target' => false])
        ->assertJsonPath('data.plan.1.id', $this->target->id)
        ->assertJsonPath('data.plan.1.status', 'target')
        ->assertJsonPath('data.dependencies.0.target.id', $this->target->id)
        ->assertJsonPath('data.dependencies.0.prerequisite.type', $type)
        ->assertJsonPath('data.dependencies.0.applicability_profile', null);
})->with(['government_id', 'document']);

it('separates satisfied prerequisites from actionable work', function (string $type) {
    $record = $type === 'government_id' ? $this->permit : $this->document;
    sequenceApiLink($this->target, $record);
    $field = $type === 'government_id' ? 'owned_government_id_ids' : 'owned_document_ids';
    $this->postJson($this->url, [$field => [$record->id]])->assertOk()
        ->assertJsonPath('data.status', 'ready')->assertJsonCount(1, 'data.plan')
        ->assertJsonPath('data.plan.0.status', 'target')
        ->assertJsonPath('data.satisfied.0', ['type' => $type, 'id' => $record->id, 'name' => $record->name, 'status' => 'satisfied', 'order' => null, 'depth' => 1, 'is_target' => false])
        ->assertJsonCount(1, 'data.dependencies');
})->with(['government_id', 'document']);

it('prunes owned ID branches and marks an owned target satisfied without new work', function () {
    sequenceApiLink($this->target, $this->permit);
    sequenceApiLink($this->permit, $this->document);
    $this->postJson($this->url, ['owned_government_id_ids' => [$this->permit->id]])->assertOk()
        ->assertJsonCount(1, 'data.plan')->assertJsonCount(1, 'data.satisfied')->assertJsonCount(1, 'data.dependencies');
    $this->postJson($this->url, ['owned_government_id_ids' => [$this->target->id]])->assertOk()
        ->assertJsonPath('data.plan', [])->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.satisfied.0.is_target', true)->assertJsonPath('data.satisfied.0.status', 'satisfied')
        ->assertJsonPath('data.dependencies', []);
});

it('preserves recursive ordering edge metadata and deduplication deterministically', function () {
    $other = GovernmentId::create(['name' => 'Other prerequisite']);
    sequenceApiLink($this->target, $other, ['sort_order' => 20]);
    sequenceApiLink($this->target, $this->permit, ['sort_order' => 5]);
    sequenceApiLink($other, $this->document);
    sequenceApiLink($this->permit, $this->document);
    $response = $this->postJson($this->url, [])->assertOk()->assertJsonCount(4, 'data.dependencies')->json();
    expect(array_column($response['data']['plan'], 'name'))->toBe([$this->document->name, $this->permit->name, $other->name, $this->target->name])
        ->and(array_column($response['data']['plan'], 'order'))->toBe([1, 2, 3, 4])
        ->and($response['data']['plan'][0]['depth'])->toBe(2);
    expect($this->postJson($this->url, [])->assertOk()->json())->toBe($response);
});

it('uses the existing matched profile and excludes a prerequisite for a nonmatching profile', function () {
    sequenceApiLink($this->target, $this->permit, ['requirement_set_id' => $this->adult->id]);
    $this->postJson($this->url, ['age' => 20, 'application_type' => 'new'])->assertOk()
        ->assertJsonPath('data.status', 'ready')->assertJsonCount(2, 'data.plan')
        ->assertJsonPath('data.applicability.selected_profile.id', $this->adult->id)
        ->assertJsonPath('data.dependencies.0.applicability_profile.id', $this->adult->id)
        ->assertJsonPath('data.dependencies.0.applicability_profile.label', $this->adult->display_label);
    $this->postJson($this->url, ['age' => 17, 'application_type' => 'new'])->assertOk()
        ->assertJsonPath('data.status', 'ready')->assertJsonCount(1, 'data.plan')
        ->assertJsonPath('data.applicability.selected_profile.id', $this->minor->id)->assertJsonPath('data.dependencies', []);
});

it('reports no match when profile-scoped prerequisite coverage cannot be resolved for supplied facts', function () {
    sequenceApiLink($this->target, $this->permit, ['requirement_set_id' => $this->adult->id]);
    $this->postJson($this->url, ['age' => 20, 'application_type' => 'replacement'])->assertOk()
        ->assertJsonPath('data.status', 'no_match')->assertJsonPath('data.plan_is_complete', false)
        ->assertJsonPath('data.missing_context', [])->assertJsonPath('data.applicability.selected_profile', null)
        ->assertJsonCount(1, 'data.plan')->assertJsonPath('data.pending_applicability.0.applicability.status', 'no_match');
});

it('returns only normalized missing facts and preserves known Everyone content in a provisional plan', function () {
    sequenceApiLink($this->target, $this->permit, ['requirement_set_id' => $this->adult->id, 'note' => 'PRIVATE pending rationale']);
    sequenceApiLink($this->target, $this->document);
    $this->postJson($this->url, ['application_type' => 'new'])->assertOk()
        ->assertJsonPath('data.status', 'insufficient_context')->assertJsonPath('data.plan_is_complete', false)
        ->assertJsonPath('data.missing_context', ['age'])
        ->assertJsonPath('data.applicability.missing_context', ['age'])
        ->assertJsonPath('data.applicant_context.application_type', 'new')
        ->assertJsonPath('data.plan.0.type', 'document')
        ->assertJsonPath('data.pending_prerequisites.0.prerequisite.id', $this->permit->id)
        ->assertJsonPath('data.pending_prerequisites.0.applicability_profile.id', $this->adult->id)
        ->assertDontSee('PRIVATE pending rationale');
    $this->postJson($this->url, [])->assertOk()->assertJsonPath('data.missing_context', ['age', 'application_type']);
});

it('returns every overlapping match without choosing the first and supports explicit selection', function () {
    $all = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    sequenceApiLink($this->target, $this->permit, ['requirement_set_id' => $this->adult->id]);
    sequenceApiLink($this->target, $this->document, ['requirement_set_id' => $all->id]);
    $facts = ['age' => 20, 'application_type' => 'new'];
    $this->postJson($this->url, $facts)->assertOk()
        ->assertJsonPath('data.status', 'multiple_matches')->assertJsonPath('data.plan_is_complete', false)
        ->assertJsonPath('data.applicability.selected_profile', null)->assertJsonCount(2, 'data.applicability.matched_profiles')
        ->assertJsonCount(2, 'data.pending_prerequisites')->assertJsonPath('data.dependencies', []);
    $this->postJson($this->url, [...$facts, 'selected_profile_id' => $all->id])->assertOk()
        ->assertJsonPath('data.status', 'ready')->assertJsonPath('data.applicability.selection_mode', 'manual')
        ->assertJsonPath('data.applicability.selected_profile.id', $all->id)->assertJsonPath('data.plan.0.type', 'document');
});

it('keeps custom labels unresolved and resolves an explicitly selected manual profile', function () {
    $custom = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'custom', 'applicant_type_custom' => 'Students age 18+', 'min_age' => 18]);
    sequenceApiLink($this->target, $this->permit, ['requirement_set_id' => $custom->id]);
    $this->postJson($this->url, ['age' => 20, 'application_type' => 'new'])->assertOk()
        ->assertJsonPath('data.status', 'manual_confirmation_required')
        ->assertJsonPath('data.applicability.selected_profile', null)
        ->assertJsonPath('data.applicability.manual_profiles.0.id', $custom->id)
        ->assertJsonPath('data.applicability.manual_profiles.0.matching_mode', 'manual_only')
        ->assertJsonPath('data.pending_prerequisites.0.prerequisite.id', $this->permit->id);
    $this->postJson($this->url, ['selected_profile_id' => $custom->id])->assertOk()
        ->assertJsonPath('data.status', 'ready')->assertJsonPath('data.missing_context', [])
        ->assertJsonPath('data.applicability.selected_profile.label', $custom->display_label)
        ->assertJsonPath('data.applicability.selection_mode', 'manual')->assertJsonPath('data.plan.0.id', $this->permit->id);
});

it('identifies recursive owners needing context without misapplying the root manual choice', function () {
    sequenceApiLink($this->target, $this->permit);
    $childProfile = $this->permit->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    sequenceApiLink($this->permit, $this->document, ['requirement_set_id' => $childProfile->id]);
    $this->postJson($this->url, ['application_type' => 'new', 'selected_profile_id' => $this->adult->id])->assertOk()
        ->assertJsonPath('data.status', 'insufficient_context')->assertJsonPath('data.missing_context', ['age'])
        ->assertJsonPath('data.applicability.selected_profile.id', $this->adult->id)
        ->assertJsonPath('data.pending_applicability.0.government_id.id', $this->permit->id)
        ->assertJsonPath('data.pending_applicability.0.applicability.available_profiles.0.id', $childProfile->id);
    $this->postJson($this->url, ['selected_profile_id' => $childProfile->id])->assertUnprocessable()->assertJsonValidationErrors('selected_profile_id');
});

it('uses deterministic state precedence across multiple unresolved ID branches', function () {
    sequenceApiLink($this->target, $this->permit);
    sequenceApiLink($this->target, $this->document, ['requirement_set_id' => $this->adult->id]);
    $child = $this->permit->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'custom', 'applicant_type_custom' => 'Other situation']);
    sequenceApiLink($this->permit, $this->document, ['requirement_set_id' => $child->id]);
    $this->postJson($this->url, ['application_type' => 'new'])->assertOk()
        ->assertJsonPath('data.status', 'insufficient_context')->assertJsonCount(2, 'data.pending_applicability');
    $this->postJson($this->url, ['age' => 20, 'application_type' => 'new'])->assertOk()
        ->assertJsonPath('data.status', 'manual_confirmation_required')->assertJsonCount(1, 'data.pending_applicability');
});

it('does not turn normal requirement references OR ways quantities or Choose-N evidence into dependencies', function () {
    foreach (['all', 'choose_one'] as $rule) {
        $group = $this->adult->groups()->create(['rule' => $rule, 'condition_type' => 'custom', 'condition_custom' => 'Other situation']);
        foreach ([2, 1] as $count) {
            $way = $group->ways()->create(['required_count' => $count]);
            foreach ([['type' => 'government_id', 'government_id_id' => $this->permit->id], ['type' => 'document', 'document_id' => $this->document->id]] as $reference) {
                $item = $group->items()->make([...$reference, 'quantity' => 2, 'copies' => 3]);
                $item->way()->associate($way);
                $item->save();
            }
        }
    }
    $this->postJson($this->url, ['age' => 20, 'application_type' => 'new'])->assertOk()
        ->assertJsonPath('data.status', 'ready')->assertJsonCount(1, 'data.plan')->assertJsonPath('data.dependencies', []);
    sequenceApiLink($this->target, $this->permit);
    $this->postJson($this->url, [])->assertOk()->assertJsonCount(2, 'data.plan')->assertJsonPath('data.plan.0.id', $this->permit->id)
        ->assertJsonCount(1, 'data.dependencies');
});

it('returns a controlled configuration error and logs diagnostics without leaking them publicly', function () {
    sequenceApiLink($this->target, $this->permit, ['note' => 'PRIVATE maintenance rationale']);
    // Legacy/bulk corruption bypasses the supported maintenance model's cycle validator.
    DB::table('government_id_prerequisites')->insert(['government_id_id' => $this->permit->id, 'prerequisite_government_id_id' => $this->target->id]);
    Log::shouldReceive('warning')->once()->with('Government ID prerequisite configuration error.', Mockery::on(function ($context) {
        return $context['government_id_id'] === $this->target->id
            && str_contains(json_encode($context['errors']), 'Circular')
            && ! str_contains(json_encode($context), 'PRIVATE')
            && ! isset($context['applicant_context']);
    }));
    $response = $this->postJson($this->url, ['age' => 20, 'application_type' => 'new'])->assertOk()
        ->assertJsonPath('data.status', 'configuration_error')->assertJsonPath('data.plan_is_complete', false)
        ->assertJsonPath('data.plan', [])->assertJsonPath('data.dependencies', [])
        ->assertJsonPath('data.error.code', 'invalid_prerequisite_configuration')->json();
    expect(json_encode($response))->not->toContain('Circular', 'PRIVATE', 'SQLSTATE', 'trace', 'exception', 'prerequisite_id');
});

it('redacts maintainer rationale and internal prerequisite metadata from resolved and pending plans', function () {
    sequenceApiLink($this->target, $this->document, ['note' => '<b>PRIVATE resolved note</b>']);
    sequenceApiLink($this->target, $this->permit, ['requirement_set_id' => $this->adult->id, 'note' => 'PRIVATE pending note']);
    foreach ([[], ['age' => 20, 'application_type' => 'new']] as $facts) {
        $data = $this->postJson($this->url, $facts)->assertOk()->json('data');
        expect(json_encode($data))->not->toContain('PRIVATE', 'note', 'sort_order', 'requirement_set_id', 'prerequisite_id', 'profile_scope_id', 'created_at', 'updated_at');
    }
});

it('rejects foreign profiles missing targets duplicate ownership and nonexistent references', function () {
    $other = $this->permit->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    foreach ([
        ['selected_profile_id' => $other->id],
        ['owned_government_id_ids' => [$this->permit->id, $this->permit->id]],
        ['owned_document_ids' => [$this->document->id, $this->document->id]],
    ] as $input) {
        $this->postJson($this->url, $input)->assertUnprocessable();
    }
    $this->postJson('/api/government-ids/999999/sequence', [])->assertNotFound();
});

it('reuses strict applicability validation without accepting invented sequencing facts', function (array $input, string $field) {
    $this->postJson($this->url, $input)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['age' => '18'], 'age'], [['age' => true], 'age'], [['age' => 18.5], 'age'], [['age' => -1], 'age'], [['age' => 65536], 'age'],
    [['application_type' => 'invented'], 'application_type'], [['application_type' => true], 'application_type'],
    [['owned_government_id_ids' => [999999]], 'owned_government_id_ids.0'], [['owned_document_ids' => [999999]], 'owned_document_ids.0'],
    [['owned_government_id_ids' => ['1']], 'owned_government_id_ids.0'], [['owned_document_ids' => [true]], 'owned_document_ids.0'],
    [['owned_document_ids' => null], 'owned_document_ids'], [['owned_document_ids' => ['key' => 1]], 'owned_document_ids'],
    [['selected_profile_id' => '1'], 'selected_profile_id'], [['selected_profile_id' => 999999], 'selected_profile_id'],
    [['applicant_type' => 'adult'], 'applicant_type'], [['prerequisites' => []], 'prerequisites'], [['selected_profiles' => []], 'selected_profiles'],
]);

it('normalizes the shared context contract without persisting facts or changing existing API responses', function () {
    sequenceApiLink($this->target, $this->permit);
    $this->adult->groups()->create(['rule' => 'all'])->items()->create(['type' => 'document', 'document_id' => $this->document->id]);
    $this->adult->applicationSteps()->create(['title' => 'Preserved guide']);
    $paths = ['/api/government-ids', '/api/government-ids/'.$this->target->id, '/api/government-ids/'.$this->target->id.'/content'];
    $before = array_map(fn ($path) => $this->getJson($path)->assertOk()->json(), $paths);
    $applicabilityUrl = '/api/government-ids/'.$this->target->id.'/applicability';
    $facts = ['age' => 20, 'application_type' => 'new'];
    $applicability = $this->postJson($applicabilityUrl, $facts)->assertOk()->json();
    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete|alter|create|drop)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $this->postJson($this->url, [...$facts, 'owned_government_id_ids' => [$this->permit->id, $this->target->id], 'owned_document_ids' => [$this->document->id]])->assertOk()
        ->assertJsonPath('data.applicant_context.owned_government_id_ids', [$this->target->id, $this->permit->id])
        ->assertJsonPath('data.applicant_context.owned_document_ids', [$this->document->id]);
    $this->postJson($this->url, $facts)->assertOk();
    foreach ($paths as $index => $path) {
        expect($this->getJson($path)->assertOk()->json())->toBe($before[$index]);
    }
    expect($this->postJson($applicabilityUrl, $facts)->assertOk()->json())->toBe($applicability);
    expect($writes)->toBe([]);
    $this->getJson('/api/auth/me')->assertUnauthorized();
});
