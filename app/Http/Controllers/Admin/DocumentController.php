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
use App\Models\Office;
use App\Models\ContentChangeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    use \App\Http\Controllers\Admin\Concerns\PreparesCatalogEntry;

    public function index(Request $request)
    {
        $sort = $request->input('sort', 'name_asc');

        $documents = Document::query()->with('agency')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->trim();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('issued_by', 'like', "%{$search}%")
                        ->orWhereHas('agency', fn ($agency) => $agency->where('name', 'like', "%{$search}%")->orWhere('acronym', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('level'), function ($query) use ($request) {
                $query->where('level', $request->level);
            })
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->where('category', $request->category);
            })
            ->when($sort === 'name_asc', fn ($query) => $query->orderBy('name'))
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('name'))
            ->when($sort === 'level', fn ($query) => $query->orderBy('level')->orderBy('name'))
            ->when($sort === 'category', fn ($query) => $query->orderBy('category')->orderBy('name'))
            ->when($sort === 'recent', fn ($query) => $query->latest())
            ->get();

        return view('admin.documents.index', compact('documents', 'sort'));
    }

    public function show(Document $document)
    {
        $document->load([
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
            ->where('entity_type', 'document')
            ->where('entity_id', $document->id)
            ->whereIn('action', ['created', 'updated'])
            ->latest('created_at')->orderByDesc('id')->first();

        $checklists = $document->requirementSets
            ->map(fn ($set) => GovernmentIdChecklistController::present($set))->values()->all();
        $requirementOptions = [];

        return view(
            'admin.documents.show',
            compact('document', 'lastEdit', 'checklists', 'requirementOptions')
        );
    }

    public function create()
    {
        $offices = Office::with('agency')->orderBy('name')->get();

        $agencies = Agency::query()
            ->orderBy('name')
            ->get();

        $checklists = [];
        $requirementOptions = $this->requirementOptions();

        return view(
            'admin.documents.create',
            compact('agencies', 'offices', 'checklists', 'requirementOptions')
        );
    }

    public function store(Request $request)
    {
        $checklistRows = app(GovernmentIdChecklists::class)->readForCreation($request);
        $guideRows = app(GovernmentIdApplicationGuide::class)->read($request);

        $validated = $this->prepareEligibility(
            $this->validateCatalogEntry($request)
        );

        $officeRows = $this->prepareOfficeLinks($request);

        $syncFees = $request->has('fees') || $request->boolean('fees_present');
        $feeRows = $this->prepareFeeRows(
            $validated['fees'] ?? []
        );

        unset($validated['fees']);

        unset(
            $validated['fee_type'],
            $validated['fee_min'],
            $validated['fee_max'],
            $validated['fee_currency'],
            $validated['fee_notes']
        );

        if ($request->has('validity_type')) $validated = $this->prepareValidity($validated);

        if ($request->has('processing_time_type')) $validated = $this->prepareProcessingTime($validated);

        $document = DB::transaction(function () use (
            $validated,
            $feeRows,
            $syncFees,
            $officeRows,
            $guideRows,
            $checklistRows
        ) {
            $document = Document::create(
                $validated
            );

            if ($syncFees) $this->syncFees($document, $feeRows);

            if ($officeRows !== null) {
                $this->syncOfficeLinks($document, $officeRows);
            }

            $draftSets = app(GovernmentIdChecklists::class)->createAll($document, $checklistRows);
            app(GovernmentIdApplicationGuide::class)->sync($document, $guideRows, $draftSets);


            return $document;
        });

        // Persist all sections together before opening the document details.
        return redirect()
            ->route(
                'admin.documents.show',
                $document
            )
            ->with(
                'success',
                'Document added successfully.'
            );
    }

    public function edit(Document $document)
    {
        $document->load([
            'agency',
            'requirementSets.groups.items.governmentId',
            'requirementSets.groups.items.document',
            'requirementSets.groups.ways',
            'requirementSets.applicationSteps.blocks',
            'offices.agency',
            'offices.schedules.intervals',
            'fees' => fn ($query) => $query->orderBy('sort_order'),
        ]);

        $offices = Office::with('agency')->orderBy('name')->get();

        $agencies = Agency::query()
            ->orderBy('name')
            ->get();

        $checklists = $document->requirementSets
            ->map(fn ($set) => GovernmentIdChecklistController::present($set))->values()->all();
        $requirementOptions = $this->requirementOptions();

        return view(
            'admin.documents.edit',
            compact(
                'document',
                'agencies',
                'offices',
                'checklists',
                'requirementOptions'
            )
        );
    }

    private function requirementOptions(): array
    {
        return GovernmentId::orderBy('name')->get(['id', 'name'])
            ->map(fn ($id) => ['id' => $id->id, 'name' => $id->name, 'type' => 'government_id'])
            ->concat(Document::orderBy('name')->get(['id', 'name'])
                ->map(fn ($document) => ['id' => $document->id, 'name' => $document->name, 'type' => 'document']))
            ->sortBy('name')->values()->all();
    }

    public function update(
        Request $request,
        Document $document
    ) {
        $guideRows = app(GovernmentIdApplicationGuide::class)->read($request);

        $validated = $this->prepareEligibility(
            $this->validateCatalogEntry($request)
        );

        $officeRows = $this->prepareOfficeLinks($request);

        $syncFees = $request->has('fees') || $request->boolean('fees_present');
        $feeRows = $this->prepareFeeRows(
            $validated['fees'] ?? []
        );

        unset($validated['fees']);

        unset(
            $validated['fee_type'],
            $validated['fee_min'],
            $validated['fee_max'],
            $validated['fee_currency'],
            $validated['fee_notes']
        );

        if ($request->has('validity_type')) $validated = $this->prepareValidity($validated);

        if ($request->has('processing_time_type')) $validated = $this->prepareProcessingTime($validated);

        DB::transaction(function () use (
            $document,
            $validated,
            $feeRows,
            $syncFees,
            $officeRows,
            $guideRows
        ) {
            $document->update($validated);

            if ($syncFees) $this->syncFees($document, $feeRows);

            if ($officeRows !== null) {
                $this->syncOfficeLinks($document, $officeRows);
            }

            app(GovernmentIdApplicationGuide::class)->sync($document, $guideRows);

        });

        return redirect()
            ->route(
                'admin.documents.show',
                $document
            )
            ->with(
                'success',
                'Document updated successfully.'
            );
    }

    public function destroy(
        Document $document
    ) {
        $hasGuide = fn () => $document->requirementSets()->whereHas('applicationSteps')->exists();
        if ($hasGuide()) {
            return redirect()->route('admin.documents.show', $document)
                ->with('error', 'This document has Application Guide steps. Remove those steps before deleting it.');
        }
        $isReferenced = fn () => GovernmentIdRequirementItem::where('document_id', $document->id)->exists()
            || \App\Models\DocumentRequirementItem::where('document_id', $document->id)->exists();
        if ($isReferenced()) {
            return redirect()->route('admin.documents.index')->with('error', 'This document is used in requirement items. Remove those references before deleting it.');
        }
        try {
            DB::transaction(fn () => $document->delete());
        } catch (QueryException $exception) {
            if ($hasGuide()) {
                return redirect()->route('admin.documents.show', $document)
                    ->with('error', 'This document has Application Guide steps. Remove those steps before deleting it.');
            }
            if (! $isReferenced()) {
                throw $exception;
            }
            return redirect()->route('admin.documents.index')->with('error', 'This document is used in requirement items. Remove those references before deleting it.');
        }

        return redirect()
            ->route(
                'admin.documents.index'
            )
            ->with(
                'success',
                'Document deleted successfully.'
            );
    }

}
