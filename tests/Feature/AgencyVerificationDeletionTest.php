<?php

require_once __DIR__.'/../Support/agency_verification.php';

use App\Models\CatalogAgencyVerification;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Services\CatalogRecordDeletion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/* A catalog record with agency verification history can never be deleted. Fake data, in-memory SQLite. */

function deletionRoute(GovernmentId|Document $record, string $action): string
{
    return route('admin.'.($record instanceof Document ? 'documents' : 'government-ids').'.'.$action, $record);
}

function deletionDocument(): Document
{
    return Document::create(['name' => 'Fixture document']);
}

it('blocks deleting a record with any verification history', function (Closure $makeRecord, Closure $history) {
    $record = $makeRecord();
    $history($record);
    $this->actingAs(avSuperAdmin());

    $this->getJson(deletionRoute($record, 'delete-impact'))->assertOk()
        ->assertJsonPath('data.can_delete', false)
        ->assertJsonFragment(['body' => CatalogRecordDeletion::VERIFICATION_BLOCKED_MESSAGE]);
    $this->deleteJson(deletionRoute($record, 'destroy'))->assertUnprocessable()->assertJsonValidationErrors('catalog_deletion');
    expect(fn () => app(CatalogRecordDeletion::class)->delete($record))->toThrow(ValidationException::class, 'agency verification history')
        ->and($record->fresh())->not->toBeNull()
        ->and(CatalogAgencyVerification::count())->toBe(1);
})->with([
    'government id' => [fn () => avRecord()],
    'document' => [fn () => deletionDocument()],
])->with([
    'draft only' => [fn ($record) => avDraft($record)],
    'approved' => [fn ($record) => avApproved($record, ['confirmed_aspects' => ['requirements']])],
    'revoked' => [fn ($record) => avService()->revoke(avSuperAdmin(), avApproved($record, ['confirmed_aspects' => ['requirements']]), 'Revoked in a deletion test.')],
]);

it('still deletes records without verification history', function (Closure $makeRecord) {
    $record = $makeRecord();
    $this->actingAs(avSuperAdmin());

    $this->getJson(deletionRoute($record, 'delete-impact'))->assertOk()->assertJsonPath('data.can_delete', true);
    $this->delete(deletionRoute($record, 'destroy'))->assertSessionHas('success');
    expect($record->fresh())->toBeNull();
})->with([
    'government id' => [fn () => avRecord()],
    'document' => [fn () => deletionDocument()],
]);

it('refuses to clear research content while Government IDs have verification history', function () {
    Storage::fake('local');
    $record = avRecord();
    avDraft($record);

    $this->artisan('idireksyon:clear-research', ['--apply' => true])->assertFailed();
    expect($record->fresh())->not->toBeNull()
        ->and(CatalogAgencyVerification::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
