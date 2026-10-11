<?php

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\DocumentApplicationStep;
use App\Models\DocumentRequirementItem;
use App\Models\DocumentRequirementSet;
use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementSet;
use App\Models\Office;
use App\Models\User;
use App\Services\GovernmentIdApplicationGuide;
use Illuminate\Support\Facades\DB;

function documentChecklist(array $items = []): array
{
    return ['application_type' => 'new', 'applicant_type' => 'adult', 'groups' => [[
        'title' => 'Proof of identity', 'rule' => 'choose_one', 'condition_type' => 'custom',
        'condition_custom' => 'When applying through a representative',
        'ways' => [[
            'required_count' => 1, 'qualification_type' => 'current_address', 'qualification_scope' => 'every',
            'items' => $items ?: [['type' => 'custom', 'custom_name' => 'Authorization letter', 'submission_format' => 'original']],
        ]],
    ]]];
}

function documentGuide(array $identity = []): array
{
    return [...$identity, 'application_type' => 'new', 'applicant_type' => 'adult', 'steps' => [[
        'title' => 'Visit the issuing office', 'type' => 'office_visit', 'blocks' => [[
            'type' => 'reminder', 'content' => ['body' => ['type' => 'doc', 'content' => [[
                'type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Bring your documents.']],
            ]]]],
        ]],
    ]]];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher', 'name' => 'Document Researcher']));
});

it('saves every section on Add Document and keeps document owned content out of government ID tables', function () {
    $id = GovernmentId::create(['name' => 'Existing ID']);
    $reference = Document::create(['name' => 'Existing certificate']);
    $office = Office::create(['name' => 'Municipal office']);
    $row = documentChecklist([
        ['type' => 'government_id', 'government_id_id' => $id->id, 'submission_format' => 'original_photocopy', 'copies' => 1],
        ['type' => 'document', 'document_id' => $reference->id, 'submission_format' => 'original'],
    ]);
    $this->post('/admin/documents', [
        'name' => 'Complete document', 'purpose' => 'Proof for an application',
        'issued_by' => 'Local department', 'eligibility_age_type' => 'minimum', 'eligibility_min_age' => 18,
        'validity_type' => 'fixed', 'validity_value' => 6, 'validity_unit' => 'month',
        'processing_time_type' => 'fixed', 'processing_time_min' => 2, 'processing_time_unit' => 'working_day',
        'fees' => [['label' => 'Issuance', 'type' => 'fixed', 'amount_min' => 50, 'is_optional' => 0]],
        'office_links_present' => 1, 'office_links' => [[
            'office_id' => $office->id, 'new_application_status' => 'available',
            'renewal_status' => 'unknown', 'replacement_status' => 'unknown',
        ]],
        'requirements_payload' => json_encode([['client_key' => 'draft-document', ...$row]]),
        'application_guide_payload' => json_encode([documentGuide(['client_key' => 'draft-document'])]),
    ])->assertSessionHasNoErrors();
    $document = Document::where('name', 'Complete document')->sole();
    $set = $document->requirementSets()->sole();
    expect($set)->toBeInstanceOf(DocumentRequirementSet::class)
        ->and($set->document_id)->toBe($document->id)
        ->and($set->groups->sole()->ways->sole()->required_count)->toBe(1)
        ->and($set->groups->sole()->items)->toHaveCount(2)
        ->and($set->applicationSteps->sole()->blocks->sole()->content['body']['type'])->toBe('doc')
        ->and($document->fees->sole()->amount_min)->toBe('50.00')
        ->and($document->offices->sole()->id)->toBe($office->id)
        ->and($document->eligibility)->toContain('18')
        ->and($document->validity)->toContain('6')
        ->and(GovernmentIdRequirementSet::count())->toBe(0);
    $this->get(route('admin.documents.show', $document))->assertOk()->assertSee('Visit the issuing office')
        ->assertSee('Bring your documents.')->assertSee('Existing ID')->assertSee('Municipal office')
        ->assertSee('Document Researcher')->assertDontSee('Add checklist')->assertDontSee('Apply step');
    $this->get(route('admin.documents.edit', $document))->assertOk()->assertSee('Add checklist')
        ->assertSee('Edit fees')->assertSee('Apply step')->assertSee('Edit offices');
});

