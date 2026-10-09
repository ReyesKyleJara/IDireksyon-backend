<?php

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdApplicationStep;
use App\Models\GovernmentIdRequirementGroup;
use App\Models\GovernmentIdRequirementItem;
use App\Models\GovernmentIdRequirementSet;
use App\Models\Office;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $this->document = Document::create(['name' => 'Supporting certificate']);
    $this->referenceId = GovernmentId::create(['name' => 'Accepted ID']);
    $this->office = Office::create(['name' => 'Application branch']);
    $documentItem = ['id' => null, 'type' => 'document', 'document_id' => $this->document->id, 'submission_format' => 'original_photocopy', 'copies' => 1];
    $idItem = ['id' => null, 'type' => 'government_id', 'government_id_id' => $this->referenceId->id, 'submission_format' => 'original'];
    $way = ['id' => null, 'required_count' => 1, 'qualification_type' => 'none', 'qualification_scope' => 'every'];
    $this->checklist = [
        'client_key' => 'checklist-1', 'application_type' => 'new', 'applicant_type' => 'adult',
        'groups' => [
            ['rule' => 'all', 'condition_type' => 'always', 'ways' => [array_merge($way, ['items' => [$documentItem]])]],
            ['title' => 'Accepted proof', 'rule' => 'all', 'condition_type' => 'always', 'ways' => [array_merge($way, ['qualification_type' => 'unexpired', 'items' => [$idItem, $documentItem]])]],
            ['title' => 'Additional photo', 'rule' => 'all', 'condition_type' => 'custom', 'condition_custom' => 'Only when requested', 'ways' => [array_merge($way, ['items' => [
                ['type' => 'custom', 'custom_name' => '2x2 photo', 'quantity' => 2, 'submission_format' => 'not_specified', 'instructions' => 'White background'],
            ]])]],
        ],
    ];
    $this->guide = [
        'client_key' => 'checklist-1', 'application_type' => 'new', 'applicant_type' => 'adult',
        'steps' => [['title' => 'Prepare the listed requirements', 'type' => 'general', 'blocks' => []]],
    ];
    $this->payload = [
        'name' => 'Complete new ID', 'description' => 'Entered before saving',
        'requirements_payload' => json_encode([$this->checklist]),
        'application_guide_payload' => json_encode([$this->guide]),
        'fees' => [['label' => 'Application', 'type' => 'fixed', 'amount_min' => 100, 'is_optional' => false]],
        'office_links_present' => 1,
        'office_links' => [['office_id' => $this->office->id, 'new_application_status' => 'available', 'renewal_status' => 'unknown', 'replacement_status' => 'unknown']],
    ];
});

it('creates requirements choices conditions and guide steps together with the ID', function () {
    $response = $this->post('/admin/government-ids', $this->payload)->assertSessionHasNoErrors();
    $id = GovernmentId::where('name', 'Complete new ID')->sole();
    $response->assertRedirect(route('admin.government-ids.show', $id));
    $set = $id->requirementSets()->sole();
    expect($set->min_age)->toBe(18)->and($set->groups()->count())->toBe(3)
        ->and($set->applicationSteps()->sole()->title)->toBe('Prepare the listed requirements')
        ->and($id->offices()->sole()->id)->toBe($this->office->id)
        ->and((float) $id->fees()->sole()->amount_min)->toBe(100.0);
    $groups = $set->groups()->with('ways', 'items')->get();
    expect($groups[0]->items->sole()->document_id)->toBe($this->document->id)
        ->and($groups[1]->ways->sole()->required_count)->toBe(1)
        ->and($groups[1]->ways->sole()->qualification_type)->toBe('unexpired')
        ->and($groups[1]->items)->toHaveCount(2)
        ->and($groups[2]->condition_custom)->toBe('Only when requested')
        ->and($groups[2]->items->sole()->quantity)->toBe(2);
    $this->get(route('admin.government-ids.edit', $id))->assertOk()->assertSee('Accepted proof');
    $this->getJson('/api/government-ids/'.$id->id)->assertOk()->assertJsonCount(1, 'data.requirement_sets');
});

