<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Barangay;
use App\Models\Category;
use App\Models\Level;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ReferenceController extends Controller
{
    private const RESOURCES = [
        'levels' => [Level::class, 'Levels'], 'categories' => [Category::class, 'Categories'],
        'agencies' => [Agency::class, 'Agencies'], 'barangays' => [Barangay::class, 'Barangays'], 'offices' => [Office::class, 'Offices & Locations'],
    ];

    private function model(string $type): string
    {
        abort_unless(isset(self::RESOURCES[$type]), 404);

        return self::RESOURCES[$type][0];
    }

    private function data(string $type): array
    {
        $this->model($type);

        return ['type' => $type, 'title' => self::RESOURCES[$type][1], 'tabs' => self::RESOURCES,
            'agencies' => Agency::orderBy('name')->get(), 'barangays' => Barangay::orderBy('name')->get(),
            'categories' => Category::with('parentCategory')->orderBy('name')->get()];
    }

    public function index(Request $request, string $type)
    {
        $model = $this->model($type);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:active,inactive,needs_research']]);
        $query = $model::query()->when($filters['q'] ?? null, fn ($q, $v) => $q->where('name', 'like', '%'.$v.'%'))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->orderBy('name');
        if ($type === 'offices') {
            $query->with(['agency', 'barangay']);
        }
        if ($type === 'categories') {
            $query->with('parentCategory');
        }

        return view('admin.references.index', $this->data($type) + ['records' => $query->paginate(24)->withQueryString()]);
    }

    public function create(string $type)
    {
        $model = $this->model($type);

        return view('admin.references.form', $this->data($type) + ['record' => new $model]);
    }

    public function edit(string $type, string $id)
    {
        $record = $this->model($type)::findOrFail($id);

        return view('admin.references.form', $this->data($type) + ['record' => $record]);
    }

    private function validated(Request $request, string $type, $record = null): array
    {
        $table = (new ($this->model($type)))->getTable();
        $rules = ['name' => ['required', 'string', 'max:255'], 'status' => ['required', Rule::in($type === 'offices' ? ['active', 'inactive', 'needs_research'] : ['active', 'inactive'])]];
        if ($type !== 'offices') {
            $rules['name'][] = Rule::unique($table)->ignore($record?->id);
            $rules['description'] = ['nullable', 'string', 'max:10000'];
        }
        if (in_array($type, ['levels', 'categories'])) {
            $rules['slug'] = ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique($table)->ignore($record?->id)];
        }
        if ($type === 'categories') {
            $rules['parent_category_id'] = ['nullable', 'integer', 'exists:categories,id'];
        }
        if ($type === 'agencies') {
            $rules['abbreviation'] = ['nullable', 'string', 'max:40'];
        }
        if ($type === 'barangays') {
            $rules['municipality'] = ['required', 'string', 'max:255'];
            $rules['province'] = ['required', 'string', 'max:255'];
        }
        if ($type === 'offices') {
            $rules += [
                'agency_id' => ['nullable', 'integer', 'exists:agencies,id'], 'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
                'address' => ['nullable', 'string', 'max:5000'], 'municipality' => ['nullable', 'string', 'max:255'], 'province' => ['nullable', 'string', 'max:255'],
                'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
                'phone' => ['nullable', 'string', 'max:100'], 'email' => ['nullable', 'email', 'max:255'], 'notes' => ['nullable', 'string', 'max:10000'],
                'source_url' => ['nullable', 'required_with:source_checked_at', 'url:http,https', 'max:2048'], 'source_checked_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                'hours_present' => ['sometimes', 'boolean'], 'hours' => ['nullable', 'array', 'max:28'], 'hours.*' => ['array:day_of_week,opens_at,closes_at,notes'],
                'hours.*.day_of_week' => ['required', 'integer', 'between:0,6'], 'hours.*.opens_at' => ['required', 'date_format:H:i'],
                'hours.*.closes_at' => ['required', 'date_format:H:i'], 'hours.*.notes' => ['nullable', 'string', 'max:255'],
            ];
        }
        $validator = Validator::make($request->all(), $rules, [], ['parent_category_id' => 'parent category', 'agency_id' => 'agency', 'barangay_id' => 'barangay']);
        $validator->after(function ($validator) use ($request, $type, $record) {
            if ($validator->errors()->any()) {
                return;
            }
            if ($type === 'categories' && $record && $request->filled('parent_category_id')) {
                $parent = Category::find($request->input('parent_category_id'));
                $seen = [];
                while ($parent) {
                    if ($parent->id === $record->id || isset($seen[$parent->id])) {
                        $validator->errors()->add('parent_category_id', 'A category cannot be its own parent or a descendant’s child.');
                        break;
                    }
                    $seen[$parent->id] = true;
                    $parent = $parent->parentCategory;
                }
            }
            if ($type === 'offices') {
                $intervals = [];
                foreach ($request->input('hours', []) ?? [] as $row) {
                    if ($row['closes_at'] <= $row['opens_at']) {
                        $validator->errors()->add('hours', 'Closing time must be after opening time. Split overnight schedules into separate days.');
                    }
                    foreach ($intervals[$row['day_of_week']] ?? [] as $prior) {
                        if ($row['opens_at'] < $prior['closes_at'] && $row['closes_at'] > $prior['opens_at']) {
                            $validator->errors()->add('hours', 'Office hours cannot overlap on the same day.');
                        }
                    }
                    $intervals[$row['day_of_week']][] = $row;
                }
                if ($request->filled('barangay_id')) {
                    $barangay = Barangay::find($request->input('barangay_id'));
                    foreach (['municipality', 'province'] as $field) {
                        if ($request->filled($field) && strcasecmp(trim($request->input($field)), $barangay->$field) !== 0) {
                            $validator->errors()->add($field, 'This location does not match the selected barangay.');
                        }
                    }
                }
            }
        });

        return $validator->validate();
    }

    private function save(array $data, $record): void
    {
        DB::transaction(function () use ($data, $record) {
            $hours = $data['hours'] ?? [];
            $sync = isset($data['hours_present']);
            unset($data['hours'],$data['hours_present']);
            if ($record instanceof Office && ! empty($data['barangay_id'])) {
                $barangay = Barangay::findOrFail($data['barangay_id']);
                $data['municipality'] = $barangay->municipality;
                $data['province'] = $barangay->province;
            }
            $record->fill($data)->save();
            if ($record instanceof Office && $sync) {
                $keep = [];
                foreach ($hours as $row) {
                    $hour = $record->hours()->updateOrCreate(['day_of_week' => $row['day_of_week'], 'opens_at' => $row['opens_at'].':00'], ['closes_at' => $row['closes_at'].':00', 'notes' => $row['notes'] ?? null]);
                    $keep[] = $hour->id;
                }
                $record->hours()->whereNotIn('id', $keep)->delete();
            }
        });
    }

    public function store(Request $request, string $type)
    {
        $model = $this->model($type);
        $record = new $model;
        $this->save($this->validated($request, $type), $record);

        return redirect()->route('admin.references.edit', [$type, $record->id])->with('success', 'Entry created.');
    }

    public function update(Request $request, string $type, string $id)
    {
        $record = $this->model($type)::findOrFail($id);
        $this->save($this->validated($request, $type, $record), $record);

        return back()->with('success', 'Changes saved.');
    }

    public function destroy(string $type, string $id)
    {
        $record = $this->model($type)::findOrFail($id);
        $relations = match ($type) {
            'levels' => ['governmentIds', 'documents'], 'categories' => ['children', 'governmentIds', 'documents'], 'agencies' => ['offices', 'governmentIds', 'documents'], 'barangays' => ['offices'], 'offices' => ['governmentIds', 'documents', 'hours']
        };
        foreach ($relations as $relation) {
            if ($record->$relation()->exists()) {
                return back()->with('error', 'This entry has linked records. Deactivate it or reassign its links first.');
            }
        }
        $record->delete();

        return redirect()->route('admin.references.index', $type)->with('success', 'Unused entry deleted.');
    }
}
