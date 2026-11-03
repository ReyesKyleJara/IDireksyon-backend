<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
});

dataset('not applicable owners', [['government-ids', GovernmentId::class], ['documents', Document::class]]);

function notApplicableFields(): array
{
    return array_fill_keys(['validity_type', 'processing_time_type', 'eligibility_age_type', 'eligibility_citizenship', 'eligibility_residency'], 'not_applicable');
}

function notApplicableXpath($response): DOMXPath
{
    $dom = new DOMDocument();
    @$dom->loadHTML($response->getContent());
    return new DOMXPath($dom);
}

it('creates and reopens all five Not Applicable selections for both catalogs', function ($resource, $model) {
    $this->post(route('admin.'.$resource.'.store'), ['name' => 'Not applicable fixture', ...notApplicableFields()])->assertSessionHasNoErrors();
    $record = $model::sole();
    foreach (notApplicableFields() as $field => $value) expect($record->$field)->toBe($value);
    expect($record->validity)->toBe('Not applicable')->and($record->processing_time)->toBe('Not applicable')
        ->and($record->eligibility)->toBe("Age: Not applicable.\nCitizenship: Not applicable.\nResidency: Not applicable.");
    $page = $this->get(route('admin.'.$resource.'.edit-wizard.show', [$record, 'basic']))->assertOk();
    $xpath = notApplicableXpath($page);
    foreach (notApplicableFields() as $field => $_) {
        $options = $xpath->query('//select[@name="'.$field.'"]/option[@value="not_applicable" and @selected]');
        expect($options->length)->toBe(1)->and(trim($options->item(0)->textContent))->toBe('Not Applicable');
    }
})->with('not applicable owners');

it('updates only the edited record and retains existing choices and unrelated notes', function ($resource, $model) {
    $saved = ['name' => 'Existing fixture', 'validity_type' => 'fixed', 'validity_value' => 5, 'validity_unit' => 'year', 'validity' => '5 years', 'processing_time_type' => 'fixed', 'processing_time_min' => 3, 'processing_time_unit' => 'working_day', 'processing_time' => '3 working days', 'eligibility_age_type' => 'range', 'eligibility_min_age' => 18, 'eligibility_max_age' => 60, 'eligibility_citizenship' => 'filipino', 'eligibility_residency' => 'custom', 'eligibility_residency_custom' => 'Saved residency', 'eligibility_other_conditions' => 'Keep these conditions'];
    $record = $model::create($saved); $other = $model::create([...$saved, 'name' => 'Untouched fixture']);
    $before = $other->fresh()->toArray();
    $this->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'basic']), ['name' => $record->name, ...notApplicableFields(), 'eligibility_other_conditions' => 'Keep these conditions'])->assertSessionHasNoErrors();
    $record->refresh();
    foreach (notApplicableFields() as $field => $value) expect($record->$field)->toBe($value);
    expect($record->eligibility_other_conditions)->toBe('Keep these conditions')->and($other->fresh()->toArray())->toBe($before);
    foreach (['validity_value', 'validity_unit', 'processing_time_min', 'processing_time_max', 'processing_time_unit', 'eligibility_min_age', 'eligibility_max_age', 'eligibility_residency_custom'] as $field) expect($record->$field)->toBeNull();
    $xpath = notApplicableXpath($this->get(route('admin.'.$resource.'.edit-wizard.show', [$other, 'basic']))->assertOk());
    foreach (['validity_type' => 'fixed', 'processing_time_type' => 'fixed', 'eligibility_age_type' => 'range', 'eligibility_citizenship' => 'filipino', 'eligibility_residency' => 'custom'] as $field => $value) expect($xpath->query('//select[@name="'.$field.'"]/option[@value="'.$value.'" and @selected]')->length)->toBe(1);
    foreach (['validity_type' => ['fixed', 'lifetime', 'no_expiration', 'until_age', 'varies'], 'processing_time_type' => ['fixed', 'range', 'same_day', 'varies'], 'eligibility_age_type' => ['none', 'minimum', 'range'], 'eligibility_citizenship' => ['none', 'filipino'], 'eligibility_residency' => ['none', 'philippines', 'santa_maria', 'custom']] as $field => $values) foreach ($values as $value) expect($xpath->query('//select[@name="'.$field.'"]/option[@value="'.$value.'"]')->length)->toBe(1);
})->with('not applicable owners');

it('restores Not Applicable selections after a validation error without changing saved values', function ($resource, $model) {
    $record = $model::create(['name' => 'Keep name', 'validity_type' => 'lifetime']);
    $pageUrl = route('admin.'.$resource.'.edit-wizard.show', [$record, 'basic']);
    $this->from($pageUrl)->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'basic']), ['name' => '', ...notApplicableFields()])->assertSessionHasErrors('name');
    $xpath = notApplicableXpath($this->get($pageUrl)->assertOk());
    foreach (notApplicableFields() as $field => $_) expect($xpath->query('//select[@name="'.$field.'"]/option[@value="not_applicable" and @selected]')->length)->toBe(1);
    expect($record->fresh()->validity_type)->toBe('lifetime')->and($record->fresh()->name)->toBe('Keep name');
})->with('not applicable owners');
