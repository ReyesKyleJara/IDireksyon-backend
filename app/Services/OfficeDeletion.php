<?php

namespace App\Services;

use App\Models\CatalogAgencyVerification;
use App\Models\Office;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Permanently deletes an office that nothing else depends on.
 *
 * Linked IDs and documents block deletion: their request methods and special
 * cases keep office IDs inside JSON, which a database cascade would leave
 * pointing at nothing. Agency verification history blocks it too, because the
 * database would otherwise blank the office on approved, unchangeable rows.
 * Schedules belong to the office and go with it.
 */
class OfficeDeletion
{
    public const LINKED_MESSAGE = 'This office is still linked to IDs or documents. Remove it from them first, then delete it.';

    public const VERIFICATION_MESSAGE = 'This office is part of agency verification history, which must be kept. It cannot be deleted.';

    /**
     * What stops deletion, for the CMS to show before anyone tries.
     *
     * @return array{records: list<array{name: string, type: string, url: string}>, verified: bool, can_delete: bool}
     */
    public function blockers(Office $office): array
    {
        $records = [
            ...$office->governmentIds()->orderBy('name')->get(['government_ids.id', 'name'])
                ->map(fn ($id) => ['name' => $id->name, 'type' => 'ID', 'url' => route('admin.government-ids.edit', $id)]),
            ...$office->documents()->orderBy('name')->get(['documents.id', 'name'])
                ->map(fn ($document) => ['name' => $document->name, 'type' => 'Document', 'url' => route('admin.documents.edit', $document)]),
        ];
        $verified = CatalogAgencyVerification::where('office_id', $office->id)->exists();

        return ['records' => $records, 'verified' => $verified, 'can_delete' => $records === [] && ! $verified];
    }

    /** Checks again inside the transaction, so a link added meanwhile still blocks. */
    public function delete(Office $office): string
    {
        return DB::transaction(function () use ($office) {
            $office = Office::whereKey($office->id)->lockForUpdate()->firstOrFail();
            $linked = DB::table('government_id_office')->where('office_id', $office->id)->lockForUpdate()->exists()
                || DB::table('document_office')->where('office_id', $office->id)->lockForUpdate()->exists();
            if ($linked) {
                throw ValidationException::withMessages(['office_deletion' => self::LINKED_MESSAGE]);
            }
            if (CatalogAgencyVerification::where('office_id', $office->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['office_deletion' => self::VERIFICATION_MESSAGE]);
            }

            $name = $office->name;
            // The audit observer records the deletion in the content change log.
            $office->delete();

            return $name.' deleted.';
        });
    }
}