it('keeps several new scenarios separate and attaches each guide to its own checklist', function () {
    $renewal = array_replace($this->checklist, ['client_key' => 'checklist-2', 'application_type' => 'renewal']);
    $guideOnly = ['client_key' => 'guide-1', 'application_type' => 'replacement', 'applicant_type' => 'minor', 'groups' => []];
    $renewalGuide = array_replace($this->guide, ['client_key' => 'checklist-2', 'application_type' => 'renewal',
        'steps' => [['title' => 'Renewal step', 'type' => 'general', 'blocks' => []]],
    ]);
    $replacementGuide = ['client_key' => 'guide-1', 'application_type' => 'replacement', 'applicant_type' => 'minor',
        'steps' => [['title' => 'Replacement step', 'type' => 'general', 'blocks' => []]],
    ];
    $this->payload['requirements_payload'] = json_encode([$this->checklist, $renewal, $guideOnly]);
    $this->payload['application_guide_payload'] = json_encode([$replacementGuide, $this->guide, $renewalGuide]);
    $this->post('/admin/government-ids', $this->payload)->assertSessionHasNoErrors();
    $id = GovernmentId::where('name', 'Complete new ID')->sole();
    $sets = $id->requirementSets()->with('groups', 'applicationSteps')->get()->keyBy('application_type');
    expect($sets)->toHaveCount(3)
        ->and($sets['new']->applicationSteps->sole()->title)->toBe('Prepare the listed requirements')
        ->and($sets['renewal']->applicationSteps->sole()->title)->toBe('Renewal step')
        ->and($sets['replacement']->applicationSteps->sole()->title)->toBe('Replacement step')
        ->and($sets['replacement']->groups)->toHaveCount(0)
        ->and($sets['replacement']->max_age)->toBe(17);
    $this->getJson('/api/government-ids/'.$id->id)->assertOk()
        ->assertJsonCount(3, 'data.requirement_sets')->assertJsonMissingPath('data.requirement_sets.0.client_key');
});

it('retains both drafts after unrelated validation failure without creating any partial ID', function () {
    $this->payload['name'] = '';
    $this->from('/admin/government-ids/create')->post('/admin/government-ids', $this->payload)
        ->assertRedirect('/admin/government-ids/create')->assertSessionHasErrors('name')
        ->assertSessionHasInput('requirements_payload', $this->payload['requirements_payload'])
        ->assertSessionHasInput('application_guide_payload', $this->payload['application_guide_payload']);
    expect(GovernmentId::count())->toBe(1)->and(GovernmentIdRequirementSet::count())->toBe(0);
    $this->get('/admin/government-ids/create')->assertOk()->assertSee('requirements_payload')->assertSee('oldPayload');
});

it('rejects unreadable requirement payloads before writing records', function ($raw) {
    $this->payload['requirements_payload'] = $raw;
    $this->postJson('/admin/government-ids', $this->payload)->assertUnprocessable();
    expect(GovernmentId::count())->toBe(1)->and(GovernmentIdRequirementSet::count())->toBe(0)
        ->and(DB::table('government_id_fees')->count())->toBe(0);
})->with(['invalid json' => ['[broken'], 'object' => ['{}'], 'null' => ['null'], 'not encoded' => [[]], 'malformed rows' => ['[null]']]);

