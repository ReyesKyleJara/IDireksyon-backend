<?php

require_once __DIR__.'/../Support/agency_verification.php';

use App\Models\CatalogAgencyVerification;
use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
 * Agency Confirmation routes, called the way a hand-made request would call them.
 * The form lives in Data Sources (Basic Information step); every action returns there.
 * Fake data only (fixture agencies), in-memory SQLite.
 */

const RV_PRIVATE = ['PRIVATE-EVIDENCE-REF', 'PRIVATE-EVIDENCE-PLACE', 'PRIVATE-NOTE', 'PRIVATE-JOB-TITLE'];

function rvBase(GovernmentId|Document $record): string
{
    return $record instanceof Document ? 'admin.documents.research-validation.' : 'admin.government-ids.research-validation.';
}

function rvForm(array $overrides = [], array $extra = []): array
{
    return ['verification' => array_replace([
        'agency_name' => 'Fixture Agency', 'method' => 'written_letter', 'requested_on' => '2026-02-01',
        'responded_on' => '2026-02-10', 'outcome' => 'confirmed', 'confirmed_aspects' => ['requirements'],
        'path_scope' => 'all', 'scope' => 'Requirements for first-time applications (test fixture).',
        'evidence_reference' => 'PRIVATE-EVIDENCE-REF', 'evidence_location' => 'PRIVATE-EVIDENCE-PLACE',
        'contact_role' => 'PRIVATE-JOB-TITLE', 'notes' => 'PRIVATE-NOTE',
    ], $overrides), ...$extra];
}

/** Basic Information step, where Data Sources lives. */
function rvBack(GovernmentId|Document $record): string
{
    $routes = $record instanceof Document ? 'admin.documents' : 'admin.government-ids';

    return route($routes.'.edit-wizard.show', [$record, 'basic']).'#data-sources';
}

function rvBasic(GovernmentId|Document $record, User $user): string
{
    return test()->actingAs($user)->get(strtok(rvBack($record), '#'))->assertOk()->getContent();
}

function rvDocumentRecord(): Document
{
    $document = Document::create(['name' => 'Fixture Clearance']);
    $document->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);

    return $document;
}

it('keeps residents out of agency confirmation', function () {
    $resident = User::factory()->create(['role' => 'resident']);
    $record = avRecord();
    $this->actingAs($resident)->get(route('admin.government-ids.research-validation.show', $record))->assertForbidden();
    $this->actingAs($resident)->post(route('admin.government-ids.research-validation.store', $record), rvForm())->assertForbidden();
    expect(CatalogAgencyVerification::count())->toBe(0);
});

it('saves a draft that is not ready, and only submits it once the evidence is there', function () {
    $record = avRecord();
    $recorder = avResearcher();

    $this->actingAs($recorder)->post(route('admin.government-ids.research-validation.store', $record),
        rvForm(['evidence_reference' => '']))->assertRedirect(rvBack($record))->assertSessionHasNoErrors();
    $row = CatalogAgencyVerification::sole();
    $this->post(route('admin.government-ids.research-validation.submit', [$record, $row->id]))
        ->assertSessionHasErrorsIn('agency', ['evidence_reference']);
    expect($row->fresh()->review_state)->toBe('draft')
        ->and(avService()->publicSummary($record))->toBe(['status' => null, 'confirmations' => []]);

    $this->put(route('admin.government-ids.research-validation.update', [$record, $row->id]), rvForm([], ['submit_now' => '1']))
        ->assertRedirect(rvBack($record))->assertSessionHasNoErrors();
    expect($row->fresh()->review_state)->toBe('submitted')
        ->and(avService()->evaluate($record)['status'])->not->toBe('agency_confirmed');
});

it('ignores a clearance reference sent in a hand-made request', function () {
    $record = avRecord();
    $this->actingAs(avResearcher())->post(route('admin.government-ids.research-validation.store', $record),
        rvForm(['ethics_clearance_reference' => 'ANYTHING']))->assertSessionHasNoErrors();

    expect(CatalogAgencyVerification::sole()->getAttributes())->not->toHaveKey('ethics_clearance_reference');
});