it('preserves omitted sections and lets the full form explicitly clear fees and office links', function () {
    $document = Document::create(['name' => 'Preserve sections', 'validity' => 'Existing validity']);
    $fee = $document->fees()->create(['label' => 'Copy', 'amount_min' => 20]);
    $office = Office::create(['name' => 'Keep office']);
    $document->offices()->attach($office);
    $saved = $this->postJson(route('admin.documents.checklists.store', $document), documentChecklist())->assertCreated()->json('checklist');
    $this->put(route('admin.documents.update', $document), ['name' => 'Renamed', 'application_guide_payload' => json_encode([documentGuide(['id' => $saved['id']])])])->assertSessionHasNoErrors();
    $this->put(route('admin.documents.update', $document), ['name' => 'Renamed again'])->assertSessionHasNoErrors();
    expect($document->fresh()->validity)->toBe('Existing validity')->and($document->fees()->sole()->id)->toBe($fee->id)
        ->and($document->offices()->count())->toBe(1)->and(DocumentApplicationStep::count())->toBe(1);
    $this->put(route('admin.documents.update', $document), ['name' => 'Renamed again', 'fees_present' => 1, 'office_links_present' => 1])->assertSessionHasNoErrors();
    expect($document->fees()->count())->toBe(0)->and($document->offices()->count())->toBe(0)->and(DocumentRequirementItem::count())->toBe(1);
    $this->assertModelExists($office);
});

it('keeps accepted item counts and qualifications through unchanged checklist edits', function () {
    $document = Document::create(['name' => 'Choose two']);
    $payload = documentChecklist([
        ['type' => 'custom', 'custom_name' => 'Item A', 'submission_format' => 'original'],
        ['type' => 'custom', 'custom_name' => 'Item B', 'submission_format' => 'photocopy', 'copies' => 1],
        ['type' => 'custom', 'custom_name' => 'Item C', 'submission_format' => 'not_specified'],
    ]);
    $payload['groups'][0]['ways'][0]['required_count'] = 2;
    $payload['groups'][0]['ways'][0]['qualification_scope'] = 'at_least_one';
    $url = route('admin.documents.checklists.store', $document);
    $saved = $this->postJson($url, $payload)->assertCreated()->json('checklist');
    $set = $document->requirementSets()->sole();
    $group = $set->groups->sole();
    $payload['groups'][0]['id'] = $group->id;
    $payload['groups'][0]['ways'][0]['id'] = $group->ways->sole()->id;
    foreach ($group->items as $index => $item) $payload['groups'][0]['ways'][0]['items'][$index]['id'] = $item->id;
    $before = ContentChangeLog::count();
    $this->putJson($url.'/'.$set->id, $payload)->assertOk()->assertJsonPath('checklist', $saved);
    expect(ContentChangeLog::count())->toBe($before);
});

it('rejects cross document checklist and nested row adoption without partial changes', function () {
    $first = Document::create(['name' => 'First']);
    $second = Document::create(['name' => 'Second']);
    $a = $this->postJson(route('admin.documents.checklists.store', $first), documentChecklist())->assertCreated()->json('checklist');
    $b = $this->postJson(route('admin.documents.checklists.store', $second), documentChecklist())->assertCreated()->json('checklist');
    $wrong = route('admin.documents.checklists.store', $first).'/'.$b['id'];
    $this->putJson($wrong, documentChecklist())->assertNotFound();
    $this->deleteJson($wrong)->assertNotFound();
    $payload = documentChecklist();
    $payload['application_type'] = 'renewal';
    $payload['groups'][0]['id'] = $b['groups'][0]['id'];
    $this->putJson(route('admin.documents.checklists.store', $first).'/'.$a['id'], $payload)->assertNotFound();
    expect($first->requirementSets()->sole()->application_type)->toBe('new')->and(DocumentRequirementItem::count())->toBe(2);
});

it('rolls back the entire Add Document form when a guide references another document', function () {
    $other = Document::create(['name' => 'Keep me']);
    $set = $other->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $this->post('/admin/documents', [
        'name' => 'Must roll back', 'fees' => [['label' => 'Fee', 'type' => 'free']],
        'requirements_payload' => json_encode([['client_key' => 'draft', ...documentChecklist()]]),
        'application_guide_payload' => json_encode([documentGuide(['id' => $set->id])]),
    ])->assertSessionHasErrors('application_guide_payload');
    expect(Document::count())->toBe(1)->and(DocumentRequirementSet::count())->toBe(1)->and(DB::table('document_fees')->count())->toBe(0);
});

