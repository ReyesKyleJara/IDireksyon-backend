<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\CatalogAgencyVerification as Verification;
use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The only way to record, submit, approve, correct or revoke agency verifications.
 *
 * - Drafts can be recorded and edited freely; they are never shown to residents.
 * - confirmed_aspects holds only what the agency actually verified, backed by evidence.
 * - Approval needs an active super_admin who is not the recorder (no override).
 * - Every permission is checked again on the locked row inside a transaction.
 * - The record status is calculated here; it is never taken from input.
 * - Every action is written to the audit log with field names only, never private values.
 */
final class CatalogAgencyValidation
{
    public const STATUSES = ['needs_review', 'public_sources', 'agency_confirmation_pending', 'agency_confirmed'];

    /** CMS wording. Never "verified", "approved by" or "endorsed". */
    public const STATUS_LABELS = [
        'needs_review' => 'Needs Review',
        'public_sources' => 'Based on Public Sources',
        'agency_confirmation_pending' => 'Agency Confirmation Pending',
        'agency_confirmed' => 'Information Confirmed with Agency',
    ];

    /**
     * The four plain statuses shown on the CMS Data Sources page.
     * Always calculated from the records; nobody can choose one by hand.
     * CMS display only: the stored status column and the API keep using STATUSES.
     */
    public const PROGRESS_LABELS = [
        'not_yet_confirmed' => 'Not Yet Confirmed',
        'waiting_for_agency' => 'Waiting for Agency',
        'pending_review' => 'Pending Review',
        'confirmed' => 'Confirmed',
    ];

    /** Endorsement-style wording that public verification text may never contain. */
    private const ENDORSEMENT = '/\b(approved\s+by|government[\s-]+approved|endorse[sd]?|endorsement|certified\s+by|official\s+partners?|accredited|authori[sz]ed\s+by|in\s+partnership\s+with|verified\s+by\s+the\s+government)\b/i';

    /** Contact roles must not carry a personal name. */
    private const PERSONAL_TITLE = "/^\s*(mr|mrs|ms|miss|dr|atty|engr|hon|sir|madam|ma'?am)\b\.?/i";

    /** Outcomes that record an agency response and therefore need documented evidence. */
    private const RESPONSES = ['confirmed', 'partially_confirmed', 'confirmed_with_corrections', 'not_confirmed'];

    public function __construct(private readonly CatalogVerificationFingerprint $fingerprints)
    {
    }

    public function createDraft(User $actor, GovernmentId|Document $record, array $input): Verification
    {
        Gate::forUser($actor)->authorize('create', Verification::class);
        $data = $this->validated($record, $input);

        return DB::transaction(function () use ($actor, $record, $data) {
            $row = new Verification($data);
            $row->verifiable()->associate($record);
            $row->forceFill(['recorded_by' => $actor->id, 'recorded_by_name' => $actor->username ?? $actor->name])->save();
            $this->audit($actor, $row, 'drafted');
            $this->refresh($record);

            return $row;
        });
    }

    public function updateDraft(User $actor, Verification $verification, array $input): Verification
    {
        return $this->locked($actor, 'update', $verification, 'updated', function (Verification $row) use ($input) {
            $row->fill($this->validated($row->verifiable, $input));
            $changed = array_keys($row->getDirty());
            $row->save();

            return $changed;
        });
    }

    public function submit(User $actor, Verification $verification): Verification
    {
        return $this->locked($actor, 'submit', $verification, 'submitted', function (Verification $row) {
            $this->assertComplete($row);
            $row->forceFill(['review_state' => 'submitted', 'submitted_at' => now()])->save();
        });
    }

    /** Lets the recorder add the agency response to their own submitted pending request. */
    public function reopen(User $actor, Verification $verification): Verification
    {
        return $this->locked($actor, 'reopen', $verification, 'reopened', fn (Verification $row) => $row->forceFill([
            'review_state' => 'draft', 'returned_at' => now(), 'returned_by' => $actor->id,
            'returned_reason' => 'Reopened by the recorder to add the agency response.',
        ])->save());
    }

    public function returnToDraft(User $actor, Verification $verification, string $reason): Verification
    {
        $reason = $this->reason($reason, 'returned_reason');

        return $this->locked($actor, 'returnToDraft', $verification, 'returned', fn (Verification $row) => $row->forceFill([
            'review_state' => 'draft', 'returned_at' => now(), 'returned_by' => $actor->id, 'returned_reason' => $reason,
        ])->save());
    }

