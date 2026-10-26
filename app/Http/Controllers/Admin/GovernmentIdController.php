<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Services\GovernmentIdApplicationGuide;
use App\Services\GovernmentIdChecklists;
use App\Models\GovernmentId;
use App\Models\Document;
use App\Models\GovernmentIdRequirementItem;
use Illuminate\Database\QueryException;
use App\Models\GovernmentIdOffice;
use App\Models\Office;
use App\Models\ContentChangeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GovernmentIdController extends Controller
{
    use \App\Http\Controllers\Admin\Concerns\PreparesCatalogEntry;

    public function index(Request $request)
    {
        $sort = $request->input('sort', 'name_asc');

        $governmentIds = GovernmentId::query()
            ->with('agency')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->trim();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('agency', function ($agencyQuery) use ($search) {
                            $agencyQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('acronym', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('level'), function ($query) use ($request) {
                $query->where('level', $request->level);
            })
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->where('category', $request->category);
            })
            ->when(
                $sort === 'name_asc',
                fn ($query) => $query->orderBy('name')
            )
            ->when(
                $sort === 'name_desc',
                fn ($query) => $query->orderByDesc('name')
            )
            ->when(
                $sort === 'level',
                fn ($query) => $query
                    ->orderBy('level')
                    ->orderBy('name')
            )
            ->when(
                $sort === 'category',
                fn ($query) => $query
                    ->orderBy('category')
                    ->orderBy('name')
            )
            ->when(
                $sort === 'recent',
                fn ($query) => $query->latest()
            )
            ->get();

        return view(
            'admin.government_ids.index',
            compact('governmentIds', 'sort')
        );
    }

    public function show(GovernmentId $governmentId)
    {
        $governmentId->load([
            'agency',
            'requirementSets.groups.items.governmentId',
            'requirementSets.groups.items.document',
            'requirementSets.groups.ways',
            'requirementSets.applicationSteps.blocks',
            'offices.agency',
            'offices.schedules.intervals',
            'fees' => fn ($query) => $query->orderBy('sort_order'),
        ]);

        $lastEdit = ContentChangeLog::with('user')
            ->where('entity_type', 'government_id')
            ->where('entity_id', $governmentId->id)
            ->whereIn('action', ['created', 'updated'])
            ->latest('created_at')->orderByDesc('id')->first();

        $checklists = $governmentId->requirementSets
            ->map(fn ($set) => GovernmentIdChecklistController::present($set))->values()->all();
        $requirementOptions = [];

        return view(
            'admin.government_ids.show',
            compact('governmentId', 'lastEdit', 'checklists', 'requirementOptions')
        );
    }

    public function create()
    {
        return app(GovernmentIdCreationWizardController::class)->create(request());
    }

    public function store(Request $request)
    {
        $checklistRows = app(GovernmentIdChecklists::class)->readForCreation($request);
        $guideRows = app(GovernmentIdApplicationGuide::class)->read($request);

        $validated = $this->prepareEligibility(
            $this->validateCatalogEntry($request)
        );

        $officeRows = $this->prepareOfficeLinks($request);

        $feeRows = $this->prepareFeeRows(
            $validated['fees'] ?? []
        );

        unset($validated['fees']);

        /*
         * The new government_id_fees table
         * is now the source of truth for fees.
         *
         * These old structured single-fee columns
         * are retained in the database temporarily,
         * but should no longer be populated by the form.
         */
        unset(
            $validated['fee_type'],
            $validated['fee_min'],
            $validated['fee_max'],
            $validated['fee_currency'],
            $validated['fee_notes']
        );

        $validated = $this->prepareValidity($validated);

        $validated = $this->prepareProcessingTime(
            $validated
        );

        $governmentId = DB::transaction(function () use (
            $validated,
            $feeRows,
            $officeRows,
            $guideRows,
            $checklistRows
        ) {
            $governmentId = GovernmentId::create(
                $validated
            );

            $this->syncFees(
                $governmentId,
                $feeRows
            );

            if ($officeRows !== null) {
                $this->syncOfficeLinks($governmentId, $officeRows);
            }

            $draftSets = app(GovernmentIdChecklists::class)->createAll($governmentId, $checklistRows);
            app(GovernmentIdApplicationGuide::class)->sync($governmentId, $guideRows, $draftSets);


            return $governmentId;
        });

        // The complete creation form saves all sections before opening ID details.
        return redirect()
            ->route(
                'admin.government-ids.show',
                $governmentId
            )
            ->with(
                'success',
                'ID or credential added successfully.'
            );
    }

    public function edit(GovernmentId $governmentId)
    {
        return app(GovernmentIdCreationWizardController::class)->edit(request(), $governmentId);
    }

    public function update(
        Request $request,
        GovernmentId $governmentId
    ) {
        $guideRows = app(GovernmentIdApplicationGuide::class)->read($request);

        $validated = $this->prepareEligibility(
            $this->validateCatalogEntry($request)
        );

        /*
         * Get the dynamic fee rows before removing
         * them from the main GovernmentId data.
         */
        $officeRows = $this->prepareOfficeLinks($request);
        $syncFees = $request->has('fees') || $request->boolean('fees_present')
            || !$governmentId->fees()->whereHas('options')->exists();

        $feeRows = $this->prepareFeeRows(
            $validated['fees'] ?? []
        );

        /*
         * These values do not belong directly
         * in GovernmentId::update().
         */
        unset($validated['fees']);

        /*
         * Stop using the old one-fee structured
         * fields as the source of truth.
         */
        unset(
            $validated['fee_type'],
            $validated['fee_min'],
            $validated['fee_max'],
            $validated['fee_currency'],
            $validated['fee_notes']
        );

        $validated = $this->prepareValidity(
            $validated
        );

        $validated = $this->prepareProcessingTime(
            $validated
        );

        DB::transaction(function () use (
            $governmentId,
            $validated,
            $feeRows,
            $syncFees,
            $officeRows,
            $guideRows
        ) {
            /*
             * Update the main Government ID record.
             */
            $governmentId->update(
                $validated
            );

            /*
             * Save all multiple fee items.
             */
            if ($syncFees) $this->syncFees($governmentId, $feeRows);

            if ($officeRows !== null) {
                $this->syncOfficeLinks($governmentId, $officeRows);
            }

            app(GovernmentIdApplicationGuide::class)->sync($governmentId, $guideRows);

        });

        /*
         * IMPORTANT:
         * After Save Changes, take the researcher
         * directly to this ID's View page.
         */
        return redirect()
            ->route(
                'admin.government-ids.show',
                $governmentId
            )
            ->with(
                'success',
                'ID or credential updated successfully.'
            );
    }

    public function destroy(
        GovernmentId $governmentId
    ) {
        $hasGuide = fn () => $governmentId->requirementSets()->whereHas('applicationSteps')->exists();
        if ($hasGuide()) {
            return redirect()->route('admin.government-ids.show', $governmentId)
                ->with('error', 'This Government ID has Application Guide steps. Remove those steps before deleting it.');
        }
        $isReferenced = fn () => GovernmentIdRequirementItem::where('government_id_id', $governmentId->id)->exists()
            || \App\Models\DocumentRequirementItem::where('government_id_id', $governmentId->id)->exists();
        if ($isReferenced()) {
            return redirect()->route('admin.government-ids.show', $governmentId)->with('error', 'This Government ID is used in requirement items. Remove those references before deleting it.');
        }
        try {
            DB::transaction(fn () => $governmentId->delete());
        } catch (QueryException $exception) {
            if ($hasGuide()) {
                return redirect()->route('admin.government-ids.show', $governmentId)
                    ->with('error', 'This Government ID has Application Guide steps. Remove those steps before deleting it.');
            }
            if (! $isReferenced()) {
                throw $exception;
            }
            return redirect()->route('admin.government-ids.show', $governmentId)->with('error', 'This Government ID is used in requirement items. Remove those references before deleting it.');
        }

        return redirect()
            ->route(
                'admin.government-ids.index'
            )
            ->with(
                'success',
                'ID or credential deleted successfully.'
            );
    }

}
