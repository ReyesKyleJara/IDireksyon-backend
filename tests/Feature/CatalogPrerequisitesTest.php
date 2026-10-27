<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Services\CatalogPrerequisites;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

dataset('prerequisite owners', [[GovernmentId::class, 'government-ids'], [Document::class, 'documents']]);

function prerequisiteSaveUrl($record, string $resource, string $mode = 'edit-wizard'): string
{
    return route('admin.'.$resource.'.'.$mode.'.save', [$record, 'requirements']);
}

it('starts empty and saves all source types through Add and Edit without changing requirements or metadata', function ($model, $resource) {
    $key = $this->get(route('admin.'.$resource.'.create'))->assertOk()->viewData('creationKey');
    $this->post(route('admin.'.$resource.'.wizard.store'), [
        'creation_key' => $key, 'name' => 'Owner', 'application_availability' => 'limited_conditional',
        'acquisition_type' => 'profession_based', 'target_group' => 'Keep target',
    ])->assertSessionHasNoErrors();
    $owner = $model::where('name', 'Owner')->sole();
    expect($owner->prerequisites)->toHaveCount(0)->and(CatalogPrerequisites::present($owner))->toBe([]);
    $this->get(route('admin.'.$resource.'.wizard.show', [$owner, 'requirements']))->assertOk()
        ->assertSeeInOrder(['Prerequisites / Dependencies', 'Application Requirements']);
    foreach (['review'] as $step) $this->get(route('admin.'.$resource.'.wizard.show', [$owner, $step]))->assertOk()->assertDontSee('Prerequisites / Dependencies');
    $id = GovernmentId::create(['name' => 'Linked ID']);
    $doc = Document::create(['name' => 'Linked document']);
    $set = $owner->requirementSets()->first();
    $group = $set->groups()->create(['title' => 'Keep application requirements', 'rule' => 'all', 'condition_type' => 'always']);
    $item = $group->items()->create(['type' => 'custom', 'custom_name' => 'Keep submission']);
    $rows = [
        ['type' => 'government_id', 'government_id_id' => $id->id, 'condition' => '  Under 18  '],
        ['type' => 'document', 'document_id' => $doc->id, 'condition' => '  '],
        ['type' => 'custom', 'custom_name' => 'Complete training', 'condition' => null],
    ];
    $this->post(prerequisiteSaveUrl($owner, $resource, 'wizard'), ['prerequisites_present' => 1, 'prerequisites' => $rows])
        ->assertSessionHasNoErrors()->assertRedirect();
    $owner->refresh();
    expect($owner->prerequisites)->toHaveCount(3)->and($owner->prerequisites[0]->condition)->toBe('Under 18')
        ->and($owner->prerequisites[1]->condition)->toBeNull()->and($owner->prerequisites->pluck('sort_order')->all())->toBe([0, 1, 2]);
    $id->update(['name' => 'Renamed linked ID']);
    foreach (['wizard', 'edit-wizard'] as $mode) {
        $this->get(route('admin.'.$resource.'.'.$mode.'.show', [$owner, 'requirements']))->assertOk()->assertSee('Complete training')->assertSee('Under 18');
        $this->get(route('admin.'.$resource.'.'.$mode.'.show', [$owner, 'review']))->assertOk()
            ->assertSeeInOrder(['Renamed linked ID', 'Linked document', 'Complete training'])->assertSee('Applies when: Under 18');
    }
    $this->get(route('admin.'.$resource.'.show', $owner))->assertOk()->assertSee('Prerequisites / Dependencies')->assertSee('Renamed linked ID');
    $saved = $owner->prerequisites->map(fn ($row) => $row->only(['id', ...CatalogPrerequisites::FIELDS]))->reverse()->values()->all();
    $saved[0]['custom_name'] = 'Updated training';
    $this->post(prerequisiteSaveUrl($owner, $resource), ['prerequisites_present' => 1, 'prerequisites' => $saved])->assertSessionHasNoErrors();
    expect($owner->fresh()->prerequisites->pluck('id')->all())->toBe(array_column($saved, 'id'));
    array_pop($saved);
    $this->post(prerequisiteSaveUrl($owner, $resource), ['prerequisites_present' => 1, 'prerequisites' => $saved])->assertSessionHasNoErrors();
    expect($owner->fresh()->prerequisites)->toHaveCount(2);
    $this->post(prerequisiteSaveUrl($owner, $resource))->assertSessionHasNoErrors();
    expect($owner->fresh()->prerequisites)->toHaveCount(2);
    $this->post(prerequisiteSaveUrl($owner, $resource), ['prerequisites_present' => 1])->assertSessionHasNoErrors();
    expect($owner->fresh()->prerequisites)->toHaveCount(0)
        ->and($owner->fresh()->application_availability)->toBe('limited_conditional')
        ->and($owner->fresh()->acquisition_type)->toBe('profession_based')->and($owner->fresh()->target_group)->toBe('Keep target')
        ->and($group->fresh()->title)->toBe('Keep application requirements')->and($item->fresh()->custom_name)->toBe('Keep submission');
})->with('prerequisite owners');