    public function approve(User $actor, Verification $verification): Verification
    {
        return $this->locked($actor, 'approve', $verification, 'approved', function (Verification $row) use ($actor) {
            $this->assertComplete($row);
            $record = $row->verifiable;
            $snapshot = $this->fingerprints->snapshot($record, $this->scopeAspects($row), $row->scope_request_paths);
            $row->forceFill([
                'review_state' => 'approved', 'approved_at' => now(),
                'approved_by' => $actor->id, 'approved_by_name' => $actor->username ?? $actor->name,
                'content_snapshot' => $snapshot,
                'content_fingerprint' => $this->fingerprints->fingerprint($snapshot),
                'fingerprint_version' => CatalogVerificationFingerprint::VERSION,
            ])->save();
        });
    }

    public function markCorrectionsApplied(User $actor, Verification $verification): Verification
    {
        return $this->locked($actor, 'markCorrectionsApplied', $verification, 'corrections_applied', fn (Verification $row) => $row->forceFill([
            'corrections_applied_at' => now(), 'corrections_applied_by' => $actor->id,
        ])->save());
    }

    public function revoke(User $actor, Verification $verification, string $reason): Verification
    {
        $reason = $this->reason($reason, 'revocation_reason');

        return $this->locked($actor, 'revoke', $verification, 'revoked', fn (Verification $row) => $row->forceFill([
            'revoked_at' => now(), 'revoked_by' => $actor->id, 'revocation_reason' => $reason,
        ])->save());
    }

    /**
     * The record's research validation status, calculated live.
     *
     * @return array{status: string, reasons: list<string>, current: Collection, outdated: Collection,
     *     pending: Collection, corrections_pending: Collection, not_confirmed_aspects: list<string>}
     */
    public function evaluate(GovernmentId|Document $record): array
    {
        $rows = $record->agencyVerifications()->get();
        $active = $rows->filter->isActive();
        $correctionsPending = $active->where('outcome', 'confirmed_with_corrections')->whereNull('corrections_applied_at')->values();
        $notConfirmed = $this->aspectsLatestNotConfirmed($active);
        [$current, $outdated] = $active->whereIn('outcome', Verification::CONFIRMING)
            ->reject(fn (Verification $row) => $row->outcome === 'confirmed_with_corrections' && $row->corrections_applied_at === null)
            ->partition(fn (Verification $row) => $this->fingerprints->isCurrent($row));
        // Pending means a submitted record of an actual request (dated, with a contact method).
        // A draft, or submitting a CMS record alone, never implies the agency was contacted.
        $pending = $rows->where('review_state', 'submitted')->where('outcome', 'pending')
            ->filter(fn (Verification $row) => $row->requested_on !== null && in_array($row->method, Verification::METHODS, true))
            ->values();
        $sources = $record->research_metadata['sources'] ?? [];

        [$status, $reasons] = match (true) {
            $correctionsPending->isNotEmpty() => ['needs_review', ['Agency corrections have not been applied yet.']],
            $notConfirmed !== [] => ['needs_review', ['The agency did not confirm: '.implode(', ', $notConfirmed).'.']],
            $current->isNotEmpty() => ['agency_confirmed', ['Confirmed with the agency for the documented scope only.']],
            $outdated->isNotEmpty() => ['needs_review', ['Confirmed information changed after approval; a new confirmation is needed.']],
            $pending->isNotEmpty() => ['agency_confirmation_pending', ['A verification request was submitted and awaits the agency response.']],
            is_array($sources) && $sources !== [] => ['public_sources', ['Based on public sources only; not confirmed with the agency.']],
            default => ['needs_review', ['No public source or agency confirmation yet.']],
        };

        return ['status' => $status, 'reasons' => $reasons, 'current' => $current->values(), 'outdated' => $outdated->values(),
            'pending' => $pending, 'corrections_pending' => $correctionsPending, 'not_confirmed_aspects' => $notConfirmed];
    }

