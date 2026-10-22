<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Services\PrerequisitePlanner;
use App\Support\ApplicantContext;
use App\Support\ProfileSelections;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->root = GovernmentId::create(['name' => 'Target A']);
    $this->child = GovernmentId::create(['name' => 'Prerequisite B']);
    $this->deep = GovernmentId::create(['name' => 'Prerequisite C']);
    $this->document = Document::create(['name' => 'Leaf document']);
    $this->rootProfile = $this->root->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $this->otherRootProfile = $this->root->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $this->childProfile = $this->child->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'custom', 'applicant_type_custom' => 'Students']);
    $this->deepProfile = $this->deep->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $this->otherDeepProfile = $this->deep->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $this->root->prerequisites()->create(['prerequisite_government_id_id' => $this->child->id]);
    $this->child->prerequisites()->create(['requirement_set_id' => $this->childProfile->id, 'prerequisite_government_id_id' => $this->deep->id, 'note' => 'PRIVATE CHILD NOTE']);
    $this->deep->prerequisites()->create(['requirement_set_id' => $this->deepProfile->id, 'prerequisite_document_id' => $this->document->id, 'note' => 'PRIVATE DEEP NOTE']);
    $this->url = '/api/government-ids/'.$this->root->id.'/sequence';
    $this->facts = ['age' => 25, 'application_type' => 'new'];
});

it('keeps legacy root responses identical when the root choice moves to the preferred map', function () {
    $legacy = $this->postJson($this->url, [...$this->facts, 'selected_profile_id' => $this->rootProfile->id])->assertOk()->json();
    $preferred = $this->postJson($this->url, [...$this->facts, 'profile_selections' => [$this->root->id => $this->rootProfile->id]])->assertOk()->json();
    $both = $this->postJson($this->url, [...$this->facts, 'selected_profile_id' => $this->rootProfile->id, 'profile_selections' => [$this->root->id => $this->rootProfile->id]])->assertOk()->json();
    expect($preferred)->toBe($legacy)->and($both)->toBe($legacy)
        ->and($preferred['data']['applicability']['selection_mode'])->toBe('manual');
});

it('resolves recursively and progressively without copying applicant facts or inferring profile labels', function () {
    $first = $this->postJson($this->url, $this->facts)->assertOk()
        ->assertJsonPath('data.status', 'manual_confirmation_required')->assertJsonPath('data.plan_is_complete', false)
        ->assertJsonPath('data.pending_applicability.0.government_id.id', $this->child->id)
        ->assertJsonPath('data.pending_applicability.0.government_id.name', $this->child->name)
        ->assertJsonPath('data.pending_applicability.0.applicability.manual_profiles.0.label', $this->childProfile->display_label)
        ->assertJsonPath('data.pending_applicability.0.applicability.missing_context', []);
    expect(array_column($first->json('data.plan'), 'id'))->toBe([$this->child->id, $this->root->id]);

    $second = $this->postJson($this->url, [...$this->facts, 'profile_selections' => [$this->child->id => $this->childProfile->id]])->assertOk()
        ->assertJsonPath('data.status', 'multiple_matches')->assertJsonPath('data.plan_is_complete', false)
        ->assertJsonCount(1, 'data.pending_applicability')
        ->assertJsonPath('data.pending_applicability.0.government_id.id', $this->deep->id)
        ->assertJsonCount(2, 'data.pending_applicability.0.applicability.matched_profiles');
    expect(array_column($second->json('data.plan'), 'id'))->toBe([$this->deep->id, $this->child->id, $this->root->id]);

    $third = $this->postJson($this->url, [...$this->facts, 'profile_selections' => [
        $this->child->id => $this->childProfile->id,
        $this->deep->id => $this->deepProfile->id,
    ]])->assertOk()->assertJsonPath('data.status', 'ready')->assertJsonPath('data.plan_is_complete', true)
        ->assertJsonPath('data.pending_applicability', [])->assertJsonPath('data.pending_prerequisites', [])
        ->assertJsonPath('data.missing_context', [])->assertJsonPath('data.applicant_context.age', 25)
        ->assertJsonPath('data.applicant_context.application_type', 'new');
    expect(array_column($third->json('data.plan'), 'name'))->toBe([$this->document->name, $this->deep->name, $this->child->name, $this->root->name])
        ->and(array_column($third->json('data.plan'), 'order'))->toBe([1, 2, 3, 4]);
});