it('rejects self links mixed sources duplicates missing targets forged IDs and invalid payloads atomically', function ($model, $resource) {
    $owner = $model::create(['name' => 'Owner']);
    $other = $model::create(['name' => 'Other owner']);
    $target = Document::create(['name' => 'Target']);
    app(CatalogPrerequisites::class)->save($owner, [['type' => 'custom', 'custom_name' => 'Keep']]);
    app(CatalogPrerequisites::class)->save($other, [['type' => 'custom', 'custom_name' => 'Other']]);
    $selfType = $owner instanceof Document ? 'document' : 'government_id';
    $valid = ['type' => 'document', 'document_id' => $target->id];
    $payloads = [
        [['type' => $selfType, $selfType.'_id' => $owner->id]],
        [$valid + ['custom_name' => 'Ambiguous']],
        [['type' => 'custom', 'custom_name' => 'Same'], ['type' => 'custom', 'custom_name' => ' Same ']],
        [$valid, $valid],
        [['type' => 'custom']], [['type' => 'other', 'custom_name' => 'Wrong']],
        [['type' => 'document', 'document_id' => 999999]],
        [['type' => 'custom', 'custom_name' => str_repeat('x', 256)]],
        [['type' => 'custom', 'custom_name' => 'Long condition', 'condition' => str_repeat('x', 1001)]],
        [['type' => ['custom'], 'custom_name' => 'Invalid']], ['bad row'],
        [['type' => 'custom', 'custom_name' => 'Forged', 'id' => $other->prerequisites()->sole()->id]],
    ];
    foreach ($payloads as $rows) {
        $this->postJson(prerequisiteSaveUrl($owner, $resource), ['prerequisites_present' => 1, 'prerequisites' => $rows])->assertUnprocessable();
        expect($owner->fresh()->prerequisites()->sole()->custom_name)->toBe('Keep');
    }
    $this->postJson(prerequisiteSaveUrl($owner, $resource), ['prerequisites_present' => 1, 'prerequisites' => 'not array'])->assertUnprocessable();
    $this->post(prerequisiteSaveUrl($owner, $resource), ['prerequisites_present' => 1, 'prerequisites' => [
        $valid + ['condition' => 'First condition'], $valid + ['condition' => 'Different condition'],
    ]])->assertSessionHasNoErrors();
})->with('prerequisite owners');

it('protects linked records from deletion and cascades only owned prerequisite rows', function ($model, $resource) {
    $owner = $model::create(['name' => 'Owner']);
    $target = $model::create(['name' => 'Target']);
    $type = $owner instanceof Document ? 'document' : 'government_id';
    app(CatalogPrerequisites::class)->save($owner, [['type' => $type, $type.'_id' => $target->id]]);
    $this->delete(route('admin.'.$resource.'.destroy', $target))->assertRedirect()->assertSessionHas('error');
    expect($target->fresh())->not->toBeNull();
    try { $target->delete(); test()->fail('Expected foreign key protection'); } catch (\Illuminate\Database\QueryException) {}
    $owner->delete();
    expect($target->fresh())->not->toBeNull()->and($owner->prerequisites()->count())->toBe(0);
})->with('prerequisite owners');

it('escapes custom names and conditions in Review and Details', function ($model, $resource) {
    $owner = $model::create(['name' => 'Owner']);
    $text = '<script>alert("unsafe")</script>';
    app(CatalogPrerequisites::class)->save($owner, [['type' => 'custom', 'custom_name' => $text, 'condition' => $text]]);
    foreach ([route('admin.'.$resource.'.show', $owner), route('admin.'.$resource.'.edit-wizard.show', [$owner, 'review'])] as $url) {
        $this->get($url)->assertOk()->assertSee($text)->assertDontSee($text, false);
    }
})->with('prerequisite owners');

it('exposes structured arrays and empty arrays without filtering public or resident catalogs', function () {
    $owner = GovernmentId::create(['name' => 'Owner', 'application_availability' => 'discontinued']);
    $doc = Document::create(['name' => 'Medical Certificate']);
    $empty = GovernmentId::create(['name' => 'Empty']);
    app(CatalogPrerequisites::class)->save($owner, [['type' => 'document', 'document_id' => $doc->id], ['type' => 'custom', 'custom_name' => 'Training', 'condition' => 'If required']]);
    $expected = [
        ['type' => 'document', 'id' => $doc->id, 'name' => $doc->name, 'condition' => null, 'sort_order' => 0],
        ['type' => 'custom', 'id' => null, 'name' => 'Training', 'condition' => 'If required', 'sort_order' => 1],
    ];
    $this->getJson('/api/government-ids/'.$owner->id)->assertOk()->assertJsonPath('data.prerequisites', $expected);
    $this->getJson('/api/government-ids/'.$empty->id)->assertOk()->assertJsonPath('data.prerequisites', []);
    $this->getJson('/api/government-ids')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.1.prerequisites', $expected);
    app(CatalogPrerequisites::class)->save($doc, [['type' => 'government_id', 'government_id_id' => $empty->id]]);
    $resident = User::factory()->create(['role' => 'resident', 'is_active' => true]);
    $this->actingAs($resident)->getJson('/api/auth/inventory')->assertOk()->assertJsonPath('data.government_ids.1.prerequisites', $expected)
        ->assertJsonPath('data.documents.0.prerequisites.0.id', $empty->id);
});
