<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

it('saves edits clears and retrieves notes without changing an existing range', function (string $resource, string $model) {
    $range = ['processing_time_type' => 'range', 'processing_time_min' => 1,
        'processing_time_max' => 10, 'processing_time_unit' => 'working_day',
        'processing_time' => '1–10 working days'];
    $record = $model::create(['name' => 'Processing fixture', ...$range]);
    $url = route("admin.$resource.edit-wizard.save", [$record, 'basic']);
    $page = route("admin.$resource.edit-wizard.show", [$record, 'basic']);
    $this->get($page)->assertOk()->assertSee('Processing Time Note')
        ->assertSee('Add additional information about processing times, delays, or special conditions.')
        ->assertSee('Optional. Explain conditions that may affect the estimated processing time.');
    $this->getJson("/api/$resource/{$record->id}")->assertOk()->assertJsonPath('data.processing_time_note', null);

    foreach (['May require HIT verification.', 'Updated release conditions.', '', '   '] as $note) {
        $expected = trim($note) === '' ? null : $note;
        $this->post($url, ['name' => $record->name, 'processing_time_note' => $note])
            ->assertSessionHasNoErrors()->assertRedirect();
        expect($record->fresh()->processing_time_note)->toBe($expected);
        foreach ($range as $key => $value) {
            expect($record->fresh()->getAttribute($key))->toBe($value);
        }
        $this->getJson("/api/$resource/{$record->id}")->assertOk()
            ->assertJsonPath('data.processing_time_note', $expected)
            ->assertJsonPath('data.processing_time', $range['processing_time']);
        if ($expected !== null) $this->get($page)->assertSee($expected);
    }
    $this->post($url, ['name' => $record->name, 'processing_time_note' => ['invalid']])
        ->assertSessionHasErrors('processing_time_note');
    $this->post($url, ['name' => $record->name, 'processing_time_note' => str_repeat('x', 10001)])
        ->assertSessionHasErrors('processing_time_note');
})->with([
    'government IDs' => ['government-ids', GovernmentId::class],
    'documents' => ['documents', Document::class],
]);

it('creates notes with range values through the creation wizard', function (string $resource, string $model) {
    $key = $this->get(route("admin.$resource.create"))->assertOk()->viewData('creationKey');
    $this->post(route("admin.$resource.wizard.store"), [
        'creation_key' => $key, 'name' => 'New note fixture',
        'processing_time_type' => 'range', 'processing_time_min' => 1,
        'processing_time_max' => 10, 'processing_time_unit' => 'working_day',
        'processing_time_note' => 'Additional verification may delay release.',
    ])->assertSessionHasNoErrors()->assertRedirect();
    $record = $model::where('name', 'New note fixture')->sole();
    expect($record->processing_time_note)->toBe('Additional verification may delay release.')
        ->and($record->processing_time)->toBe('1–10 working days');
})->with([
    'government IDs' => ['government-ids', GovernmentId::class],
    'documents' => ['documents', Document::class],
]);
