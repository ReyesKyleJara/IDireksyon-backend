<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CatalogRequest;
use App\Models\Agency;
use App\Models\Category;
use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Level;
use App\Models\Office;
use App\Models\Requirement;
use App\Services\CatalogDefinition;
use App\Support\CatalogOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

abstract class CatalogController extends Controller
{
    protected string $modelClass;

    protected string $resource;

    protected string $viewFolder;

    protected string $label;

    protected string $plural;

    protected function pageData(): array
    {
        return [
            'resource' => $this->resource,
            'label' => $this->label,
            'plural' => $this->plural,
            'isDocument' => $this->resource === 'documents',
            'agencies' => Agency::orderBy('name')->get(),
            'categories' => Category::with('parentCategory')->orderBy('name')->get(),
            'levels' => Level::orderBy('id')->get(),
            'recordTypes' => CatalogOptions::RECORD_TYPES,
            'availabilityStates' => CatalogOptions::AVAILABILITY,
            'offices' => Office::with('barangay')->orderBy('name')->get(),
            'issuanceLevels' => CatalogOptions::LEVELS,
            'researchStages' => CatalogOptions::RESEARCH_STAGES,
        ];
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'view' => ['nullable', 'in:list,grid'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'level_id' => ['nullable', 'integer', 'exists:levels,id'],
            'availability_status' => ['nullable', Rule::in(array_keys(CatalogOptions::AVAILABILITY))],
            'issuance_level' => ['nullable', Rule::in(array_keys(CatalogOptions::LEVELS))],
            'research_status' => ['nullable', Rule::in(array_keys(CatalogOptions::RESEARCH_STAGES))],
        ]);
        $search = trim($filters['q'] ?? '');
        $records = $this->modelClass::with(['issuingAgency', 'category', 'level'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('agency', 'like', '%'.$search.'%')
                ->orWhereHas('issuingAgency', fn ($agency) => $agency->where('name', 'like', '%'.$search.'%')->orWhere('abbreviation', 'like', '%'.$search.'%'))))
            ->when($filters['category_id'] ?? null, fn ($query, $value) => $query->where('category_id', $value))
            ->when($filters['level_id'] ?? null, fn ($query, $value) => $query->where('level_id', $value))
            ->when($filters['availability_status'] ?? null, fn ($query, $value) => $query->where('availability_status', $value))
            ->when($filters['issuance_level'] ?? null, fn ($query, $value) => $query->where('issuance_level', $value))
            ->when($filters['research_status'] ?? null, fn ($query, $value) => $query->where('research_status', $value))
            ->orderBy('name')->paginate(12)->withQueryString();