it('returns missing context for the precise deeper ID and permits its explicit choice', function () {
    $input = ['profile_selections' => [$this->child->id => $this->childProfile->id]];
    $this->postJson($this->url, $input)->assertOk()
        ->assertJsonPath('data.status', 'insufficient_context')->assertJsonPath('data.plan_is_complete', false)
        ->assertJsonPath('data.missing_context', ['age', 'application_type'])
        ->assertJsonPath('data.pending_applicability.0.government_id.id', $this->deep->id)
        ->assertJsonPath('data.pending_applicability.0.applicability.missing_context', ['age', 'application_type']);
    $this->postJson($this->url, ['profile_selections' => [
        $this->child->id => $this->childProfile->id, $this->deep->id => $this->deepProfile->id,
    ]])->assertOk()->assertJsonPath('data.status', 'ready')->assertJsonPath('data.plan_is_complete', true)
        ->assertJsonPath('data.missing_context', [])->assertJsonPath('data.applicant_context.age', null);
});

it('resolves sibling IDs independently using their own selected profiles', function () {
    $sibling = GovernmentId::create(['name' => 'Sibling D']);
    $profile = $sibling->requirementSets()->create(['application_type' => 'custom', 'application_type_custom' => 'Other situation', 'applicant_type' => 'all']);
    $this->root->prerequisites()->create(['prerequisite_government_id_id' => $sibling->id, 'sort_order' => 10]);
    $sibling->prerequisites()->create(['requirement_set_id' => $profile->id, 'prerequisite_document_id' => $this->document->id]);
    $this->postJson($this->url, [...$this->facts, 'profile_selections' => [
        $this->child->id => $this->childProfile->id,
        $this->deep->id => $this->deepProfile->id,
    ]])->assertOk()->assertJsonPath('data.plan_is_complete', false)
        ->assertJsonPath('data.pending_applicability.0.government_id.id', $sibling->id);
    $result = $this->postJson($this->url, [...$this->facts, 'profile_selections' => [
        $sibling->id => $profile->id, $this->deep->id => $this->deepProfile->id, $this->child->id => $this->childProfile->id,
    ]])->assertOk()->assertJsonPath('data.plan_is_complete', true)->assertJsonCount(5, 'data.plan')->json('data');
    expect(array_column($result['plan'], 'name'))->toBe([$this->document->name, $this->deep->name, $this->child->name, $sibling->name, $this->root->name]);
});

it('keeps owned branches satisfied regardless of retained selections and prunes deeper decisions', function () {
    foreach ([[], [$this->child->id => $this->childProfile->id]] as $map) {
        $this->postJson($this->url, ['owned_government_id_ids' => [$this->child->id], 'profile_selections' => (object) $map])->assertOk()
            ->assertJsonPath('data.status', 'ready')->assertJsonPath('data.plan_is_complete', true)
            ->assertJsonCount(1, 'data.plan')->assertJsonPath('data.plan.0.id', $this->root->id)
            ->assertJsonPath('data.satisfied.0.id', $this->child->id)
            ->assertJsonPath('data.satisfied.0.status', 'satisfied')->assertJsonPath('data.pending_applicability', []);
    }
    $this->postJson($this->url, ['owned_government_id_ids' => [$this->deep->id], 'profile_selections' => [$this->child->id => $this->childProfile->id]])->assertOk()
        ->assertJsonPath('data.status', 'ready')->assertJsonPath('data.satisfied.0.id', $this->deep->id)->assertJsonCount(2, 'data.plan');
    $this->postJson($this->url, ['owned_document_ids' => [$this->document->id], 'profile_selections' => [
        $this->child->id => $this->childProfile->id, $this->deep->id => $this->deepProfile->id,
    ]])->assertOk()->assertJsonPath('data.status', 'ready')->assertJsonPath('data.satisfied.0.type', 'document')->assertJsonCount(3, 'data.plan');
});

