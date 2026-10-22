<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Services\GovernmentIdApplicability;
use App\Support\ApplicantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->target = GovernmentId::create(['name' => 'Applicability ID', 'requirements' => 'Legacy content']);
    $this->adult = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $this->minor = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'minor']);
    $this->renewal = $this->target->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'all']);
    $this->shared = $this->target->applicationSteps()->create(['title' => 'Book appointment', 'sort_order' => 0]);
    $this->adultStep = $this->adult->applicationSteps()->create(['title' => 'Adult instructions', 'sort_order' => 1]);
    $this->minorStep = $this->minor->applicationSteps()->create(['title' => 'Minor appearance', 'sort_order' => 1]);
    $this->renewalStep = $this->renewal->applicationSteps()->create(['title' => 'Bring old passport', 'sort_order' => 2]);
    foreach ([$this->adult, $this->minor, $this->renewal] as $profile) {
        $profile->groups()->create(['title' => 'Required evidence', 'rule' => 'all'])->items()->create(['type' => 'custom', 'custom_name' => 'Photo']);
    }
    $this->url = '/api/government-ids/'.$this->target->id.'/applicability';
});

it('automatically matches adult and minor profiles and their shared guide context', function (int $age, string $profileName, string $stepName) {
    $profile = $this->$profileName;
    $specific = $this->$stepName;
    $this->postJson($this->url, ['age' => $age, 'application_type' => 'new'])->assertOk()
        ->assertJsonPath('data.applicant_context.age', $age)
        ->assertJsonPath('data.applicability.status', 'matched')
        ->assertJsonPath('data.applicability.selection_mode', 'automatic')
        ->assertJsonPath('data.applicability.has_single_automatic_match', true)
        ->assertJsonPath('data.applicability.has_multiple_automatic_matches', false)
        ->assertJsonPath('data.applicability.requires_manual_confirmation', false)
        ->assertJsonPath('data.applicability.selected_profile.id', $profile->id)
        ->assertJsonPath('data.requirements.profiles.0.id', $profile->id)
        ->assertJsonPath('data.requirements.profiles.0.groups.0.items', null)
        ->assertJsonPath('data.requirements.pending_profiles', [])
        ->assertJsonPath('data.application_guide.steps.0.id', $this->shared->id)
        ->assertJsonPath('data.application_guide.steps.0.applicability_profile_id', null)
        ->assertJsonPath('data.application_guide.steps.1.id', $specific->id)
        ->assertJsonPath('data.application_guide.steps.1.applicability_profile_id', $profile->id)
        ->assertJsonCount(2, 'data.application_guide.steps');
})->with([[18, 'adult', 'adultStep'], [17, 'minor', 'minorStep'], [0, 'minor', 'minorStep']]);

it('returns renewal instructions only for renewal and preserves block contents', function () {
    $block = $this->renewalStep->blocks()->create(['type' => 'text', 'content' => ['body' => 'Bring the original.'], 'sort_order' => 2]);
    $this->postJson($this->url, ['application_type' => 'renewal'])->assertOk()
        ->assertJsonPath('data.applicability.selected_profile.id', $this->renewal->id)
        ->assertJsonPath('data.applicability.missing_context', [])
        ->assertJsonPath('data.application_guide.steps.1.id', $this->renewalStep->id)
        ->assertJsonPath('data.application_guide.steps.1.blocks.0.id', $block->id)
        ->assertJsonPath('data.application_guide.steps.1.blocks.0.content.body', 'Bring the original.');
    $result = $this->postJson($this->url, ['age' => 20, 'application_type' => 'new'])->assertOk()->json('data');
    expect(array_column($result['application_guide']['steps'], 'id'))->not->toContain($this->renewalStep->id);
});

it('reports missing age without guessing and retains pending structures and global steps', function () {
    $this->postJson($this->url, ['application_type' => 'new'])->assertOk()
        ->assertJsonPath('data.applicability.status', 'insufficient_context')
        ->assertJsonPath('data.applicability.selection_mode', 'none')
        ->assertJsonPath('data.applicability.missing_context', ['age'])
        ->assertJsonPath('data.applicability.requires_manual_confirmation', true)
        ->assertJsonPath('data.applicability.selected_profile', null)
        ->assertJsonPath('data.applicability.matched_profiles', [])
        ->assertJsonCount(2, 'data.applicability.pending_profiles')
        ->assertJsonPath('data.requirements.profiles', [])
        ->assertJsonCount(2, 'data.requirements.pending_profiles')
        ->assertJsonPath('data.application_guide.steps.0.id', $this->shared->id)
        ->assertJsonCount(1, 'data.application_guide.steps')
        ->assertJsonCount(2, 'data.application_guide.pending_steps');
    $this->postJson($this->url, [])->assertOk()
        ->assertJsonPath('data.applicability.missing_context', ['age', 'application_type']);
});

