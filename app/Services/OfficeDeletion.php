<?php

namespace App\Services;

use App\Models\CatalogAgencyVerification;
use App\Models\Office;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Deletes an office and removes its catalog selections in one transaction. */
class OfficeDeletion
{
    public const VERIFICATION_MESSAGE = 'This office is part of agency verification history, which must be kept. It cannot be deleted.';

    /** @return array{linked_count: int, can_delete: bool} */
    public function blockers(Office $office): array
    {
        return [
            'linked_count' => $office->governmentIds()->count() + $office->documents()->count(),
            'can_delete' => ! CatalogAgencyVerification::where('office_id', $office->id)->exists(),
        ];
    }

    /** Keeps catalog references and office deletion in the same transaction. */
    public function delete(Office $office): string
    {
        return DB::transaction(function () use ($office) {
            $office = Office::whereKey($office->id)->lockForUpdate()->firstOrFail();
            if (CatalogAgencyVerification::where('office_id', $office->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['office_deletion' => self::VERIFICATION_MESSAGE]);
            }

            foreach ([$office->governmentIds(), $office->documents()] as $records) {
                foreach ($records->lockForUpdate()->get() as $record) {
                    foreach ($record->requirementSets()->lockForUpdate()->get() as $set) {
                        $channel = $this->withoutOffice($set->request_channel ?? [], $office->id);
                        if ($channel !== ($set->request_channel ?? [])) {
                            $set->update(['request_channel' => $channel]);
                        }
                    }

                    $research = $record->research_metadata ?? [];
                    if (isset($research['special_cases'])) {
                        $cases = array_map(fn ($case) => $this->withoutOffice($case, $office->id), $research['special_cases']);
                        if ($cases !== $research['special_cases']) {
                            $research['special_cases'] = $cases;
                            $record->update(['research_metadata' => $research]);
                        }
                    }
                }
            }

            $name = $office->name;
            // The audit observer records the deletion in the content change log.
            $office->delete();

            return $name.' deleted.';
        });
    }

    private function withoutOffice(array $data, int $officeId): array
    {
        if (is_array($data['office_ids'] ?? null)) {
            $data['office_ids'] = array_values(array_filter(
                $data['office_ids'], fn ($id) => (int) $id !== $officeId,
            ));
        }

        return $data;
    }
}