        return view($this->viewFolder.'.index', $this->pageData() + [
            'records' => $records, 'search' => $search, 'viewMode' => $filters['view'] ?? 'list',
        ]);
    }

    public function create()
    {
        return view($this->viewFolder.'.create', $this->pageData() + ['record' => new $this->modelClass]);
    }

    public function store(CatalogRequest $request)
    {
        $record = DB::transaction(function () use ($request) {
            $data = $request->safe()->except('office_ids');
            if (array_key_exists('level_id', $data)) {
                $data['issuance_level'] = ! empty($data['level_id']) ? Level::findOrFail($data['level_id'])->slug : null;
            }
            if (! empty($data['agency_id'])) {
                $data['agency'] = Agency::findOrFail($data['agency_id'])->name;
            }
            $record = $this->modelClass::create($data + ['last_updated' => now()]);
            $officeChanges = $record->offices()->sync(array_filter($request->validated('office_ids') ?? []));
            if (array_filter($officeChanges)) {
                ContentChangeLog::record($record, 'offices_updated', ['offices']);
            }

            $this->validateReview($record);

            return $record;
        });

        return redirect()->route('admin.'.$this->resource.'.show', $record)
            ->with('success', $this->label.' created. You can now add its requirements.');
    }

    public function show(string $id)
    {
        $record = $this->modelClass::with(['issuingAgency', 'category', 'level', 'offices.barangay', 'requirements.referencedGovernmentId', 'requirements.referencedDocument', 'requirements.group', 'requirementGroups', 'fees'])->findOrFail($id);

        return view($this->viewFolder.'.show', $this->pageData() + ['record' => $record]);
    }

    public function edit(string $id)
    {
        $record = $this->modelClass::with(['issuingAgency', 'category', 'level', 'offices.barangay', 'requirements.referencedGovernmentId', 'requirements.referencedDocument', 'requirements.group', 'requirementGroups', 'fees'])->findOrFail($id);

        return view($this->viewFolder.'.edit', $this->pageData() + [
            'record' => $record,
            'availableGovernmentIds' => GovernmentId::query()
                ->when($record instanceof GovernmentId, fn ($query) => $query->whereKeyNot($record->id))->orderBy('name')->get(),
            'availableDocuments' => Document::query()
                ->when($record instanceof Document, fn ($query) => $query->whereKeyNot($record->id))->orderBy('name')->get(),
        ]);
    }

    public function update(CatalogRequest $request, string $id)
    {
        $record = $this->modelClass::findOrFail($id);
        DB::transaction(function () use ($request, $record) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
            $data = $request->safe()->except('office_ids');
            if (array_key_exists('level_id', $data)) {
                $data['issuance_level'] = ! empty($data['level_id']) ? Level::findOrFail($data['level_id'])->slug : null;
            }
            if (! empty($data['agency_id'])) {
                $data['agency'] = Agency::findOrFail($data['agency_id'])->name;
            }
            $data['requirements_reviewed'] = $request->boolean('requirements_reviewed');
            $record->update(array_merge($data, [
                'application_steps' => array_values($request->validated('application_steps') ?? []),
                'last_updated' => now(),
            ]));
            if ($request->has('office_ids')) {
                $officeChanges = $record->offices()->sync(array_filter($request->validated('office_ids') ?? []));
                if (array_filter($officeChanges)) {
                    ContentChangeLog::record($record, 'offices_updated', ['offices']);
                }
            }
            $this->validateReview($record->fresh());
        });

        return redirect()->route('admin.'.$this->resource.'.show', $record)->with('success', 'Changes saved.');
    }

    public function destroy(string $id)
    {
        $record = $this->modelClass::findOrFail($id);
        $referenceColumn = $record instanceof Document ? 'referenced_document_id' : 'referenced_government_id_id';
        $isReferenced = Requirement::where($referenceColumn, $record->id)
            ->where(fn ($query) => $query->whereHas('governmentIds')->orWhereHas('documents'))->exists();

        if ($isReferenced) {
            return back()->with('error', 'This entry is used in another application’s requirements. Remove those references before deleting it.');
        }

        $record->delete();

        return redirect()->route('admin.'.$this->resource.'.index')->with('success', $this->label.' deleted.');
    }

    public function addRequirement(Request $request, string $id)
    {
        $record = $this->modelClass::findOrFail($id);
        $validated = $request->validateWithBag('requirement', [
            'type' => ['required', Rule::in(['government_id', 'document', 'custom'])],
            'referenced_government_id_id' => ['exclude_unless:type,government_id', 'required', 'integer', 'exists:government_ids,id',
                Rule::notIn($record instanceof GovernmentId ? [$record->id] : [])],
            'referenced_document_id' => ['exclude_unless:type,document', 'required', 'integer', 'exists:documents,id',
                Rule::notIn($record instanceof Document ? [$record->id] : [])],
            'name' => ['exclude_unless:type,custom', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $validated += $this->requirementStructure($request, $record, $validated['type']);

        if ($validated['type'] === 'government_id') {
            $validated['name'] = GovernmentId::findOrFail($validated['referenced_government_id_id'])->name;
        } elseif ($validated['type'] === 'document') {
            $validated['name'] = Document::findOrFail($validated['referenced_document_id'])->name;
        }

        DB::transaction(function () use ($record, $validated, $request) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
            $this->requirementStructure($request, $record, $validated['type']);
            $this->rejectDuplicate($record, $validated);
            // Each application owns its requirement notes; edits must not affect other applications.
            $requirement = Requirement::create($validated);
            $record->requirements()->attach($requirement->id);
            ContentChangeLog::record($record, 'requirement_added', ['requirements']);
            $record->update(['requirements_reviewed' => false, 'last_updated' => now()]);
        });

        return redirect()->route('admin.'.$this->resource.'.edit', $record)->with('success', 'Requirement added.');
    }

    public function removeRequirement(string $id, string $requirement)
    {
        $record = $this->modelClass::findOrFail($id);
        $attached = $record->requirements()->findOrFail($requirement);
        DB::transaction(function () use ($record, $attached) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
            $record->requirements()->detach($attached->id);
            ContentChangeLog::record($record, 'requirement_removed', ['requirements']);
            $record->update(['requirements_reviewed' => false, 'last_updated' => now()]);
        });

        return redirect()->route('admin.'.$this->resource.'.edit', $record)->with('success', 'Requirement removed from this application.');
    }

    private function validateReview(GovernmentId|Document $record): void
    {
        $definition = app(CatalogDefinition::class);
        $errors = [];
        if ($record->is_published && ($issues = $definition->directoryIssues($record))) {
            $errors['is_published'] = $issues;
        }
        if ($record->requirements_reviewed && ($issues = $definition->sequencingIssues($record))) {
            $errors['requirements_reviewed'] = $issues;
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function requirementStructure(Request $request, GovernmentId|Document $record, string $type): array
    {
        $parent = $record instanceof GovernmentId ? 'government_id_id' : 'document_id';
        $data = $request->validateWithBag('requirement', [
            'requirement_group_id' => ['nullable', 'integer', Rule::exists('requirement_groups', 'id')->where($parent, $record->id)],
            'is_dependency' => ['sometimes', 'boolean'],
        ]);
        $data['requirement_group_id'] = $data['requirement_group_id'] ?? null;
        $data['is_dependency'] = $request->boolean('is_dependency');
        if ($type === 'custom' && $data['is_dependency']) {
            throw ValidationException::withMessages(['is_dependency' => 'Choose a linked ID or document for an obtain-first dependency.'])->errorBag('requirement');
        }

        return $data;
    }

    private function rejectDuplicate(GovernmentId|Document $record, array $data, ?int $except = null): void
    {
        $query = $record->requirements()->where('type', $data['type'])
            ->where('requirement_group_id', $data['requirement_group_id']);
        if ($except) {
            $query->where('requirements.id', '!=', $except);
        }
        if ($data['type'] === 'document') {
            $query->where('referenced_document_id', $data['referenced_document_id']);
        } elseif ($data['type'] === 'government_id') {
            $query->where('referenced_government_id_id', $data['referenced_government_id_id']);
        } else {
            $query->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))]);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['requirement_group_id' => 'This requirement is already in that group. Edit the existing option instead.'])->errorBag('requirement');
        }
    }

    public function updateRequirement(Request $request, string $id, string $requirement)
    {
        $record = $this->modelClass::findOrFail($id);
        $attached = $record->requirements()->findOrFail($requirement);
        $data = $this->requirementStructure($request, $record, $attached->type);
        $data += $request->validateWithBag('requirement', [
            'description' => ['nullable', 'string', 'max:5000'],
            'name' => [$attached->type === 'custom' ? 'required' : 'exclude', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($record, $attached, $data, $request) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
            $attached = $record->requirements()->lockForUpdate()->findOrFail($attached->id);
            $this->requirementStructure($request, $record, $attached->type);
            $this->rejectDuplicate($record, array_merge($attached->getAttributes(), $data), $attached->id);
            // Older data may share a requirement. Editing one application must not change another.
            if ($attached->governmentIds()->count() + $attached->documents()->count() > 1) {
                $copy = $attached->replicate();
                $copy->fill($data)->save();
                $record->requirements()->detach($attached->id);
                $record->requirements()->attach($copy->id);
            } else {
                $attached->update($data);
            }
            ContentChangeLog::record($record, 'requirement_updated', ['requirements']);
            $record->update(['requirements_reviewed' => false, 'last_updated' => now()]);
        });

        return redirect()->route('admin.'.$this->resource.'.edit', $record)->with('success', 'Requirement updated. Review the complete rules before sequencing use.');
    }
}