it('reports all overlapping matches without choosing one and keeps undecided profiles visible', function () {
    $all = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $allStep = $all->applicationSteps()->create(['title' => 'Other applicable instruction', 'sort_order' => 1]);
    $this->postJson($this->url, ['age' => 20, 'application_type' => 'new'])->assertOk()
        ->assertJsonPath('data.applicability.status', 'multiple_matches')
        ->assertJsonPath('data.applicability.has_multiple_automatic_matches', true)
        ->assertJsonPath('data.applicability.has_single_automatic_match', false)
        ->assertJsonPath('data.applicability.selected_profile', null)
        ->assertJsonCount(2, 'data.applicability.matched_profiles')
        ->assertJsonCount(2, 'data.requirements.profiles')
        ->assertJsonPath('data.application_guide.steps.2.id', $allStep->id);
    $this->postJson($this->url, ['application_type' => 'new'])->assertOk()
        ->assertJsonPath('data.applicability.status', 'insufficient_context')
        ->assertJsonPath('data.applicability.has_single_automatic_match', true)
        ->assertJsonPath('data.applicability.selected_profile', null);
});

it('preserves custom profiles as manual options and never interprets their labels', function () {
    $custom = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'custom', 'applicant_type_custom' => 'Adult', 'min_age' => 18]);
    $customStep = $custom->applicationSteps()->create(['title' => 'Manual instructions']);
    $group = $custom->groups()->create(['rule' => 'all', 'condition_type' => 'custom', 'condition_custom' => 'Other situation']);
    $group->items()->create(['type' => 'custom', 'custom_name' => 'Manual evidence']);
    $this->postJson($this->url, ['age' => 20, 'application_type' => 'new'])->assertOk()
        ->assertJsonPath('data.applicability.status', 'manual_confirmation_required')
        ->assertJsonPath('data.applicability.has_single_automatic_match', true)
        ->assertJsonPath('data.applicability.selected_profile', null)
        ->assertJsonPath('data.applicability.matched_profiles.0.id', $this->adult->id)
        ->assertJsonCount(1, 'data.applicability.matched_profiles')
        ->assertJsonPath('data.applicability.manual_profiles.0.id', $custom->id)
        ->assertJsonPath('data.applicability.manual_profiles.0.matching_mode', 'manual_only')
        ->assertJsonPath('data.requirements.pending_profiles.0.groups.0.condition_label', 'Other situation')
        ->assertJsonPath('data.application_guide.pending_steps.0.id', $customStep->id);
    $this->postJson($this->url, ['selected_profile_id' => $custom->id])->assertOk()
        ->assertJsonPath('data.applicability.status', 'manual_selected')
        ->assertJsonPath('data.applicability.selection_mode', 'manual')
        ->assertJsonPath('data.applicability.selected_profile.id', $custom->id)
        ->assertJsonPath('data.applicability.selected_profile.matching_mode', 'manual_only')
        ->assertJsonPath('data.applicability.requires_manual_confirmation', false)
        ->assertJsonPath('data.applicability.missing_context', [])
        ->assertJsonPath('data.applicability.matched_profiles', [])
        ->assertJsonPath('data.requirements.profiles.0.id', $custom->id)
        ->assertJsonPath('data.requirements.profiles.0.groups.0.ways.0.items.0.name', 'Manual evidence')
        ->assertJsonPath('data.application_guide.steps.1.id', $customStep->id)
        ->assertJsonPath('data.application_guide.pending_steps', []);
});

it('distinguishes explicit structured profile selection from automatic matching', function () {
    $this->postJson($this->url, ['age' => 20, 'application_type' => 'new', 'selected_profile_id' => $this->minor->id])->assertOk()
        ->assertJsonPath('data.applicability.selection_mode', 'manual')
        ->assertJsonPath('data.applicability.selected_profile.id', $this->minor->id)
        ->assertJsonPath('data.applicability.matched_profiles.0.id', $this->adult->id)
        ->assertJsonPath('data.requirements.profiles.0.id', $this->minor->id)
        ->assertJsonPath('data.application_guide.steps.1.id', $this->minorStep->id);
});

it('rejects profiles owned by another ID in both HTTP and service calls', function () {
    $other = GovernmentId::create(['name' => 'Other target']);
    $profile = $other->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $this->postJson($this->url, ['selected_profile_id' => $profile->id])->assertUnprocessable()->assertJsonValidationErrors('selected_profile_id');
    expect(fn () => app(GovernmentIdApplicability::class)->evaluate($this->target, new ApplicantContext, $profile->id))->toThrow(ModelNotFoundException::class);
    $this->postJson('/api/government-ids/999999/applicability', [])->assertNotFound();
});

