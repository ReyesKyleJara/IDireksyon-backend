<?php

use App\Models\Document;
use App\Models\GovernmentId;
use Illuminate\Support\Facades\DB;

function residentRequirementItem($group, $way, array $attributes = [])
{
    $item = $group->items()->make(array_replace([
        'type' => 'custom', 'custom_name' => 'Supporting item',
    ], $attributes));
    $item->way()->associate($way);
    $item->save();
    return $item;
}

it('adds an empty requirements list without removing any existing detail fields', function () {
    $id = GovernmentId::create(['name' => 'Legacy ID', 'requirements' => 'Bring the original', 'description' => 'Description']);
    $this->getJson('/api/government-ids/'.$id->id)->assertOk()
        ->assertJsonPath('data.requirement_sets', [])
        ->assertJsonPath('data.requirements', 'Bring the original')
        ->assertJsonPath('data.description', 'Description')
        ->assertJsonStructure(['data' => [
            'id', 'name', 'level', 'category', 'agency', 'issued_by', 'purpose', 'description',
            'eligibility', 'requirements', 'prerequisite_notes', 'fee', 'fees', 'application_process',
            'processing_time', 'processing_time_type', 'processing_time_min', 'processing_time_max',
            'processing_time_unit', 'renewal_process', 'replacement_process', 'validity',
            'validity_type', 'validity_value', 'validity_unit', 'office_location', 'office_hours',
            'official_link', 'official_sources', 'last_verified_at', 'requirement_sets',
        ]]);
    $this->getJson('/api/government-ids')->assertOk()->assertJsonMissingPath('data.0.requirement_sets');
    $this->getJson('/api/government-ids/999999')->assertNotFound();
});

it('returns resolved references separate alternatives counts qualifications and submission details', function () {
    $passport = GovernmentId::create(['name' => 'Passport']);
    $accepted = GovernmentId::create(['name' => 'National ID']);
    $document = Document::create(['name' => 'PSA Birth Certificate']);
    $set = $passport->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $group = $set->groups()->create(['title' => 'Proof of identity', 'rule' => 'all', 'condition_type' => 'spouse_surname']);
    $first = $group->ways()->create(['required_count' => 1, 'qualification_type' => 'current_address']);
    residentRequirementItem($group, $first, ['type' => 'government_id', 'government_id_id' => $accepted->id,
        'submission_format' => 'original_photocopy', 'copies' => 1, 'instructions' => 'Front and back.']);
    $second = $group->ways()->create(['required_count' => 2, 'qualification_type' => 'photo_signature', 'qualification_scope' => 'at_least_one', 'sort_order' => 1]);
    residentRequirementItem($group, $second, ['type' => 'document', 'document_id' => $document->id]);
    residentRequirementItem($group, $second, ['custom_name' => 'School ID']);
    residentRequirementItem($group, $second, ['custom_name' => 'Supporting photo', 'quantity' => 2,
        'submission_format' => 'custom', 'submission_format_custom' => 'Signed print', 'copies' => 3]);
    $other = GovernmentId::create(['name' => 'Other ID']);
    $other->requirementSets()->create(['application_type' => 'replacement', 'applicant_type' => 'minor']);

    $response = $this->getJson('/api/government-ids/'.$passport->id)->assertOk()
        ->assertJsonCount(1, 'data.requirement_sets')
        ->assertJsonPath('data.requirement_sets.0.application_type', 'new')
        ->assertJsonPath('data.requirement_sets.0.application_type_label', 'First-Time Application')
        ->assertJsonPath('data.requirement_sets.0.applicant_type', 'adult')
        ->assertJsonPath('data.requirement_sets.0.display_label', 'Adult • First-Time Application')
        ->assertJsonPath('data.requirement_sets.0.min_age', 18)
        ->assertJsonPath('data.requirement_sets.0.max_age', null)
        ->assertJsonCount(2, 'data.requirement_sets.0.groups.0.ways');
    $result = $response->json('data.requirement_sets.0.groups.0');
    expect($result['condition_type'])->toBe('spouse_surname')
        ->and($result['condition_label'])->toBe('Married applicant using spouse’s surname')
        ->and($result['ways'][0]['items'])->toHaveCount(1)
        ->and($result['ways'][0]['items'][0]['government_id_id'])->toBe($accepted->id)
        ->and($result['ways'][0]['items'][0]['name'])->toBe('National ID')
        ->and($result['ways'][0]['items'][0]['submission_label'])->toBe('Original + Photocopy')
        ->and($result['ways'][0]['items'][0]['copies'])->toBe(1)
        ->and($result['ways'][0]['items'][0]['instructions'])->toBe('Front and back.')
        ->and($result['ways'][0]['qualification_label'])->toBe('Each selected item must: Show current address')
        ->and($result['ways'][1]['required_count'])->toBe(2)
        ->and($result['ways'][1]['qualification_scope'])->toBe('at_least_one')
        ->and($result['ways'][1]['qualification_label'])->toBe('At least one selected item must: Contain photo and signature')
        ->and($result['ways'][1]['items'][0]['document_id'])->toBe($document->id)
        ->and($result['ways'][1]['items'][0]['name'])->toBe('PSA Birth Certificate')
        ->and($result['ways'][1]['items'][2]['type'])->toBe('custom')
        ->and($result['ways'][1]['items'][2]['quantity'])->toBe(2)
        ->and($result['ways'][1]['items'][2]['submission_label'])->toBe('Signed print')
        ->and($result['ways'][1]['items'][2]['copies'])->toBe(3);
    expect(array_keys($result))->not->toContain('created_at', 'updated_at');
    $response->assertJsonMissingPath('data.requirement_sets.0.groups.0.ways.0.items.0.government_id');
});