it('runs the full workflow over HTTP for both record types', function (Closure $makeRecord) {
    $record = $makeRecord();
    $base = rvBase($record);
    $recorder = avResearcher();
    $approver = avSuperAdmin();

    $this->actingAs($recorder)->post(route($base.'store', $record), rvForm())->assertRedirect(rvBack($record));
    $row = CatalogAgencyVerification::sole();
    expect($row->review_state)->toBe('draft')->and($row->scope_request_paths)->toBeNull();

    $this->post(route($base.'submit', [$record, $row->id]))->assertRedirect();
    $this->actingAs($approver)->post(route($base.'approve', [$record, $row->id]))->assertSessionHasErrorsIn('agency', ['reviewed']);
    expect($row->fresh()->review_state)->toBe('submitted');

    $this->post(route($base.'approve', [$record, $row->id]), ['reviewed' => '1'])->assertRedirect();
    expect($row->fresh()->review_state)->toBe('approved')
        ->and(avService()->evaluate($record)['status'])->toBe('agency_confirmed');
})->with([
    'government id' => [fn () => avRecord()],
    'document' => [fn () => rvDocumentRecord()],
]);

it('stores only the selected request paths when the scope is limited', function () {
    $record = avRecord();
    $this->actingAs(avResearcher())->post(route('admin.government-ids.research-validation.store', $record),
        rvForm(['path_scope' => 'selected', 'scope_request_paths' => [(string) avRenewalPath($record)]]))->assertRedirect();

    expect(CatalogAgencyVerification::sole()->scope_request_paths)->toBe([avRenewalPath($record)]);
});

it('ignores workflow fields sent in a form', function () {
    $record = avRecord();
    $this->actingAs(avResearcher())->post(route('admin.government-ids.research-validation.store', $record), [
        ...rvForm(['review_state' => 'approved', 'approved_by' => 1, 'submitted_at' => '2026-01-01']),
        'review_state' => 'approved', 'approved_by' => 1, 'content_fingerprint' => str_repeat('a', 64),
    ])->assertRedirect();

    expect(CatalogAgencyVerification::sole())->review_state->toBe('draft')->approved_by->toBeNull()->content_fingerprint->toBeNull();
});

it('refuses self-approval and approval by researchers', function () {
    $record = avRecord();
    $admin = avSuperAdmin();
    $own = avSubmitted($record, [], $admin);
    $other = avSubmitted($record);

    $this->actingAs($admin)->post(route('admin.government-ids.research-validation.approve', [$record, $own->id]), ['reviewed' => '1'])->assertForbidden();
    $this->actingAs(avResearcher())->post(route('admin.government-ids.research-validation.approve', [$record, $other->id]), ['reviewed' => '1'])->assertForbidden();
    expect($own->fresh()->review_state)->toBe('submitted')->and($other->fresh()->review_state)->toBe('submitted');
});

it('refuses super_admin actions to researchers', function (string $action, array $data) {
    $record = avRecord();
    $row = $action === 'return'
        ? avSubmitted($record)
        : avApproved($record, ['outcome' => 'confirmed_with_corrections', 'corrections_summary' => 'Fee corrected (test).']);

    $this->actingAs(avResearcher())->post(route('admin.government-ids.research-validation.'.$action, [$record, $row->id]), $data)->assertForbidden();
})->with([
    'return' => ['return', ['reason' => 'Please check the evidence reference.']],
    'revoke' => ['revoke', ['reason' => 'Evidence was found to be incomplete.']],
    'corrections applied' => ['corrections-applied', ['corrections_done' => '1']],
]);

it('lets only the recorder edit, submit or reopen', function () {
    $record = avRecord();
    $recorder = avResearcher();
    $draft = avDraft($record, [], $recorder);
    $pending = avSubmitted($record, ['outcome' => 'pending', 'confirmed_aspects' => [], 'responded_on' => null], $recorder);
    $base = 'admin.government-ids.research-validation.';

    $this->actingAs(avResearcher());
    $this->get(route($base.'edit', [$record, $draft->id]))->assertForbidden();
    $this->put(route($base.'update', [$record, $draft->id]), rvForm())->assertForbidden();
    $this->post(route($base.'submit', [$record, $draft->id]))->assertForbidden();
    $this->post(route($base.'reopen', [$record, $pending->id]))->assertForbidden();

    $this->actingAs($recorder)->post(route($base.'reopen', [$record, $pending->id]))->assertRedirect();
    expect($pending->fresh()->review_state)->toBe('draft');
});

it('cannot edit approved history through the update route', function () {
    $record = avRecord();
    $recorder = avResearcher();
    $row = avApproved($record, [], $recorder);

    $this->actingAs($recorder)->put(route('admin.government-ids.research-validation.update', [$record, $row->id]),
        rvForm(['scope' => 'Rewritten after approval for testing']))->assertForbidden();
    expect($row->fresh()->scope)->toBe(avInput()['scope']);
});

