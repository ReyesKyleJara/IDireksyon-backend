<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogAgencyVerification as Verification;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Services\CatalogAgencyValidation;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Agency Confirmation actions for one Government ID or Document.
 *
 * The form and the records are shown inside Data Sources, in the Basic Information
 * step of the edit wizard; every action returns there. Each change goes through
 * CatalogAgencyValidation, which re-authorizes on the locked row, so a hand-made
 * request gets the same answer as a button in the page.
 */
class CatalogVerificationController extends Controller
{
    /** Errors from these forms are kept apart from the Basic Information fields. */
    public const ERROR_BAG = 'agency';

    public function __construct(private readonly CatalogAgencyValidation $validation)
    {
    }

    /** The old Data Sources page address now opens Basic Information. */
    public function show(Request $request): RedirectResponse
    {
        $owner = $this->owner($request);
        Gate::authorize('viewAny', Verification::class);

        return $this->toDataSources($owner);
    }

    /** "Record a new contact for the same scope": opens the inline form, prefilled. */
    public function create(Request $request): RedirectResponse
    {
        $owner = $this->owner($request);
        Gate::authorize('create', Verification::class);
        $prefill = null;
        if ($request->filled('from')) {
            // A new request is documented independently: only who was asked and which paths are copied.
            $prefill = $this->verification($request, $owner, (int) $request->query('from'))->id;
        }

        return $this->toDataSources($owner)->with('agency_form', 'new')->with('agency_prefill_from', $prefill);
    }

    public function store(Request $request): RedirectResponse
    {
        $owner = $this->owner($request);
        try {
            $row = $this->validation->createDraft($request->user(), $owner, $this->input($request));
        } catch (ValidationException $exception) {
            return $this->failed($owner, $exception->errors(), 'new');
        } catch (DomainException $exception) {
            return $this->failed($owner, ['agency' => [$exception->getMessage()]], 'new');
        }

        return $this->afterSave($request, $owner, $row, 'Draft saved. A draft is never shown to residents.');
    }

    public function edit(Request $request): RedirectResponse
    {
        $owner = $this->owner($request);
        $row = $this->verification($request, $owner);
        Gate::authorize('update', $row);

        return $this->toDataSources($owner)->with('agency_form', (string) $row->id);
    }

    public function update(Request $request): RedirectResponse
    {
        $owner = $this->owner($request);
        $row = $this->verification($request, $owner);
        try {
            $row = $this->validation->updateDraft($request->user(), $row, $this->input($request));
        } catch (ValidationException $exception) {
            return $this->failed($owner, $exception->errors(), (string) $row->id);
        } catch (DomainException $exception) {
            return $this->failed($owner, ['agency' => [$exception->getMessage()]], (string) $row->id);
        }

        return $this->afterSave($request, $owner, $row, 'Draft saved.');
    }

    /**
     * "Save and submit for review": the draft is always kept; if something is still
     * missing (for example the evidence reference), the form reopens with each item marked.
     */
    private function afterSave(Request $request, GovernmentId|Document $owner, Verification $row, string $saved): RedirectResponse
    {
        if ($row->outcome === 'documented') {
            return $this->toDataSources($owner)->with('success', 'Verification saved. It can now appear in the app.');
        }
        if (! $request->boolean('submit_now')) {
            return $this->toDataSources($owner)->with('success', $saved);
        }
        try {
            $this->validation->submit($request->user(), $row);
        } catch (ValidationException $exception) {
            return $this->failed($owner, $exception->errors(), (string) $row->id, withInput: false)
                ->with('warning', 'Your draft was saved, but it cannot be submitted yet. See the items marked below.');
        }

        return $this->toDataSources($owner)->with('success', 'Saved and submitted for review. A different super admin will check it.');
    }

    public function submit(Request $request): RedirectResponse
    {
        $owner = $this->owner($request);
        $row = $this->verification($request, $owner);

        return $this->attempt($owner, fn () => $this->validation->submit($request->user(), $row), 'Submitted for review.', (string) $row->id);
    }

    public function reopen(Request $request): RedirectResponse
    {
        $owner = $this->owner($request);
        $row = $this->verification($request, $owner);

        return $this->attempt($owner, fn () => $this->validation->reopen($request->user(), $row),
            'Reopened as a draft so you can add the agency’s reply.', (string) $row->id);
    }