it('rejects invalid counts references conditions and copied row IDs without partial saves', function ($field, $value) {
    data_set($this->checklist, $field, $value);
    $this->payload['requirements_payload'] = json_encode([$this->checklist]);
    $this->postJson('/admin/government-ids', $this->payload)->assertUnprocessable();
    expect(GovernmentId::count())->toBe(1)->and(GovernmentIdRequirementSet::count())->toBe(0)
        ->and(GovernmentIdRequirementItem::count())->toBe(0);
})->with([
    ['groups.1.ways.0.required_count', 3],
    ['groups.0.ways.0.items.0.document_id', 99999999],
    ['groups.2.condition_custom', '   '],
    ['groups.0.id', 1],
    ['groups.0.ways.0.id', 1],
    ['groups.0.ways.0.items.0.id', 1],
    ['groups.1.ways.0.qualification_type', 'invalid'],
    ['applicant_type', 'invalid'],
]);

it('rejects duplicate temporary scenario keys', function () {
    $this->payload['requirements_payload'] = json_encode([$this->checklist, $this->checklist]);
    $this->postJson('/admin/government-ids', $this->payload)->assertUnprocessable();
    expect(GovernmentId::count())->toBe(1)->and(GovernmentIdRequirementSet::count())->toBe(0);
});

it('rolls back the ID and earlier sections if its guide references a missing draft', function () {
    $this->guide['client_key'] = 'missing-checklist';
    $this->payload['application_guide_payload'] = json_encode([$this->guide]);
    $auditCount = ContentChangeLog::count();
    $this->postJson('/admin/government-ids', $this->payload)->assertUnprocessable();
    expect(GovernmentId::count())->toBe(1)->and(GovernmentIdRequirementSet::count())->toBe(0)
        ->and(GovernmentIdRequirementGroup::count())->toBe(0)->and(GovernmentIdRequirementItem::count())->toBe(0)
        ->and(DB::table('government_id_fees')->count())->toBe(0)
        ->and(DB::table('government_id_office')->count())->toBe(0)
        ->and(ContentChangeLog::count())->toBe($auditCount);
});

it('rolls back all new sections if the final guide audit fails', function () {
    $this->withoutExceptionHandling();
    $auditCount = ContentChangeLog::count();
    $dispatcher = ContentChangeLog::getEventDispatcher();
    ContentChangeLog::setEventDispatcher(clone $dispatcher);
    ContentChangeLog::creating(function ($log) {
        if (in_array('application_guide', $log->changed_fields ?? [])) throw new RuntimeException('Creation audit failed');
    });
    try {
        expect(fn () => $this->post('/admin/government-ids', $this->payload))->toThrow(RuntimeException::class, 'Creation audit failed');
        expect(GovernmentId::count())->toBe(1)->and(GovernmentIdRequirementSet::count())->toBe(0)
            ->and(GovernmentIdRequirementItem::count())->toBe(0)->and(GovernmentIdApplicationStep::count())->toBe(0)
            ->and(DB::table('government_id_fees')->count())->toBe(0)->and(DB::table('government_id_office')->count())->toBe(0)
            ->and(ContentChangeLog::count())->toBe($auditCount);
    } finally {
        ContentChangeLog::setEventDispatcher($dispatcher);
    }
});

it('accepts name only and intentionally empty checklists', function () {
    $this->post('/admin/government-ids', ['name' => 'Name only', 'requirements_payload' => '[]'])->assertSessionHasNoErrors();
    $this->checklist['groups'] = [];
    $this->post('/admin/government-ids', ['name' => 'Empty scenario', 'requirements_payload' => json_encode([$this->checklist])])->assertSessionHasNoErrors();
    expect(GovernmentId::where('name', 'Name only')->sole()->requirementSets()->count())->toBe(0)
        ->and(GovernmentId::where('name', 'Empty scenario')->sole()->requirementSets()->sole()->groups()->count())->toBe(0);
});

it('protects complete ID creation from guests residents and inactive staff', function () {
    auth()->logout();
    $this->postJson('/admin/government-ids', $this->payload)->assertUnauthorized();
    foreach ([['role' => 'resident'], ['role' => 'researcher', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes));
        $this->postJson('/admin/government-ids', $this->payload)->assertForbidden();
    }
    expect(GovernmentId::count())->toBe(1)->and(GovernmentIdRequirementSet::count())->toBe(0);
});