it('returns 404 for a verification reached through another record', function () {
    $record = avRecord();
    $other = avRecord();
    $row = avSubmitted($record);
    $base = 'admin.government-ids.research-validation.';
    $this->actingAs(avSuperAdmin());

    $this->post(route($base.'approve', [$other, $row->id]), ['reviewed' => '1'])->assertNotFound();
    $this->get(route($base.'create', [$other, 'from' => $row->id]))->assertNotFound();
    $this->post(route('admin.documents.research-validation.approve', [rvDocumentRecord(), $row->id]), ['reviewed' => '1'])->assertNotFound();
    expect($row->fresh()->review_state)->toBe('submitted');
});

it('shows private details only to the recorder and active super_admins', function () {
    $record = avRecord();
    $recorder = avResearcher();
    $this->actingAs($recorder)->post(route('admin.government-ids.research-validation.store', $record), rvForm())->assertRedirect();

    // A deactivated account cannot open the CMS at all.
    $this->actingAs(avSuperAdmin(['is_active' => false]))->get(strtok(rvBack($record), '#'))->assertForbidden();

    foreach ([rvBasic($record, avResearcher()), rvBasic($record, avResearcher())] as $html) {
        foreach (RV_PRIVATE as $secret) {
            expect($html)->not->toContain($secret);
        }
        expect($html)->toContain('Fixture Agency')->toContain('Requirements for first-time applications')
            ->not->toContain('data-agency-form="agency-form-'.CatalogAgencyVerification::sole()->id.'"');
    }
    foreach ([rvBasic($record, $recorder), rvBasic($record, avSuperAdmin())] as $html) {
        expect($html)->toContain('PRIVATE-EVIDENCE-REF')->toContain('PRIVATE-NOTE');
    }
});

it('prefills a new request with only agency, office and request paths', function () {
    $record = avRecord();
    $row = avApproved($record, ['scope_request_paths' => [avFirstPath($record)], 'office_label' => 'Fixture office label',
        'evidence_reference' => 'PRIVATE-EVIDENCE-REF', 'scope' => 'Original scope wording for testing']);
    $html = $this->actingAs(avResearcher())->followingRedirects()
        ->get(route('admin.government-ids.research-validation.create', [$record, 'from' => $row->id]))->assertOk()->getContent();
    $start = strpos($html, 'data-agency-form="agency-form-new"');
    $form = substr($html, $start, strpos($html, 'Saving reloads this page', $start) - $start);

    expect($start)->toBeInt()->and($form)->toContain('value="Fixture Agency"')->toContain('value="Fixture office label"')
        ->toContain('value="'.avFirstPath($record).'" checked')
        ->not->toContain('PRIVATE-EVIDENCE-REF')->not->toContain('Original scope wording')
        ->not->toContain('value="2026-02-10"')
        ->not->toContain('value="requirements" checked')->not->toContain('value="fees" checked');
});

it('writes audit entries with names only, never private values or reasons', function () {
    $record = avRecord();
    $recorder = avResearcher();
    $admin = avSuperAdmin();
    $base = 'admin.government-ids.research-validation.';

    $this->actingAs($recorder)->post(route($base.'store', $record), rvForm());
    $row = CatalogAgencyVerification::sole();
    $this->put(route($base.'update', [$record, $row->id]), rvForm(['notes' => 'PRIVATE-NOTE second']));
    $this->post(route($base.'submit', [$record, $row->id]));
    $this->actingAs($admin)->post(route($base.'return', [$record, $row->id]), ['reason' => 'PRIVATE-RETURN-REASON please check']);
    $this->actingAs($recorder)->post(route($base.'submit', [$record, $row->id]));
    $this->actingAs($admin)->post(route($base.'approve', [$record, $row->id]), ['reviewed' => '1']);
    $this->post(route($base.'revoke', [$record, $row->id]), ['reason' => 'PRIVATE-REVOKE-REASON incomplete']);

    $logs = ContentChangeLog::where('action', 'like', 'verification_%')->orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['verification_drafted', 'verification_updated', 'verification_submitted',
        'verification_returned', 'verification_submitted', 'verification_approved', 'verification_revoked'])
        ->and($logs->pluck('entity_type')->unique()->all())->toBe(['government_id'])
        ->and($logs->pluck('user_id')->unique()->sort()->values()->all())->toBe(collect([$recorder->id, $admin->id])->sort()->values()->all())
        ->and($logs[1]->changed_fields)->toBe(['agency_verification #'.$row->id, 'notes']);
    $json = $logs->toJson();
    foreach ([...RV_PRIVATE, 'PRIVATE-RETURN-REASON', 'PRIVATE-REVOKE-REASON', 'Requirements for first-time'] as $secret) {
        expect($json)->not->toContain($secret);
    }
});