    public function returnToDraft(Request $request): RedirectResponse
    {
        $owner = $this->owner($request);
        $row = $this->verification($request, $owner);

        return $this->attempt($owner, fn () => $this->validation->returnToDraft($request->user(), $row, (string) $request->input('reason')),
            'Sent back to the recorder as a draft.');
    }

    public function approve(Request $request): RedirectResponse
    {
        $owner = $this->owner($request);
        $row = $this->verification($request, $owner);
        Gate::authorize('approve', $row);
        if (! $request->boolean('reviewed')) {
            return $this->failed($owner, ['reviewed' => ['Confirm that you reviewed the verification and supporting evidence.']]);
        }

        return $this->attempt($owner, fn () => $this->validation->approve($request->user(), $row),
            'Approved. Only the listed aspects and request paths count as confirmed.');
    }

    public function correctionsApplied(Request $request): RedirectResponse
    {
        $owner = $this->owner($request);
        $row = $this->verification($request, $owner);
        Gate::authorize('markCorrectionsApplied', $row);
        if (! $request->boolean('corrections_done')) {
            return $this->failed($owner, ['corrections_done' => ['Confirm that the CMS content was corrected first.']]);
        }

        return $this->attempt($owner, fn () => $this->validation->markCorrectionsApplied($request->user(), $row), 'Corrections recorded as applied.');
    }

    public function revoke(Request $request): RedirectResponse
    {
        $owner = $this->owner($request);
        $row = $this->verification($request, $owner);

        return $this->attempt($owner, fn () => $this->validation->revoke($request->user(), $row, (string) $request->input('reason')),
            'Confirmation revoked. It stays in the history.');
    }

    /** Only editable content is read from the form; workflow fields are never accepted. */
    private function input(Request $request): array
    {
        $form = (array) $request->input('verification', []);
        $input = array_intersect_key($form, array_flip(Verification::CONTENT));
        $input['confirmed_aspects'] = array_values((array) ($input['confirmed_aspects'] ?? []));
        $input['not_covered_aspects'] = array_values((array) ($input['not_covered_aspects'] ?? []));
        $input['scope_request_paths'] = ($form['path_scope'] ?? 'all') === 'selected'
            ? array_values(array_map('intval', (array) ($input['scope_request_paths'] ?? []))) : null;
        foreach ($input as $key => $value) {
            if ($value === '') {
                $input[$key] = null;
            }
        }

        return $input;
    }

    /** Runs a service action; refused changes come back as a message next to the record. */
    private function attempt(GovernmentId|Document $owner, callable $action, string $message, ?string $form = null): RedirectResponse
    {
        try {
            $action();
        } catch (ValidationException $exception) {
            return $this->failed($owner, $exception->errors(), $form, withInput: false);
        } catch (DomainException $exception) {
            return $this->failed($owner, ['agency' => [$exception->getMessage()]], null, withInput: false);
        }

        return $this->toDataSources($owner)->with('success', $message);
    }

    private function failed(GovernmentId|Document $owner, array $errors, ?string $form = null, bool $withInput = true): RedirectResponse
    {
        $response = $this->toDataSources($owner)->withErrors($errors, self::ERROR_BAG)->with('agency_form', $form);

        return $withInput ? $response->withInput(request()->only('verification')) : $response;
    }

    /** Basic Information step of the edit wizard, scrolled to Data Sources with Agency Confirmation open. */
    private function toDataSources(GovernmentId|Document $owner): RedirectResponse
    {
        $routes = $owner instanceof Document ? 'admin.documents' : 'admin.government-ids';

        return redirect()->to(route($routes.'.edit-wizard.show', [$owner, 'basic']).'#data-sources')
            ->with('data_sources_tab', 'agency');
    }

    /** The record in the URL; an unknown id is a 404. */
    private function owner(Request $request): GovernmentId|Document
    {
        $route = $request->route();
        $parameter = $route->hasParameter('document') ? 'document' : 'governmentId';
        $value = $route->parameter($parameter);
        if ($value instanceof GovernmentId || $value instanceof Document) {
            return $value;
        }
        $record = ($parameter === 'document' ? Document::class : GovernmentId::class)::query()->findOrFail((int) $value);
        $route->setParameter($parameter, $record);

        return $record;
    }

    /** A verification is only reachable through the record it belongs to. */
    private function verification(Request $request, GovernmentId|Document $owner, ?int $id = null): Verification
    {
        return $owner->agencyVerifications()->whereKey($id ?? (int) $request->route('verification'))->firstOrFail();
    }
}
