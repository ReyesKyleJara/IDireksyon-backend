<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OfficeRequest;
use App\Models\Agency;
use App\Models\ContentChangeLog;
use App\Models\Office;
use App\Services\OfficeLocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfficeController extends Controller
{
    public function __construct(private OfficeLocator $locator)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
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
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('admin.offices.index', [
            'offices' => $offices,
            'agencies' => Agency::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        return $this->form(new Office(), $request->boolean('embedded'));
    }

    public function edit(Office $office)
    {
        $office->load('schedules.intervals');
        return $this->form($office);
    }

    private function form(Office $office, bool $embedded = false)
    {
        return view('admin.offices.form', [
            'office' => $office,
            'embedded' => $embedded,
            'agencies' => Agency::orderBy('name')->get(),
        ]);
    }

    public function store(OfficeRequest $request)
    {
        $office = $this->saveOffice(new Office(), $request->validated());
        if ($request->boolean('embedded')) {
            $office->load('agency');
            return view('admin.offices.created-embedded', [
                'createdOffice' => [
                    'id' => (string) $office->id,
                    'name' => $office->name,
                    'agency' => $office->agency?->name ?? '',
                    'status' => $office->status,
                    'location' => collect([$office->municipality, $office->province])->filter()->implode(', '),
                ],
            ]);
        }
        return redirect()->route('admin.offices.edit', $office)->with('success', 'Office saved.');
    }

    public function update(OfficeRequest $request, Office $office)
    {
        $this->saveOffice($office, $request->validated());
        return redirect()->route('admin.offices.edit', $office)->with('success', 'Office updated.');
    }

    private function saveOffice(Office $office, array $data): Office
    {
        $saved = DB::transaction(function () use ($office, $data) {
            $schedules = $data['schedules'] ?? null;
            unset($data['schedules']);
            $office->fill($this->applyLocationInput($office, $data));
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

        // Outside the transaction so Google can neither hold up nor undo the save.
        if ($this->locator->shouldLocate($saved)) {
            $this->locator->locate($saved);
        }

        return $saved;
    }

    /** Typed coordinates win; blank boxes leave the location to automatic lookup. */
    private function applyLocationInput(Office $office, array $data): array
    {
        if (! array_key_exists('latitude', $data) || ! array_key_exists('longitude', $data)) {
            unset($data['latitude'], $data['longitude']);

            return $data;
        }

        if (filled($data['latitude'])) {
            $office->forceFill([
                'location_source' => 'manual',
                'google_place_id' => null,
                'location_status' => null,
                'location_checked_at' => null,
            ]);

            return $data;
        }

        if ($office->location_source === 'manual') {
            $office->location_source = null;

            return $data;
        }

        unset($data['latitude'], $data['longitude']);

        return $data;
    }
}