it('preserves legacy all required and choose one semantics without writing ways', function () {
    $id = GovernmentId::create(['name' => 'Older checklist']);
    $set = $id->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'minor']);
    foreach (['all', 'choose_one'] as $rule) {
        $group = $set->groups()->create(['rule' => $rule]);
        foreach (['A', 'B'] as $name) $group->items()->create(['type' => 'custom', 'custom_name' => $name]);
    }
    $this->getJson('/api/government-ids/'.$id->id)->assertOk()
        ->assertJsonPath('data.requirement_sets.0.groups.0.ways.0.required_count', 2)
        ->assertJsonPath('data.requirement_sets.0.groups.1.ways.0.required_count', 1)
        ->assertJsonPath('data.requirement_sets.0.groups.0.ways.0.id', null)
        ->assertJsonPath('data.requirement_sets.0.groups.0.condition_label', null)
        ->assertJsonPath('data.requirement_sets.0.groups.0.ways.0.qualification_label', null);
    $this->assertDatabaseCount('government_id_requirement_ways', 0);
});

it('returns custom labels zero age and explicitly ordered groups ways and items', function () {
    $id = GovernmentId::create(['name' => 'Custom case']);
    $set = $id->requirementSets()->create(['application_type' => 'custom', 'application_type_custom' => 'Correction',
        'applicant_type' => 'custom', 'applicant_type_custom' => 'Infant', 'min_age' => 0, 'max_age' => 0]);
    $set->groups()->create(['title' => 'Later', 'rule' => 'all', 'sort_order' => 9]);
    $group = $set->groups()->create(['title' => 'First', 'rule' => 'all', 'condition_type' => 'custom', 'condition_custom' => 'When the name differs']);
    $group->ways()->create(['sort_order' => 9]);
    $way = $group->ways()->create(['qualification_type' => 'custom', 'qualification_custom' => 'Be issued this year']);
    residentRequirementItem($group, $way, ['custom_name' => 'Later item', 'sort_order' => 9]);
    residentRequirementItem($group, $way, ['custom_name' => 'First item']);
    $this->getJson('/api/government-ids/'.$id->id)->assertOk()
        ->assertJsonPath('data.requirement_sets.0.application_type_label', 'Correction')
        ->assertJsonPath('data.requirement_sets.0.applicant_type_label', 'Infant')
        ->assertJsonPath('data.requirement_sets.0.min_age', 0)
        ->assertJsonPath('data.requirement_sets.0.max_age', 0)
        ->assertJsonPath('data.requirement_sets.0.groups.0.title', 'First')
        ->assertJsonPath('data.requirement_sets.0.groups.0.condition_label', 'When the name differs')
        ->assertJsonPath('data.requirement_sets.0.groups.0.ways.0.qualification_label', 'Each selected item must: Be issued this year')
        ->assertJsonPath('data.requirement_sets.0.groups.0.ways.0.items.0.name', 'First item');
});

it('keeps query counts bounded as referenced items increase', function () {
    $id = GovernmentId::create(['name' => 'Query example']);
    $accepted = GovernmentId::create(['name' => 'Accepted ID']);
    $doc = Document::create(['name' => 'Accepted document']);
    $set = $id->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $add = function () use ($set, $accepted, $doc) {
        $group = $set->groups()->create(['rule' => 'all']);
        $way = $group->ways()->create([]);
        residentRequirementItem($group, $way, ['type' => 'government_id', 'government_id_id' => $accepted->id]);
        residentRequirementItem($group, $way, ['type' => 'document', 'document_id' => $doc->id]);
    };
    $add();
    $count = function () use ($id) {
        DB::enableQueryLog(); DB::flushQueryLog();
        try {
            $this->getJson('/api/government-ids/'.$id->id)->assertOk();
            return count(DB::getQueryLog());
        } finally { DB::disableQueryLog(); DB::flushQueryLog(); }
    };
    $before = $count();
    for ($i = 0; $i < 8; $i++) $add();
    expect($count())->toBe($before);
});
