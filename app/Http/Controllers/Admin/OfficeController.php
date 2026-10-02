<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OfficeRequest;
use App\Models\Agency;
use App\Models\ContentChangeLog;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfficeController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'status' => ['nullable', 'in:draft,inactive'],
        ]);
        $offices = Office::with('agency')
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters) {
                $term = '%'.trim($filters['q']).'%';
                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', $term)
                        ->orWhere('municipality', 'like', $term)
                        ->orWhere('province', 'like', $term)
                        ->orWhereHas('agency', fn ($agency) => $agency->where('name', 'like', $term));
                });
            })
            ->when($filters['agency_id'] ?? null, fn ($query, $agency) => $query->where('agency_id', $agency))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('admin.offices.index', [
            'offices' => $offices,
            'agencies' => Agency::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return $this->form(new Office());
    }

    public function edit(Office $office)
    {
        $office->load('schedules.intervals');
        return $this->form($office);
    }

    private function form(Office $office)
    {
        return view('admin.offices.form', [
            'office' => $office,
            'agencies' => Agency::orderBy('name')->get(),
        ]);
    }

    public function store(OfficeRequest $request)
    {
        $office = $this->saveOffice(new Office(), $request->validated());
        return redirect()->route('admin.offices.edit', $office)->with('success', 'Office draft saved.');
    }

    public function update(OfficeRequest $request, Office $office)
    {
        $this->saveOffice($office, $request->validated());
        return redirect()->route('admin.offices.edit', $office)->with('success', 'Office updated.');
    }

    private function saveOffice(Office $office, array $data): Office
    {
        return DB::transaction(function () use ($office, $data) {
            $schedules = $data['schedules'] ?? null;
            unset($data['schedules']);
            $office->fill($data);
            $office->save();

            // Omitted days retain existing research; the editor submits all seven days.
            if ($schedules !== null) {
                $changed = false;
                foreach ($schedules as $day => $values) {
                    $schedule = $office->schedules()->firstOrNew(['day_of_week' => (int) $day]);
                    $intervals = collect($values['intervals'] ?? [])->sortBy('opens_at')->values()->all();
                    $existing = $schedule->exists
                        ? $schedule->intervals()->get()->map(fn ($interval) => [
                            'opens_at' => substr($interval->opens_at, 0, 5),
                            'closes_at' => substr($interval->closes_at, 0, 5),
                        ])->all()
                        : [];
                    if (! $schedule->exists || $schedule->status !== $values['status'] || $existing !== $intervals) {
                        $schedule->status = $values['status'];
                        $schedule->save();
                        $schedule->intervals()->delete();
                        $schedule->intervals()->createMany(array_map(fn ($interval) => [
                            'opens_at' => $interval['opens_at'].':00',
                            'closes_at' => $interval['closes_at'].':00',
                        ], $intervals));
                        $changed = true;
                    }
                }
                if ($changed) {
                    ContentChangeLog::record($office, 'updated', ['schedules']);
                }
            }

            return $office;
        });
    }
}