it('blocks parent and scenario deletion with guides and blocks deletion of referenced IDs and documents', function () {
    $document = Document::create(['name' => 'Guide parent']);
    $reference = Document::create(['name' => 'Referenced certificate']);
    $id = GovernmentId::create(['name' => 'Referenced ID']);
    $payload = documentChecklist([
        ['type' => 'document', 'document_id' => $reference->id, 'submission_format' => 'original'],
        ['type' => 'government_id', 'government_id_id' => $id->id, 'submission_format' => 'original'],
    ]);
    $url = route('admin.documents.checklists.store', $document);
    $set = $this->postJson($url, $payload)->assertCreated()->json('checklist');
    $this->put(route('admin.documents.update', $document), ['name' => $document->name, 'application_guide_payload' => json_encode([documentGuide(['id' => $set['id']])])])->assertSessionHasNoErrors();
    $this->deleteJson($url.'/'.$set['id'])->assertUnprocessable();
    foreach ([route('admin.documents.destroy', $document), route('admin.documents.destroy', $reference), route('admin.government-ids.destroy', $id)] as $target) {
        $this->delete($target)->assertSessionHas('error');
    }
    expect(Document::count())->toBe(2)->and(GovernmentId::count())->toBe(1)->and(DocumentApplicationStep::count())->toBe(1);
});

it('protects document checklist mutations from guests residents and inactive researchers', function () {
    $document = Document::create(['name' => 'Protected']);
    $url = route('admin.documents.checklists.store', $document);
    $id = $this->postJson($url, documentChecklist())->assertCreated()->json('checklist.id');
    auth()->logout();
    $this->postJson($url, documentChecklist())->assertUnauthorized();
    $this->putJson($url.'/'.$id, documentChecklist())->assertUnauthorized();
    $this->deleteJson($url.'/'.$id)->assertUnauthorized();
    foreach ([['role' => 'resident'], ['role' => 'researcher', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes));
        $this->postJson($url, documentChecklist())->assertForbidden();
        $this->putJson($url.'/'.$id, documentChecklist())->assertForbidden();
        $this->deleteJson($url.'/'.$id)->assertForbidden();
    }
});

it('rejects invalid drafts and restores the entered form without creating anything', function () {
    $payload = documentChecklist();
    $payload['groups'][0]['ways'][0]['required_count'] = 4;
    $draft = json_encode([['client_key' => 'draft-invalid', ...$payload]]);
    $this->from('/admin/documents/create')->post('/admin/documents', ['name' => 'Retain name', 'requirements_payload' => $draft])
        ->assertSessionHasErrors('requirements_payload')->assertSessionHasInput('requirements_payload', $draft);
    $this->get('/admin/documents/create')->assertOk()->assertSee('Retain name')->assertSee('Create Document');
    expect(Document::count())->toBe(0)->and(DocumentRequirementSet::count())->toBe(0);
});

it('rolls back document and child writes if their audit fails', function () {
    $this->withoutExceptionHandling();
    $dispatcher = ContentChangeLog::getEventDispatcher();
    ContentChangeLog::setEventDispatcher(clone $dispatcher);
    ContentChangeLog::creating(function ($log) {
        if (in_array('requirement_sets', $log->changed_fields ?? [], true)) throw new RuntimeException('Audit unavailable');
    });
    try {
        expect(fn () => $this->post('/admin/documents', ['name' => 'Rollback', 'requirements_payload' => json_encode([['client_key' => 'draft', ...documentChecklist()]])]))->toThrow(RuntimeException::class);
    } finally {
        ContentChangeLog::setEventDispatcher($dispatcher);
    }
    expect(Document::count())->toBe(0)->and(DocumentRequirementSet::count())->toBe(0)->and(ContentChangeLog::count())->toBe(0);
});

it('stops migration rollback before deleting saved document research', function () {
    Document::create(['name' => 'Preserved', 'purpose' => 'Saved purpose']);
    $migration = require database_path('migrations/2026_10_09_000000_add_structured_document_content.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Rollback stopped');
    $this->assertDatabaseHas('documents', ['name' => 'Preserved', 'purpose' => 'Saved purpose']);
    expect(\Illuminate\Support\Facades\Schema::hasTable('document_requirement_sets'))->toBeTrue();
});
