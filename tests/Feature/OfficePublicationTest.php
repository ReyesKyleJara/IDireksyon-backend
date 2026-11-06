<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\User;
use App\Services\CatalogRevision;

dataset('publication record owners', [
    'Passport' => [GovernmentId::class, 'government-ids', 'Philippine Passport'],
    'NBI' => [Document::class, 'documents', 'NBI Clearance'],
]);

it('shows every linked office immediately and reflects CMS edits and unlinking without changing methods', function ($model, $resource, $name) {
    $this->actingAs(User::factory()->create(['role' => 'super_admin']));
    $owner = $model::create(['name' => $name]);
    $names = $resource === 'government-ids'
        ? ['DFA Consular Office Malolos', 'DFA Consular Office Marilao']
        : ['NBI Clearance center A', 'NBI Clearance center B', 'NBI Clearance center C'];
    $offices = collect($names)->map(fn ($officeName) => Office::create(['name' => $officeName, 'address' => 'Verified fixture address']));
    $owner->offices()->attach($offices->pluck('id')->all());
    $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all',
        'request_channel' => ['label' => 'In person', 'office_ids' => []]]);
    $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all',
        'request_channel' => ['label' => 'Online', 'office_ids' => []]]);
    $before = $owner->requirementSets()->get()->toArray();
    $office = $offices->first();
    $url = '/api/'.$resource.'/'.$owner->id;
    $data = $this->getJson($url)->assertOk()->assertJsonCount(count($names), 'data.offices')->json('data');
    expect(array_column($data['offices'], 'name'))->toEqualCanonicalizing($names)
        ->and($data['request_paths'][0]['offices'])->toBe([])
        ->and($data['request_paths'][1]['offices'])->toBe([]);
    $this->get(route('admin.offices.edit', $office))->assertOk()->assertDontSee('name="status"', false);
    $this->put(route('admin.offices.update', $office), [
        'name' => $office->name, 'address' => 'Updated address', 'latitude' => 14.8, 'longitude' => 120.9,
        'schedules' => [1 => ['status' => 'open', 'intervals' => [['opens_at' => '09:00', 'closes_at' => '16:00']]]],
    ])->assertSessionHasNoErrors();
    $updated = collect($this->getJson($url)->assertOk()->json('data.offices'))->firstWhere('id', $office->id);
    expect($updated['address'])->toBe('Updated address')
        ->and((float) $updated['latitude'])->toBe(14.8)
        ->and((float) $updated['longitude'])->toBe(120.9)
        ->and($updated['schedules'][0]['intervals'][0]['opens_at'])->toBe('09:00:00')
        ->and($office->fresh()->status)->toBe('draft');
    $remaining = $offices->slice(1)->map(fn ($linked) => [
        'office_id' => $linked->id, 'new_application_status' => 'unknown',
        'renewal_status' => 'unknown', 'replacement_status' => 'unknown',
    ])->values()->all();
    $this->post(route('admin.'.$resource.'.edit-wizard.save', [$owner, 'offices']), [
        'office_links_present' => 1, 'office_links' => $remaining,
        '_catalog_revision' => CatalogRevision::token($owner),
    ])->assertSessionHasNoErrors();
    $ids = array_column($this->getJson($url)->assertOk()->json('data.offices'), 'id');
    expect($ids)->not->toContain($office->id)
        ->and($ids)->toHaveCount(count($names) - 1)
        ->and($office->fresh())->not->toBeNull()
        ->and($owner->requirementSets()->get()->toArray())->toBe($before);
})->with('publication record owners');

it('keeps new offices Draft and blocks researcher publication including forged input', function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $this->post(route('admin.offices.store'), ['name' => 'Draft branch'])->assertSessionHasNoErrors();
    $office = Office::where('name', 'Draft branch')->sole();
    expect($office->status)->toBe('draft');
    $this->get(route('admin.offices.edit', $office))->assertOk()->assertDontSee('value="active"', false);
    $this->put(route('admin.offices.update', $office), ['name' => $office->name, 'status' => 'active'])->assertSessionHasErrors('status');
    $this->post(route('admin.offices.store'), ['name' => 'Forged active office', 'status' => 'active'])->assertSessionHasErrors('status');
    expect($office->fresh()->status)->toBe('draft')->and(Office::where('name', 'Forged active office')->exists())->toBeFalse();
    $office->update(['status' => 'active']);
    $this->put(route('admin.offices.update', $office), ['name' => 'Edited details'])->assertSessionHasNoErrors();
    expect($office->fresh()->status)->toBe('active');
    $this->put(route('admin.offices.update', $office), ['name' => 'Edited details', 'status' => 'inactive'])->assertSessionHasErrors('status');
});

it('manages all record offices without Request Methods and never rewrites method metadata', function ($model, $resource, $name) {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $owner = $model::create(['name' => $name]);
    $office = Office::create(['name' => 'Independent linked draft']);
    $row = fn ($office) => ['office_id' => $office->id, 'new_application_status' => 'unknown',
        'renewal_status' => 'unknown', 'replacement_status' => 'unknown', 'service_notes' => 'Saved at record level'];
    foreach (['wizard', 'edit-wizard'] as $wizard) {
        $this->get(route('admin.'.$resource.'.'.$wizard.'.show', [$owner, 'offices']))->assertOk()
            ->assertDontSee('Create a request method in Step 2 first.')
            ->assertDontSee('Request method context')
            ->assertSee('No Request Method is required.')
            ->assertSee('Linked offices appear for residents automatically.');
        $this->post(route('admin.'.$resource.'.'.$wizard.'.save', [$owner, 'offices']), [
            'office_links_present' => 1, 'office_links' => [$row($office)],
            '_catalog_revision' => CatalogRevision::token($owner),
        ])->assertSessionHasNoErrors();
        expect($owner->offices()->pluck('offices.id')->all())->toBe([$office->id]);
    }
    $set = $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all',
        'request_channel' => ['label' => 'Empty selection', 'office_ids' => []]]);
    $before = $set->fresh()->request_channel;
    $other = Office::create(['name' => 'Another directly linked draft']);
    $this->get(route('admin.'.$resource.'.edit-wizard.show', [$owner, 'offices', 'path' => $set->id]))
        ->assertOk()->assertSee('Independent linked draft')->assertDontSee('Editing Request Method:');
    $this->post(route('admin.'.$resource.'.edit-wizard.save', [$owner, 'offices']), [
        'office_links_present' => 1, 'office_links' => [$row($office), $row($other)],
        'option_content' => 1, 'path' => $set->id, '_catalog_revision' => CatalogRevision::token($owner),
    ])->assertSessionHasNoErrors();
    expect($owner->offices()->count())->toBe(2)->and($set->fresh()->request_channel)->toBe($before);
})->with('publication record owners');