    /**
     * Where the research stands, in one of four plain labels, with short reasons.
     *
     * "Confirmed" only ever follows evaluate()'s agency_confirmed, so the same evidence,
     * two-person approval and freshness rules apply. Problems (outdated,
     * corrections, not confirmed) are always listed as reasons, whatever the label.
     *
     * @return array{key: string, label: string, reasons: list<string>}
     */
    public function progress(GovernmentId|Document $record, ?array $evaluation = null): array
    {
        $evaluation ??= $this->evaluate($record);
        $awaitingReview = $record->agencyVerifications()->where('review_state', 'submitted')
            ->where('outcome', '!=', 'pending')->exists();
        $sources = $record->research_metadata['sources'] ?? [];

        $problems = [];
        if ($evaluation['outdated']->isNotEmpty()) {
            $problems[] = 'A confirmation is outdated because the information changed after approval.';
        }
        if ($evaluation['corrections_pending']->isNotEmpty()) {
            $problems[] = 'The agency’s corrections are not applied yet.';
        }
        if ($evaluation['not_confirmed_aspects'] !== []) {
            $problems[] = 'The agency did not confirm: '.collect($evaluation['not_confirmed_aspects'])
                ->map(fn ($key) => strtolower(Verification::ASPECT_LABELS[$key] ?? $key))->implode(', ').'.';
        }

        [$key, $reason] = match (true) {
            $evaluation['status'] === 'agency_confirmed' => ['confirmed', 'An agency confirmed part of this information. Residents see exactly which parts.'],
            $awaitingReview => ['pending_review', 'The agency’s reply is recorded and is waiting for a second super admin to check it.'],
            $evaluation['pending']->isNotEmpty() => ['waiting_for_agency', 'A request was sent to the agency. Their reply is not recorded yet.'],
            $problems !== [] => ['not_yet_confirmed', null],
            is_array($sources) && $sources !== [] => ['not_yet_confirmed', 'Based on online sources only. No agency has confirmed it yet.'],
            default => ['not_yet_confirmed', 'No online sources or agency confirmation yet.'],
        };
        if ($key === 'confirmed' && $awaitingReview) {
            $problems[] = 'A newer agency reply is waiting for review.';
        }

        return ['key' => $key, 'label' => self::PROGRESS_LABELS[$key],
            'reasons' => array_values(array_filter([$reason, ...$problems]))];
    }

    /**
     * The only verification data residents receive (the API's agency_verification field).
     *
     * Confirmations appear only while the record's overall status is agency_confirmed,
     * so corrections pending, a newer "not confirmed" response, or an outdated
     * confirmation hide them. Drafts, submitted, returned, pending, revoked and
     * outdated rows are never included. Built from an allowlist: evidence, locations,
     * notes, contact roles, notes on what was not covered, user
     * details, snapshots and fingerprints are never sent.
     *
     * @return array{status: ?string, confirmations: list<array<string, mixed>>}
     */
    public function publicSummary(GovernmentId|Document $record): array
    {
        $evaluation = $this->evaluate($record);
        if ($evaluation['status'] !== 'agency_confirmed') {
            return ['status' => null, 'confirmations' => []];
        }
        $paths = $this->pathLabels($record);

        return ['status' => 'agency_confirmed', 'confirmations' => $evaluation['current']->map(fn (Verification $row) => [
            'agency_name' => $row->agency_name,
            'office_label' => $row->office_label,
            'outcome' => $row->outcome,
            'scope' => $row->scope,
            'confirmed_aspects' => array_values($row->confirmed_aspects ?? []),
            // Everything not verified is "not covered", whether or not the researcher listed it.
            'not_covered_aspects' => array_values(array_diff(Verification::ASPECTS, $row->confirmed_aspects ?? [])),
            'applies_to_all_request_paths' => $row->scope_request_paths === null,
            'request_paths' => $row->scope_request_paths === null ? [] : collect($row->scope_request_paths)
                ->map(fn ($id) => ['id' => (int) $id, 'label' => $paths[$id] ?? null])->values()->all(),
            'confirmed_on' => $row->responded_on?->format('Y-m-d'),
        ])->values()->all()];
    }

    /** @return array<int, string> request path id => the name residents see */
    public function pathLabels(GovernmentId|Document $record): array
    {
        return $record->requirementSets()->get()
            ->mapWithKeys(fn ($set) => [$set->id => ($set->request_channel['label'] ?? null) ?: $set->display_label])->all();
    }

    /** Updates only the cached status column: no timestamps, no audit noise, no other field. */
    public function refresh(GovernmentId|Document $record): string
    {
        $status = $this->evaluate($record)['status'];
        DB::table($record->getTable())->where('id', $record->getKey())
            ->where('agency_validation_status', '!=', $status)->update(['agency_validation_status' => $status]);

        return $status;
    }

