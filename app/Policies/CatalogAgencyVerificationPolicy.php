<?php

namespace App\Policies;

use App\Models\CatalogAgencyVerification;
use App\Models\User;

/**
 * Who may do what with agency verifications. The service checks these rules
 * again inside a locked transaction, so a hand-made request cannot skip them.
 */
class CatalogAgencyVerificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessCms();
    }

    public function view(User $user, CatalogAgencyVerification $verification): bool
    {
        return $user->canAccessCms();
    }

    /**
     * Private research details: evidence reference and location, contact role, notes and return/revocation reasons. Only the original recorder
     * and active super_admins may see them; everyone else gets the team summary only.
     */
    public function viewPrivate(User $user, CatalogAgencyVerification $verification): bool
    {
        return $this->isRecorder($user, $verification) || $user->canApproveAgencyVerifications();
    }

    /** Any CMS account may record a draft. A draft is never shown to residents. */
    public function create(User $user): bool
    {
        return $user->canAccessCms();
    }

    public function update(User $user, CatalogAgencyVerification $verification): bool
    {
        return $this->isRecorder($user, $verification) && ($verification->review_state === 'draft'
            || ($verification->outcome === 'documented' && ! $verification->isRevoked()
                && in_array($verification->review_state, ['submitted', 'published'], true)));
    }

    /** The recorder submits their own draft; assertComplete() checks the evidence it needs. */
    public function submit(User $user, CatalogAgencyVerification $verification): bool
    {
        return $verification->outcome !== 'documented' && $this->update($user, $verification);
    }

    /** The recorder may reopen their own submitted pending request to add the agency's response. */
    public function reopen(User $user, CatalogAgencyVerification $verification): bool
    {
        return $this->isRecorder($user, $verification)
            && $verification->review_state === 'submitted' && $verification->outcome === 'pending';
    }

    public function returnToDraft(User $user, CatalogAgencyVerification $verification): bool
    {
        return $user->canApproveAgencyVerifications() && $verification->review_state === 'submitted';
    }

    /** Two-person rule: an active super_admin who did not record it. No override exists. */
    public function approve(User $user, CatalogAgencyVerification $verification): bool
    {
        return $user->canApproveAgencyVerifications()
            && (int) $verification->recorded_by !== (int) $user->id
            && $verification->review_state === 'submitted'
            && ! in_array($verification->outcome, ['pending', 'documented'], true);
    }

    public function markCorrectionsApplied(User $user, CatalogAgencyVerification $verification): bool
    {
        return $user->canApproveAgencyVerifications() && $verification->isActive()
            && $verification->outcome === 'confirmed_with_corrections' && $verification->corrections_applied_at === null;
    }

    public function revoke(User $user, CatalogAgencyVerification $verification): bool
    {
        return $user->canApproveAgencyVerifications() && $verification->isActive();
    }

    private function isRecorder(User $user, CatalogAgencyVerification $verification): bool
    {
        return $user->canAccessCms() && (int) $verification->recorded_by === (int) $user->id;
    }
}