it('does not change the catalog record itself', function () {
    $record = avRecord();
    DB::table('government_ids')->where('id', $record->id)->update(['updated_at' => '2026-01-01 00:00:00']);
    $before = (array) DB::table('government_ids')->find($record->id);
    $recorder = avResearcher();
    $base = 'admin.government-ids.research-validation.';

    $this->actingAs($recorder)->post(route($base.'store', $record), rvForm());
    $row = CatalogAgencyVerification::sole();
    $this->post(route($base.'submit', [$record, $row->id]));
    $this->actingAs(avSuperAdmin())->post(route($base.'approve', [$record, $row->id]), ['reviewed' => '1']);
    $after = (array) DB::table('government_ids')->find($record->id);

    expect(array_diff_key($after, ['agency_validation_status' => 1]))->toBe(array_diff_key($before, ['agency_validation_status' => 1]));
});

it('saves and submits in one step when the record is complete', function () {
    $record = avRecord();
    $this->actingAs(avResearcher())->post(route('admin.government-ids.research-validation.store', $record), rvForm([], ['submit_now' => '1']))
        ->assertRedirect(rvBack($record))
        ->assertSessionHas('success', 'Saved and submitted for review. A different super admin will check it.');

    expect(CatalogAgencyVerification::sole()->review_state)->toBe('submitted');
});

it('keeps the draft and marks what is missing when it cannot be submitted yet', function () {
    $record = avRecord();
    $response = $this->actingAs(avResearcher())->post(route('admin.government-ids.research-validation.store', $record),
        rvForm(['evidence_reference' => '', 'responded_on' => ''], ['submit_now' => '1']));
    $row = CatalogAgencyVerification::sole();

    $response->assertRedirect(rvBack($record))
        ->assertSessionHasErrorsIn('agency', ['evidence_reference', 'responded_on'])
        ->assertSessionHas('agency_form', (string) $row->id)
        ->assertSessionHas('warning');
    expect($row->review_state)->toBe('draft');
});

it('saves only a draft when "Save as draft" is chosen', function () {
    $record = avRecord();
    $this->actingAs(avResearcher())->post(route('admin.government-ids.research-validation.store', $record), rvForm([], ['submit_now' => '0']))
        ->assertSessionHas('success');

    expect(CatalogAgencyVerification::sole()->review_state)->toBe('draft');
});

it('gives each person only the actions they may take', function () {
    $record = avRecord();
    $recorder = avResearcher();
    $draft = avDraft($record, [], $recorder);
    $submitted = avSubmitted($record);

    expect(rvBasic($record, $recorder))->toContain('data-agency-form="agency-form-'.$draft->id.'"')
        ->toContain('id="agency-submit-'.$draft->id.'"')->not->toContain('waiting for your review')
        ->and(rvBasic($record, avSuperAdmin()))->toContain('Record #'.$submitted->id.' is <strong>waiting for your review</strong>')
        ->toContain('id="agency-approve-'.$submitted->id.'"')->not->toContain('data-agency-form="agency-form-'.$draft->id.'"')
        ->and(rvBasic($record, avResearcher()))->not->toContain('aria-label="Needs your attention"')
        ->not->toContain('id="agency-approve-')->not->toContain('data-agency-form="agency-form-'.$draft->id.'"');
});

it('previews exactly what residents see', function () {
    $record = avRecord();
    $show = fn () => rvBasic($record, avSuperAdmin());
    expect($show())->toContain('No agency confirmation is shown.');

    avApproved($record, ['confirmed_aspects' => ['fees']]);
    expect($show())->toContain('✓ Information Confirmed with Fixture Agency: Fees');

    $record->fees()->first()->update(['amount_min' => 2000]);
    expect($show())->toContain('No agency confirmation is shown.')->toContain('Outdated: hidden from residents');
});

it('shows the reason a draft was sent back only to people allowed to see private details', function () {
    $record = avRecord();
    $recorder = avResearcher();
    avService()->returnToDraft(avSuperAdmin(), avSubmitted($record, [], $recorder), 'PRIVATE-RETURN-REASON please check');

    expect(rvBasic($record, $recorder))->toContain('PRIVATE-RETURN-REASON')->toContain('Draft: sent back for changes')
        ->and(rvBasic($record, avResearcher()))->not->toContain('PRIVATE-RETURN-REASON')->toContain('Draft: sent back for changes');
});

it('opens the old Data Sources address in Basic Information', function () {
    $record = avRecord();
    $this->actingAs(avResearcher())->get(route('admin.government-ids.research-validation.show', $record))->assertRedirect(rvBack($record));
});
