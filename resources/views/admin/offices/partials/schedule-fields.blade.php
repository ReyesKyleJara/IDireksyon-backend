@php
    $weekdays = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];
    $storedSchedules = $office->exists ? $office->schedules->keyBy('day_of_week') : collect();
    $previousSchedules = old('schedules');
@endphp
<section class="admin-panel p-6">
    <h2 class="text-lg font-semibold text-slate-900">Office Hours</h2>
    <p class="mb-6 mt-1 text-sm text-slate-500">Philippine local time. Leave unresearched days as “Not yet verified.” Add a separate interval after a lunch break.</p>
    <div class="divide-y divide-slate-100">
        @foreach($weekdays as $day => $label)
            @php
                $stored = $storedSchedules->get($day);
                $initial = is_array($previousSchedules)
                    ? ($previousSchedules[$day] ?? [])
                    : [
                        'status' => $stored?->status ?? 'unknown',
                        'intervals' => $stored ? $stored->intervals->map(fn ($interval) => [
                            'opens_at' => substr($interval->opens_at, 0, 5),
                            'closes_at' => substr($interval->closes_at, 0, 5),
                        ])->all() : [],
                    ];
                $initial = is_array($initial) ? $initial : [];
                $dayStatus = in_array($initial['status'] ?? null, ['unknown', 'open', 'closed'], true) ? $initial['status'] : 'unknown';
                $rows = is_array($initial['intervals'] ?? null) ? $initial['intervals'] : [];
                $rows = collect($rows)->filter(fn ($row) => is_array($row))->take(4)->map(fn ($row) => [
                    'opens_at' => is_string($row['opens_at'] ?? null) ? $row['opens_at'] : '',
                    'closes_at' => is_string($row['closes_at'] ?? null) ? $row['closes_at'] : '',
                ])->values()->all();
            @endphp
            <fieldset class="py-5" x-data="{ status: @js($dayStatus), intervals: @js($rows) }">
                <legend class="font-semibold text-slate-800">{{ $label }}</legend>
                <label for="schedule-status-{{ $day }}" class="sr-only">{{ $label }} status</label>
                <select id="schedule-status-{{ $day }}" name="schedules[{{ $day }}][status]" x-model="status"
                    @change="intervals = status === 'open' ? [{ opens_at: '', closes_at: '' }] : []" class="admin-input mt-2">
                    <option value="unknown">Not yet verified</option>
                    <option value="open">Open</option>
                    <option value="closed">Closed</option>
                </select>
                <div x-show="status === 'open'" x-cloak class="mt-4 space-y-3">
                    <template x-for="(interval, index) in intervals" :key="index">
                        <div class="grid items-end gap-3 sm:grid-cols-3">
                            <label class="text-sm font-medium">Opens
                                <input type="time" step="60" x-model="interval.opens_at" :name="'schedules[{{ $day }}][intervals][' + index + '][opens_at]'"
                                    :required="status === 'open'" :disabled="status !== 'open'" class="admin-input mt-1">
                            </label>
                            <label class="text-sm font-medium">Closes
                                <input type="time" step="60" x-model="interval.closes_at" :name="'schedules[{{ $day }}][intervals][' + index + '][closes_at]'"
                                    :required="status === 'open'" :disabled="status !== 'open'" class="admin-input mt-1">
                            </label>
                            <button type="button" @click="intervals.splice(index, 1)" class="admin-secondary" :aria-label="'Remove {{ $label }} interval ' + (index + 1)">Remove interval</button>
                        </div>
                    </template>
                    <button type="button" @click="intervals.push({ opens_at: '', closes_at: '' })" :disabled="intervals.length >= 4" class="admin-secondary">Add interval</button>
                </div>
                @foreach($errors->get('schedules.'.$day.'.*') as $messages)
                    @foreach((array) $messages as $message)<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@endforeach
                @endforeach
            </fieldset>
        @endforeach
    </div>
</section>