    /** Structural rules, checked on every save. Completeness for an outcome is checked on submit and approve. */
    public function validated(GovernmentId|Document $record, array $input): array
    {
        $validator = Validator::make($input, [
            'agency_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')],
            'agency_name' => ['required_without:agency_id', 'nullable', 'string', 'max:255'],
            'office_id' => ['nullable', 'integer', Rule::exists('offices', 'id')],
            'office_label' => ['nullable', 'string', 'max:255'],
            'method' => ['required', Rule::in(Verification::METHODS)],
            'requested_on' => ['nullable', 'date_format:Y-m-d'],
            'responded_on' => ['nullable', 'date_format:Y-m-d'],
            'outcome' => ['required', Rule::in(Verification::OUTCOMES)],
            'confirmed_aspects' => ['present', 'array'],
            'confirmed_aspects.*' => ['string', 'distinct', Rule::in(Verification::ASPECTS)],
            'not_covered_aspects' => ['nullable', 'array'],
            'not_covered_aspects.*' => ['string', 'distinct', Rule::in(Verification::ASPECTS)],
            'scope_request_paths' => ['nullable', 'array', 'list', 'min:1'],
            'scope_request_paths.*' => ['integer', 'distinct'],
            'scope' => ['required', 'string', 'min:10', 'max:1000'],
            'not_covered_note' => ['nullable', 'string', 'max:2000'],
            'corrections_summary' => ['nullable', 'string', 'max:2000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'evidence_location' => ['nullable', 'string', 'max:255'],
            'contact_role' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $validator->after(function ($validator) use ($record, $input) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $this->checkDates($validator, $input);
            $this->checkAspects($validator, $input);
            $this->checkRequestPaths($validator, $record, $input['scope_request_paths'] ?? null);
            $this->checkWording($validator, $input);
        });
        $data = $validator->validate();

        $data['not_covered_aspects'] = array_values($data['not_covered_aspects'] ?? []);
        $data['confirmed_aspects'] = array_values($data['confirmed_aspects']);
        $data['scope_request_paths'] = isset($data['scope_request_paths'])
            ? collect($data['scope_request_paths'])->map(fn ($id) => (int) $id)->sort()->values()->all() : null;
        if (! empty($data['agency_id'])) {
            $data['agency_name'] = Agency::findOrFail($data['agency_id'])->name;
        }

        return array_intersect_key($data, array_flip(Verification::CONTENT));
    }

    /** @return array<string, string> field => message for anything this outcome still needs. */
    public function completenessErrors(Verification $row): array
    {
        $needs = [];
        $blank = fn ($value) => $value === null || (is_string($value) && trim($value) === '') || $value === [];
        $require = function (string $field, string $message) use ($row, $blank, &$needs) {
            if ($blank($row->{$field})) {
                $needs[$field] = $message;
            }
        };
        if (in_array($row->outcome, self::RESPONSES, true)) {
            $require('responded_on', 'Record the date the agency responded.');
            $require('evidence_reference', 'A documented evidence reference is required for an agency response.');
        }
        if (in_array($row->outcome, ['pending', 'no_response'], true)) {
            $require('requested_on', 'Record the date the request was made.');
        }
        if ($row->outcome === 'partially_confirmed') {
            $require('not_covered_aspects', 'List what the agency did not confirm.');
            $require('not_covered_note', 'Explain what was not confirmed.');
        }
        if ($row->outcome === 'not_confirmed') {
            $require('not_covered_aspects', 'List what the agency did not confirm.');
        }
        if ($row->outcome === 'confirmed_with_corrections') {
            $require('corrections_summary', 'Summarise the corrections the agency gave.');
        }

        return $needs;
    }

    /** Aspects a row covers: verified aspects when confirming, otherwise the aspects asked about. */
    private function scopeAspects(Verification $row): array
    {
        return in_array($row->outcome, Verification::CONFIRMING, true)
            ? ($row->confirmed_aspects ?? []) : ($row->not_covered_aspects ?? []);
    }

    /** @return list<string> aspects whose most recent approved agency response is "not confirmed" */
    private function aspectsLatestNotConfirmed(Collection $active): array
    {
        $responses = $active->whereIn('outcome', self::RESPONSES);
        $declined = [];
        foreach (Verification::ASPECTS as $aspect) {
            $latest = $responses
                ->filter(fn (Verification $row) => in_array($aspect, $row->outcome === 'not_confirmed'
                    ? ($row->not_covered_aspects ?? []) : ($row->confirmed_aspects ?? []), true))
                ->sortBy(fn (Verification $row) => [$row->responded_on?->toDateString(), $row->approved_at?->toIso8601String(), $row->id])
                ->last();
            if ($latest?->outcome === 'not_confirmed') {
                $declined[] = $aspect;
            }
        }

        return $declined;
    }

