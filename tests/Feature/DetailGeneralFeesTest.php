<?php

use App\Models\Document;
use App\Models\GovernmentId;

it('keeps method fees out of general detail costs without changing CMS rows', function ($model, $resource) {
    $record = $model::create(['name' => 'Fee separation', 'fee' => 'Old combined summary']);
    $general = $record->fees()->create(['label' => 'General certification fee', 'type' => 'fixed', 'amount_min' => 100]);
    $methodFee = $record->fees()->create(['label' => 'Online delivery fee', 'type' => 'options']);
    $methodFee->options()->create(['label' => 'Courier', 'type' => 'fixed', 'amount_min' => 50]);
    $method = $record->requirementSets()->create([
        'application_type' => 'copy', 'applicant_type' => 'all',
        'request_channel' => ['label' => 'Online request', 'channel' => 'online', 'fee_ids' => [$methodFee->id]],
    ]);
    $feesBefore = $record->fees()->with('options')->get()->toJson();
    $methodBefore = $method->fresh()->toJson();
    $this->getJson('/api/'.$resource.'/'.$record->id)->assertOk()
        ->assertJsonCount(1, 'data.fees')->assertJsonPath('data.fees.0.id', $general->id)
        ->assertJsonPath('data.request_paths.0.fee_ids', [$methodFee->id])
        ->assertJsonCount(1, 'data.request_paths.0.fees')
        ->assertJsonPath('data.request_paths.0.fees.0.id', $methodFee->id)
        ->assertJsonPath('data.request_paths.0.fees.0.options.0.label', 'Courier');
    expect($record->fees()->with('options')->get()->toJson())->toBe($feesBefore)
        ->and($method->fresh()->toJson())->toBe($methodBefore);
})->with([[GovernmentId::class, 'government-ids'], [Document::class, 'documents']]);

it('does not expose a combined legacy summary when all structured fees belong to methods', function ($model, $resource) {
    $record = $model::create(['name' => 'Method fees only', 'fee' => 'Delivery fee: 50']);
    $fee = $record->fees()->create(['label' => 'Delivery fee', 'type' => 'fixed', 'amount_min' => 50]);
    $record->requirementSets()->create([
        'application_type' => 'new', 'applicant_type' => 'all',
        'request_channel' => ['label' => 'Delivery', 'fee_ids' => [$fee->id]],
    ]);
    $this->getJson('/api/'.$resource.'/'.$record->id)->assertOk()
        ->assertJsonPath('data.fees', [])->assertJsonPath('data.fee', null)
        ->assertJsonPath('data.request_paths.0.fees.0.id', $fee->id);
    expect($record->fresh()->fee)->toBe('Delivery fee: 50');
})->with([[GovernmentId::class, 'government-ids'], [Document::class, 'documents']]);

it('keeps general fees when a legacy method has no explicit fee links', function () {
    $record = Document::create(['name' => 'Legacy method']);
    $fee = $record->fees()->create(['label' => 'General fee', 'type' => 'free']);
    $record->requirementSets()->create([
        'application_type' => 'new', 'applicant_type' => 'all', 'request_channel' => ['label' => 'Outlet'],
    ]);
    $this->getJson('/api/documents/'.$record->id)->assertOk()
        ->assertJsonPath('data.fees.0.id', $fee->id)->assertJsonPath('data.request_paths.0.fee_ids', null);
});

it('keeps a legacy general fee when there are no structured fees', function () {
    $record = GovernmentId::create(['name' => 'Legacy general fee', 'fee' => 'Confirm the fee at the counter.']);
    $this->getJson('/api/government-ids/'.$record->id)->assertOk()
        ->assertJsonPath('data.fees', [])->assertJsonPath('data.fee', $record->fee);
});