it('validates strict input types reference existence duplicates and unknown facts', function (array $input, string $field) {
    $this->postJson($this->url, $input)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['age' => '18'], 'age'], [['age' => 18.5], 'age'], [['age' => true], 'age'],
    [['age' => -1], 'age'], [['age' => 65536], 'age'],
    [['application_type' => 'invented'], 'application_type'], [['application_type' => true], 'application_type'],
    [['owned_government_id_ids' => [999999]], 'owned_government_id_ids.0'],
    [['owned_document_ids' => [999999]], 'owned_document_ids.0'],
    [['owned_government_id_ids' => ['1']], 'owned_government_id_ids.0'],
    [['owned_document_ids' => [1.5]], 'owned_document_ids.0'],
    [['owned_document_ids' => [true]], 'owned_document_ids.0'],
    [['owned_document_ids' => null], 'owned_document_ids'],
    [['owned_document_ids' => ['unexpected' => 1]], 'owned_document_ids'],
    [['selected_profile_id' => '1'], 'selected_profile_id'],
    [['selected_profile_id' => 999999], 'selected_profile_id'],
    [['name' => 'Unnecessary personal data'], 'name'],
    [['applicant_type' => 'adult'], 'applicant_type'],
]);

it('normalizes valid ownership references and rejects duplicates', function () {
    $other = GovernmentId::create(['name' => 'Owned ID']);
    $document = Document::create(['name' => 'Owned document']);
    $this->postJson($this->url, ['owned_government_id_ids' => [$other->id, $this->target->id], 'owned_document_ids' => [$document->id]])->assertOk()
        ->assertJsonPath('data.applicant_context.owned_government_id_ids', [$this->target->id, $other->id])
        ->assertJsonPath('data.applicant_context.owned_document_ids', [$document->id]);
    $this->postJson($this->url, ['owned_government_id_ids' => [$other->id, $other->id]])->assertUnprocessable()->assertJsonValidationErrors('owned_government_id_ids.0');
    $this->postJson($this->url, ['owned_document_ids' => [$document->id, $document->id]])->assertUnprocessable()->assertJsonValidationErrors('owned_document_ids.0');
});

it('preserves requirements alternatives quantities references and conditional groups exactly', function () {
    $document = Document::create(['name' => 'Supporting certificate']);
    $group = $this->adult->groups()->create(['title' => 'Identity evidence', 'rule' => 'choose_one', 'condition_type' => 'spouse_surname', 'sort_order' => 2]);
    $way = $group->ways()->create(['required_count' => 2, 'qualification_type' => 'unexpired']);
    foreach ([['type' => 'document', 'document_id' => $document->id], ['type' => 'government_id', 'government_id_id' => $this->target->id], ['type' => 'custom', 'custom_name' => 'Proof']] as $fields) {
        $item = $group->items()->make([...$fields, 'quantity' => 2, 'copies' => 3, 'submission_format' => 'photocopy']);
        $item->way()->associate($way);
        $item->save();
    }
    $alternative = $group->ways()->create(['required_count' => 1, 'sort_order' => 1]);
    $item = $group->items()->make(['type' => 'custom', 'custom_name' => 'Alternative']);
    $item->way()->associate($alternative);
    $item->save();
    $expected = $this->getJson('/api/government-ids/'.$this->target->id)->assertOk()->json('data.requirement_sets.0');
    $this->postJson($this->url, ['age' => 20, 'application_type' => 'new'])->assertOk()
        ->assertJsonPath('data.requirements.profiles.0', $expected);
});

it('keeps global content when no profiles match or no profiles exist', function () {
    $this->postJson($this->url, ['application_type' => 'replacement'])->assertOk()
        ->assertJsonPath('data.applicability.status', 'no_match')
        ->assertJsonPath('data.applicability.missing_context', [])
        ->assertJsonPath('data.requirements.profiles', [])
        ->assertJsonPath('data.application_guide.steps.0.id', $this->shared->id);
    $empty = GovernmentId::create(['name' => 'No profiles']);
    $step = $empty->applicationSteps()->create(['title' => 'Common instructions']);
    $this->postJson('/api/government-ids/'.$empty->id.'/applicability', [])->assertOk()
        ->assertJsonPath('data.applicability.status', 'no_match')
        ->assertJsonPath('data.applicability.available_profiles', [])
        ->assertJsonPath('data.application_guide.steps.0.id', $step->id);
});

it('leaves legacy endpoints and all records unchanged and exposes no model metadata', function () {
    $paths = ['/api/government-ids', '/api/government-ids/'.$this->target->id, '/api/government-ids/'.$this->target->id.'/content'];
    $before = array_map(fn ($path) => $this->getJson($path)->assertOk()->json(), $paths);
    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete|alter|create|drop)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $result = $this->postJson($this->url, ['age' => 20, 'application_type' => 'new'])->assertOk()->json('data');
    foreach ($paths as $index => $path) {
        expect($this->getJson($path)->assertOk()->json())->toBe($before[$index]);
    }
    expect($writes)->toBe([])
        ->and(json_encode($result))->not->toContain('created_at', 'updated_at', 'last_verified_by', 'password', 'client_key', 'government_id_id_foreign');
});