    /** Contact and reply dates: never in the future, and the reply never before the request. */
    private function checkDates($validator, array $input): void
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        foreach (['requested_on', 'responded_on'] as $field) {
            if (empty($input[$field])) {
                continue;
            }
            $date = CarbonImmutable::createFromFormat('Y-m-d', $input[$field], 'Asia/Manila')->startOfDay();
            if ($date->greaterThan($today)) {
                $validator->errors()->add($field, 'This date cannot be in the future.');
            }
        }
        if (! empty($input['requested_on']) && ! empty($input['responded_on']) && $input['responded_on'] < $input['requested_on']) {
            $validator->errors()->add('responded_on', 'The response date cannot be before the request date.');
        }
    }

    private function checkAspects($validator, array $input): void
    {
        $confirmed = $input['confirmed_aspects'] ?? [];
        $notCovered = $input['not_covered_aspects'] ?? [];
        $confirming = in_array($input['outcome'], Verification::CONFIRMING, true);

        if ($confirming && $confirmed === []) {
            $validator->errors()->add('confirmed_aspects', 'List exactly what the agency verified.');
        }
        if (! $confirming && $confirmed !== []) {
            $validator->errors()->add('confirmed_aspects', 'Nothing can be listed as confirmed for this outcome.');
        }
        if (array_intersect($confirmed, $notCovered) !== []) {
            $validator->errors()->add('not_covered_aspects', 'An aspect cannot be both confirmed and not covered.');
        }
        if ($input['outcome'] === 'confirmed' && $notCovered !== []) {
            $validator->errors()->add('not_covered_aspects', 'Use "partially confirmed" when part of the request was not confirmed.');
        }
        if ($input['method'] === 'other' && blank($input['notes'] ?? null)) {
            $validator->errors()->add('notes', 'Describe the method when "other" is selected.');
        }
    }

    private function checkRequestPaths($validator, GovernmentId|Document $record, ?array $paths): void
    {
        if ($paths === null) {
            return;
        }
        $own = $record->requirementSets()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (array_diff(array_map('intval', $paths), $own) !== []) {
            $validator->errors()->add('scope_request_paths', 'Only request paths of this record can be in scope.');
        }
    }

    private function checkWording($validator, array $input): void
    {
        foreach (['scope', 'not_covered_note', 'agency_name', 'office_label'] as $field) {
            if (is_string($input[$field] ?? null) && preg_match(self::ENDORSEMENT, $input[$field])) {
                $validator->errors()->add($field, 'Describe only what was confirmed; endorsement or approval wording is not allowed.');
            }
        }
        if (is_string($input['contact_role'] ?? null) && preg_match(self::PERSONAL_TITLE, $input['contact_role'])) {
            $validator->errors()->add('contact_role', 'Record the role only, never a personal name or title.');
        }
    }

    /** Run on submit and approve: completeness, plus the dates checked again now. */
    private function assertComplete(Verification $row): void
    {
        $errors = $this->completenessErrors($row);
        if (! in_array($row->method, Verification::METHODS, true)) {
            $errors['method'] = 'Record how the agency was contacted.';
        }
        $dates = Validator::make([], []);
        $this->checkDates($dates, [
            'requested_on' => $row->requested_on?->format('Y-m-d'),
            'responded_on' => $row->responded_on?->format('Y-m-d'),
        ]);
        foreach ($dates->errors()->messages() as $field => $messages) {
            $errors[$field] ??= $messages[0];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function reason(string $reason, string $field): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([$field => 'Give a reason of 10 to 2000 characters.']);
        }

        return $reason;
    }

    /** Re-checks the permission on the locked, freshly read row and actor before changing anything. */
    private function locked(User $actor, string $ability, Verification $verification, string $action, callable $change): Verification
    {
        Gate::forUser($actor)->authorize($ability, $verification);

        return DB::transaction(function () use ($actor, $ability, $verification, $action, $change) {
            $row = Verification::query()->lockForUpdate()->findOrFail($verification->getKey());
            $freshActor = User::query()->findOrFail($actor->getKey());
            Gate::forUser($freshActor)->authorize($ability, $row);
            $changed = $change($row);
            $this->audit($freshActor, $row, $action, is_array($changed) ? $changed : []);
            $this->refresh($row->verifiable);

            return $row;
        });
    }

    /**
     * Audit entry on the catalog record: action and field NAMES only.
     * Evidence, locations, contact roles, notes, scope text and reasons are never logged.
     */
    private function audit(User $actor, Verification $row, string $action, array $fields = []): void
    {
        $record = $row->verifiable;
        ContentChangeLog::create([
            'user_id' => $actor->getKey(),
            'action' => 'verification_'.$action,
            'entity_type' => Str::snake(class_basename($record)),
            'entity_id' => $record->getKey(),
            'entity_name' => $record->name,
            'changed_fields' => array_values(array_unique(['agency_verification #'.$row->getKey(),
                ...array_values(array_diff($fields, ['updated_at']))])),
            'created_at' => now(),
        ]);
    }
}