it('rejects cross-owner nonexistent and conflicting selections with normal validation responses', function () {
    foreach ([
        [$this->child->id => $this->rootProfile->id],
        [$this->child->id => 999999],
        [999999 => $this->childProfile->id],
    ] as $map) {
        $id = array_key_first($map);
        $this->postJson($this->url, ['profile_selections' => $map])->assertUnprocessable()->assertJsonValidationErrors('profile_selections.'.$id);
    }
    foreach ([$this->otherRootProfile->id, null] as $legacy) {
        $this->postJson($this->url, ['selected_profile_id' => $legacy, 'profile_selections' => [$this->root->id => $this->rootProfile->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('selected_profile_id');
    }
    // A legacy root choice may be combined with independent child choices.
    $this->postJson($this->url, ['selected_profile_id' => $this->rootProfile->id, 'profile_selections' => [
        $this->child->id => $this->childProfile->id, $this->deep->id => $this->deepProfile->id,
    ]])->assertOk()->assertJsonPath('data.plan_is_complete', true);
});

it('validates selections even when ownership or an unvisited branch would otherwise bypass them', function () {
    $this->postJson($this->url, ['owned_government_id_ids' => [$this->child->id], 'profile_selections' => [$this->child->id => $this->rootProfile->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('profile_selections.'.$this->child->id);
    $this->postJson($this->url, ['profile_selections' => [$this->deep->id => $this->childProfile->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('profile_selections.'.$this->deep->id);
});

it('rejects invalid map shapes key aliases and value types', function (mixed $map) {
    $this->postJson($this->url, ['profile_selections' => $map])->assertUnprocessable();
})->with([
    [null], [true], ['invalid'], [42], [[]], [[11]],
    [(object) ['0' => 1]], [(object) ['035' => 1]], [(object) ['+35' => 1]], [(object) ['35.0' => 1]],
    [(object) ['35e0' => 1]], [(object) ['999999999999999999999999' => 1]],
    [(object) ['35' => '11']], [(object) ['35' => true]], [(object) ['35' => 11.5]],
    [(object) ['35' => null]], [(object) ['35' => []]], [(object) ['35' => 0]],
]);

it('rejects duplicate raw JSON keys instead of silently accepting PHP decoder overwrites', function (string $template) {
    $raw = str_replace(['OWNER', 'PROFILE'], [(string) $this->child->id, (string) $this->childProfile->id], $template);
    $this->call('POST', $this->url, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $raw)
        ->assertUnprocessable()->assertJsonValidationErrors('request');
})->with([
    '{"profile_selections":{"OWNER":PROFILE,"OWNER":PROFILE}}',
    '{"profile_selections":{"OWNER":PROFILE},"profile_selections":{}}',
    '{"selected_profile_id":null,"selected_profile_id":null}',
    // Literal and escaped property names must be recognized as the same JSON key.
    '{"profile_selections":{},"\u0070rofile_selections":{}}',
    '{"profile_selections":{"1":1,"\u0031":1}}',
]);

it('returns validation errors for malformed JSON and oversized maps', function () {
    $this->call('POST', $this->url, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{"profile_selections":')
        ->assertUnprocessable()->assertJsonValidationErrors('request');
    $this->postJson($this->url, ['profile_selections' => array_fill_keys(range(1, 501), 1)])
        ->assertUnprocessable()->assertJsonValidationErrors('profile_selections');
});

it('supports typed planner callers and validates ownership and conflicts at the service boundary', function () {
    $planner = app(PrerequisitePlanner::class);
    $choices = new ProfileSelections([$this->child->id => $this->childProfile->id, $this->deep->id => $this->deepProfile->id]);
    $result = $planner->plan($this->root, new ApplicantContext(25, 'new'), profileSelections: $choices);
    expect($result->resolutionStatus())->toBe('ready');
    expect(fn () => $planner->plan($this->root, new ApplicantContext, profileSelections: new ProfileSelections([$this->child->id => $this->rootProfile->id])))
        ->toThrow(ValidationException::class);
    expect(fn () => $planner->plan($this->root, new ApplicantContext, $this->rootProfile->id, new ProfileSelections([$this->root->id => $this->otherRootProfile->id])))
        ->toThrow(ValidationException::class);
});

it('retains deterministic public responses privacy and reference exclusion without writes', function () {
    $this->rootProfile->groups()->create(['rule' => 'all'])->items()->create(['type' => 'government_id', 'government_id_id' => $this->root->id]);
    $this->rootProfile->applicationSteps()->create(['title' => 'Preserved Guide']);
    $legacyUrl = '/api/government-ids/'.$this->root->id.'/applicability';
    $before = $this->postJson($legacyUrl, $this->facts)->assertOk()->json();
    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete|alter|create|drop)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $map = [$this->root->id => $this->rootProfile->id, $this->child->id => $this->childProfile->id, $this->deep->id => $this->deepProfile->id];
    $first = $this->postJson($this->url, [...$this->facts, 'profile_selections' => $map])->assertOk()->json();
    $second = $this->postJson($this->url, [...$this->facts, 'profile_selections' => array_reverse($map, true)])->assertOk()->json();
    expect($first)->toBe($second)
        ->and($first['data']['plan_is_complete'])->toBeTrue()
        ->and(count($first['data']['plan']))->toBe(4)
        ->and(json_encode($first))->not->toContain('PRIVATE', 'note', 'requirement_set_id', 'prerequisite_id', 'trace', 'profile_scope_id');
    expect($this->postJson($legacyUrl, $this->facts)->assertOk()->json())->toBe($before);
    // The new map is deliberately not accepted by the unchanged applicability API.
    $this->postJson($legacyUrl, ['profile_selections' => $map])->assertUnprocessable()->assertJsonValidationErrors('profile_selections');
    expect($writes)->toBe([]);
});
