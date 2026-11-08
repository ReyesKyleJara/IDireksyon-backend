<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\User;
use App\Services\CatalogRevision;

beforeEach(function () { $this->actingAs(User::factory()->create(['role' => 'researcher'])); });

dataset('request option owners', [['documents', Document::class], ['government-ids', GovernmentId::class]]);

function optionWizardChannel(array $extra = []): array
{
    return array_replace(['label' => 'Certified Copy — In Person', 'channel' => 'outlet', 'provider' => 'Fixture Registry', 'appointment' => 'unknown', 'fulfillment' => [], 'fee_ids' => [], 'office_ids' => []], $extra);
}

it('uses seven ordered steps with one option editor and reuses existing path IDs', function ($resource, $model) {
    $key = $this->get(route('admin.'.$resource.'.create'))->assertOk()->viewData('creationKey');
    $this->post(route('admin.'.$resource.'.wizard.store'), ['creation_key' => $key, 'name' => 'Sequential fixture'])->assertSessionHasNoErrors();
    $owner = $model::sole(); $set = $owner->requirementSets()->sole();
    $url = fn ($step) => route('admin.'.$resource.'.wizard.show', [$owner, $step, 'path' => $set->id]);
    $page = $this->get($url('channels'))->assertOk()->assertSee('+ Add Request Method')->assertSee('Save Method');
    expect(array_keys($page->viewData('steps')))->toBe(['basic', 'channels', 'requirements', 'fees', 'guide', 'offices', 'review']);
    // The automatically created checklist is configured, not duplicated.
    $page->assertSee('inlineRequestMethods', false);
    $editor = $this->get(route('admin.'.$resource.'.request-options.edit', [$owner, $set->id, 'wizard' => 'create']))->assertOk();
    $editor->assertDontSee('What does the applicant want to do?')->assertDontSee('Display Name')->assertSee('Category')->assertSee('What to Prepare')->assertDontSee('Required item')->assertDontSee('Requirement description')->assertDontSee('Application Steps')->assertSee('aria-label="Edit fees"', false);
    $payload = ['path_id' => $set->id, 'return_wizard' => 'create', 'option_editor' => 1, 'application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => optionWizardChannel(), 'fee_ids_scope' => 'selected', 'office_ids_scope' => 'selected'];
    foreach ([1, 2] as $_) $this->post(route('admin.'.$resource.'.channels', $owner), $payload)->assertSessionHasNoErrors()->assertRedirect($url('channels').'#related-content');
    expect($owner->requirementSets()->sole()->id)->toBe($set->id);
    // Offices are linked to the whole entry, so that step has no method picker.
    $this->get($url('offices'))->assertOk()->assertSee('No Request Method is required.')->assertDontSee('Editing Request Method:');
    foreach (['requirements', 'fees', 'guide'] as $step) {
        $this->get($url($step))->assertOk()->assertSee('Editing Request Method:')->assertSee('Manage Request Methods')
            ->assertDontSee('+ Add Request Method')->assertDontSee('name="request_channel[label]"', false)->assertDontSee('Delete Method');
    }
    $this->post(route('admin.'.$resource.'.wizard.save', [$owner, 'channels']), ['path' => $set->id])->assertRedirect($url('requirements'));
    unset($payload['path_id']); $payload['request_channel'] = optionWizardChannel(['label' => 'Renewal — Online', 'channel' => 'online']); $payload['application_type'] = 'renewal';
    $this->post(route('admin.'.$resource.'.channels', $owner), $payload)->assertSessionHasNoErrors();
    expect($owner->requirementSets()->count())->toBe(2)->and($set->fresh()->application_type)->toBe('copy');
})->with('request option owners');

it('keeps requirements steps and navigation context isolated across saves errors back and reopen', function ($resource, $model) {
    $owner = $model::create(['name' => 'Context fixture']);
    $sets = collect(['In Person', 'Online'])->map(fn ($label) => $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => optionWizardChannel(['label' => $label])]));
    foreach ($sets as $index => $set) {
        $this->putJson(route('admin.'.$resource.'.checklists.update', [$owner, $set]), ['application_type' => 'copy', 'applicant_type' => 'all', 'groups' => [[
            'title' => 'Checklist '.$index, 'rule' => 'all', 'condition_type' => 'always', 'ways' => [['required_count' => 1, 'qualification_type' => 'none', 'qualification_scope' => 'every', 'items' => [['type' => 'custom', 'custom_name' => 'Evidence '.$index, 'submission_format' => 'original']]]],
        ]]])->assertOk();
        $set->applicationSteps()->create(['title' => 'Procedure '.$index, 'type' => 'general']);
    }
    [$first, $second] = $sets->all();
    $url = fn ($step, $path = null) => route('admin.'.$resource.'.edit-wizard.show', [$owner, $step, 'path' => $path]);
    $page = $this->get($url('requirements', $second->id))->assertOk()->assertSee('Evidence 1')->assertDontSee('Evidence 0');
    expect(array_column($page->viewData('checklists'), 'id'))->toBe([$second->id]);
    $this->get($url('guide'))->assertOk()->assertSee('Procedure 1')->assertDontSee('Procedure 0')->assertViewHas('selectedPath', $second->id);
    $this->post(route('admin.'.$resource.'.edit-wizard.save', [$owner, 'requirements']), ['path' => $second->id])->assertRedirect($url('fees', $second->id));
    $this->from($url('fees', $second->id))->post(route('admin.'.$resource.'.edit-wizard.save', [$owner, 'fees']), ['path' => $second->id, 'option_content' => 1, 'fees' => [['label' => 'Unfinished', 'type' => 'fixed']]])->assertSessionHasErrors('fees.0.amount_min')->assertSessionHasInput('path', $second->id);
    $this->get($url('fees'))->assertOk()->assertViewHas('selectedPath', $second->id)->assertSee($url('requirements', $second->id), false);
    $this->get($url('review'))->assertOk()->assertViewHas('selectedPath', $second->id);
    $this->get(route('admin.'.$resource.'.edit', $owner))->assertOk()->assertViewHas('selectedPath', $second->id);
    $this->get($url('guide', $first->id))->assertOk()->assertSee('Procedure 0')->assertDontSee('Procedure 1');
    expect($owner->requirementSets()->count())->toBe(2)->and($first->groups()->count())->toBe(1)->and($second->groups()->count())->toBe(1);
})->with('request option owners');

