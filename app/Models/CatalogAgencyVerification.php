<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One documented contact with a government agency about a catalog record.
 *
 * Separate from research_metadata.sources (public website references).
 * Changed only through App\Services\CatalogAgencyValidation, which enforces the
 * evidence rules and the two-person approval rule.
 * The guards below are a last line of defence: approved history cannot be edited
 * and no verification row can ever be deleted.
 */
class CatalogAgencyVerification extends Model
{
    public const ASPECTS = ['eligibility', 'requirements', 'fees', 'processing_time', 'validity', 'application_process', 'offices'];

    public const METHODS = ['written_letter', 'email', 'in_person_interview', 'phone_interview', 'office_visit', 'official_reply_document', 'other'];

    public const OUTCOMES = ['pending', 'confirmed', 'partially_confirmed', 'confirmed_with_corrections', 'not_confirmed', 'no_response', 'withdrawn'];

    /** Outcomes in which the agency confirmed at least part of the stated scope. */
    public const CONFIRMING = ['confirmed', 'partially_confirmed', 'confirmed_with_corrections'];

    public const ASPECT_LABELS = ['eligibility' => 'Eligibility', 'requirements' => 'Requirements', 'fees' => 'Fees',
        'processing_time' => 'Processing time', 'validity' => 'Validity', 'application_process' => 'Application process', 'offices' => 'Offices'];

    public const METHOD_LABELS = ['written_letter' => 'Written letter', 'email' => 'Email', 'in_person_interview' => 'In-person interview',
        'phone_interview' => 'Phone interview', 'office_visit' => 'Office visit', 'official_reply_document' => 'Reply document from the agency', 'other' => 'Other'];

    public const OUTCOME_LABELS = [
        'pending' => 'Request made, awaiting response',
        'confirmed' => 'Confirmed (only the listed aspects)',
        'partially_confirmed' => 'Partially confirmed',
        'confirmed_with_corrections' => 'Confirmed with corrections',
        'not_confirmed' => 'Not confirmed by the agency',
        'no_response' => 'No response from the agency',
        'withdrawn' => 'Withdrawn (not used)',
    ];

    /** Content a recorder may edit while the row is a draft. */
    public const CONTENT = ['agency_id', 'agency_name', 'office_id', 'office_label', 'method', 'requested_on', 'responded_on',
        'outcome', 'confirmed_aspects', 'not_covered_aspects', 'scope_request_paths', 'scope', 'not_covered_note',
        'corrections_summary', 'evidence_reference', 'evidence_location', 'contact_role', 'notes'];

    /** Fields each workflow transition may change (updated_at is always allowed). */
    private const TRANSITIONS = [
        'draft>draft' => self::CONTENT,
        'draft>submitted' => ['review_state', 'submitted_at'],
        'submitted>draft' => ['review_state', 'returned_at', 'returned_by', 'returned_reason'],
        'submitted>approved' => ['review_state', 'approved_at', 'approved_by', 'approved_by_name',
            'content_snapshot', 'content_fingerprint', 'fingerprint_version'],
        'approved>approved' => ['corrections_applied_at', 'corrections_applied_by', 'revoked_at', 'revoked_by', 'revocation_reason'],
    ];

    /** Workflow fields are set deliberately by the service, never from form input. */
    protected $fillable = self::CONTENT;

    protected $attributes = ['outcome' => 'pending', 'review_state' => 'draft'];

    protected function casts(): array
    {
        return [
            'requested_on' => 'date:Y-m-d',
            'responded_on' => 'date:Y-m-d',
            'confirmed_aspects' => 'array',
            'not_covered_aspects' => 'array',
            'scope_request_paths' => 'array',
            'content_snapshot' => 'array',
            'fingerprint_version' => 'integer',
            'submitted_at' => 'datetime',
            'returned_at' => 'datetime',
            'approved_at' => 'datetime',
            'corrections_applied_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $row) {
            if ($row->review_state !== 'draft' || $row->approved_at || $row->approved_by || $row->revoked_at
                || $row->content_fingerprint || $row->submitted_at) {
                throw new DomainException('A verification can only be created as an unapproved draft.');
            }
        });

        static::updating(function (self $row) {
            $from = (string) $row->getOriginal('review_state');
            $to = (string) $row->review_state;
            $allowed = self::TRANSITIONS["{$from}>{$to}"] ?? null;
            $changed = array_diff(array_keys($row->getDirty()), ['updated_at']);

            if ($allowed === null || array_diff($changed, $allowed) !== []) {
                throw new DomainException("This verification is {$from} and cannot be changed this way.");
            }
            if ($from === 'approved') {
                if ($row->getOriginal('revoked_at')) {
                    throw new DomainException('A revoked verification is final.');
                }
                foreach (['corrections_applied_at', 'corrections_applied_by', 'revoked_at', 'revoked_by', 'revocation_reason'] as $once) {
                    if ($row->isDirty($once) && $row->getOriginal($once) !== null) {
                        throw new DomainException('Approved verification history cannot be rewritten.');
                    }
                }
            }
        });

        static::deleting(function () {
            throw new DomainException('Verification history cannot be deleted. Use the withdrawn outcome for unused drafts.');
        });
    }

    public function verifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->review_state === 'approved';
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** Counts toward the record's status: approved and not revoked. */
    public function isActive(): bool
    {
        return $this->isApproved() && ! $this->isRevoked();
    }
}