it('edits channel fees and offices without removing other options reusable records or special cases', function ($resource, $model) {
    $owner = $model::create(['name' => 'Associations fixture', 'research_metadata' => ['status' => 'draft', 'special_cases' => [['id' => 'saved-case', 'title' => 'Delayed Registration', 'description' => 'Separate help']]]]);
    $fee1 = $owner->fees()->create(['label' => 'Counter fee', 'type' => 'free', 'sort_order' => 0]);
    $fee2 = $owner->fees()->create(['label' => 'Online fee', 'type' => 'free', 'sort_order' => 1]);
    $office1 = Office::create(['name' => 'Counter branch', 'status' => 'active']); $office2 = Office::create(['name' => 'Online provider', 'status' => 'active']);
    $owner->offices()->attach([$office1->id, $office2->id]);
    $first = $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => optionWizardChannel(['fee_ids' => [$fee1->id], 'office_ids' => [$office1->id]])]);
    $second = $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => optionWizardChannel(['label' => 'Online', 'fee_ids' => [$fee2->id], 'office_ids' => [$office2->id]])]);
    $cases = $owner->research_metadata['special_cases'];
    $url = fn ($step) => route('admin.'.$resource.'.edit-wizard.show', [$owner, $step, 'path' => $first->id]);
    $this->get($url('fees'))->assertOk()->assertSee('Counter fee')->assertDontSee('Online fee');
    $this->get($url('offices'))->assertOk()->assertSee('No Request Method is required.')->assertDontSee('Editing Request Method:');
    $this->post(route('admin.'.$resource.'.edit-wizard.save', [$owner, 'fees']), ['option_content' => 1, 'path' => $first->id, 'fees' => [['id' => $fee1->id, 'label' => 'Updated counter fee', 'type' => 'free'], ['label' => 'Counter delivery', 'type' => 'free']]])->assertSessionHasNoErrors();
    expect($owner->fees()->count())->toBe(3)->and($fee2->fresh()->label)->toBe('Online fee')->and($first->fresh()->request_channel['fee_ids'])->toHaveCount(2);
    $this->post(route('admin.'.$resource.'.edit-wizard.save', [$owner, 'offices']), ['option_content' => 1, 'path' => $first->id, 'office_links_present' => 1, 'office_links' => []])->assertSessionHasErrors('office_ids');
    // Offices are saved for the whole entry, so one still used by a Request Method cannot be unlinked.
    expect($owner->offices()->count())->toBe(2)->and($first->fresh()->request_channel['office_ids'])->toBe([$office1->id])->and($second->fresh()->request_channel['office_ids'])->toBe([$office2->id]);
    $api = '/api/'.$resource.'/'.$owner->id;
    $this->getJson($api)->assertOk()->assertJsonCount(2, 'data.request_paths.0.fees')->assertJsonPath('data.request_paths.0.offices.0.id', $office1->id)->assertJsonPath('data.request_paths.1.fees.0.id', $fee2->id)->assertJsonPath('data.request_paths.1.offices.0.id', $office2->id);
    $this->post(route('admin.'.$resource.'.edit-wizard.save', [$owner, 'fees']), ['option_content' => 1, 'path' => $first->id, 'fees' => []])->assertSessionHasNoErrors();
    expect($owner->fees()->count())->toBe(3)->and($first->fresh()->request_channel['fee_ids'])->toBe([])->and($owner->fresh()->research_metadata['special_cases'])->toBe($cases);
    $this->get($url('guide'))->assertOk()->assertSee('Delayed Registration')->assertSee('Special Cases / Additional Help');
})->with('request option owners');

it('recovers option input and rejects stale edits foreign paths and destructive option deletion', function ($resource, $model) {
    $owner = $model::create(['name' => 'Safety fixture']);
    $set = $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => optionWizardChannel()]);
    $editor = route('admin.'.$resource.'.request-options.edit', [$owner, $set->id]);
    $payload = ['path_id' => $set->id, 'application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => optionWizardChannel(['portal_url' => 'invalid-link'])];
    $this->from($editor)->post(route('admin.'.$resource.'.channels', $owner), $payload)->assertSessionHasErrors()->assertSessionHasInput('request_channel.portal_url', 'invalid-link');
    $this->get($editor)->assertOk()->assertSee('invalid-link')->assertSee('role="alert"', false);
    $revision = CatalogRevision::token($owner);
    $set->groups()->create(['rule' => 'all', 'title' => 'Keep this requirement']);
    $this->postJson(route('admin.'.$resource.'.channels', $owner), [...$payload, '_catalog_revision' => $revision])->assertUnprocessable()->assertJsonValidationErrors('_catalog_revision');
    $this->deleteJson(route('admin.'.$resource.'.request-options.destroy', [$owner, $set->id]))->assertUnprocessable();
    $other = $model::create(['name' => 'Other owner']);
    $this->get(route('admin.'.$resource.'.request-options.edit', [$other, $set->id]))->assertNotFound();
    $this->get(route('admin.'.$resource.'.edit-wizard.show', [$other, 'requirements', 'path' => $set->id]))->assertNotFound();
    $this->deleteJson(route('admin.'.$resource.'.request-options.destroy', [$other, $set->id]))->assertNotFound();
    expect($set->fresh()->groups()->count())->toBe(1);
    $empty = $owner->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'all']);
    $this->delete(route('admin.'.$resource.'.request-options.destroy', [$owner, $empty->id]))->assertSessionHasNoErrors();
    expect($owner->requirementSets()->count())->toBe(1)->and($set->fresh()->id)->toBe($set->id);
})->with('request option owners');

it('retains distinct shared selected and empty associations when option forms are saved and reopened', function ($resource, $model) {
    $owner = $model::create(['name' => 'Scope fixture']);
    $fee = $owner->fees()->create(['label' => 'Reusable fee', 'type' => 'free']);
    $office = Office::create(['name' => 'Reusable branch', 'status' => 'active']); $owner->offices()->attach($office);
    $set = $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all']);
    foreach (['all', 'selected', 'none'] as $mode) {
        $channel = optionWizardChannel(['fee_ids' => $mode === 'selected' ? [$fee->id] : [], 'office_ids' => $mode === 'selected' ? [$office->id] : []]);
        $scope = $mode === 'all' ? 'all' : 'selected';
        $this->post(route('admin.'.$resource.'.channels', $owner), ['path_id' => $set->id, 'application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => $channel, 'fee_ids_scope' => $scope, 'office_ids_scope' => $scope])->assertSessionHasNoErrors();
        $saved = $set->fresh()->request_channel;
        expect($saved['fee_ids'])->toBe($mode === 'all' ? null : ($mode === 'selected' ? [$fee->id] : []))
            ->and($saved['office_ids'])->toBe($mode === 'all' ? null : ($mode === 'selected' ? [$office->id] : []));
        $this->get(route('admin.'.$resource.'.request-options.edit', [$owner, $set->id]))->assertOk();
        $this->getJson('/api/'.$resource.'/'.$owner->id)->assertOk()->assertJsonCount($mode === 'none' ? 0 : 1, 'data.request_paths.0.fees')->assertJsonCount($mode === 'none' ? 0 : 1, 'data.request_paths.0.offices');
    }
    expect($owner->fees()->sole()->id)->toBe($fee->id)->and($owner->offices()->sole()->id)->toBe($office->id);
})->with('request option owners');
