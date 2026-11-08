@php
    $weekdays = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];
    $storedSchedules = $office->exists ? $office->schedules->keyBy('day_of_week') : collect();
    $previousSchedules = old('schedules');
    $days = [];
    foreach ($weekdays as $day => $label) {
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
        $days[$day] = [
            'status' => $dayStatus,
            'intervals' => $rows,
            // A day with a validation error starts open so the problem is visible.
            'expanded' => $errors->get('schedules.'.$day.'.*') !== [],
        ];
    }
@endphp
<section class="admin-panel p-6" @invalid.capture="reveal($event)" x-data="{
    days: @js($days),
    copied: false,
    complete(day) {
        return day.intervals.length > 0 && day.intervals.every((interval) => interval.opens_at && interval.closes_at);
    },
    clock(time) {
        const [hour, minute] = time.split(':').map(Number);
        return (hour % 12 || 12) + ':' + String(minute).padStart(2, '0') + (hour < 12 ? ' AM' : ' PM');
    },
    hours(day) {
        if (day.status !== 'open') return '';
        if (! this.complete(day)) return 'Hours needed';
        return day.intervals.map((interval) => this.clock(interval.opens_at) + ' – ' + this.clock(interval.closes_at)).join(', ');
    },
    expandOnly(day, expanded) {
        Object.values(this.days).forEach((other) => other.expanded = false);
        day.expanded = expanded;
    },
    statusChanged(day) {
        day.intervals = day.status === 'open' ? [{ opens_at: '', closes_at: '' }] : [];
        this.expandOnly(day, day.status === 'open');
    },
    applyMonday() {
        [2, 3, 4, 5].forEach((number) => {
            this.days[number].status = this.days[1].status;
            this.days[number].intervals = this.days[1].intervals.map((interval) => ({ ...interval }));
            this.days[number].expanded = false;
        });
        this.days[1].expanded = false;
        this.copied = true;
        setTimeout(() => this.copied = false, 2500);
    },
    reveal(event) {
        // Browsers refuse to save over a required field they cannot show.
        const row = event.target.closest('[data-schedule-day]');
        if (! row) return;
        this.days[row.dataset.scheduleDay].expanded = true;
        row.querySelector('[data-schedule-hours]').style.removeProperty('display');
    },
}">
    <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <h2 class="text-lg font-semibold text-slate-900">Office Hours</h2>
            <p class="mt-1 text-sm text-slate-500">Philippine local time. Leave unresearched days as “Not yet verified.” Add a separate interval after a lunch break.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm text-slate-500" role="status" x-show="copied" x-cloak>Copied to Tuesday–Friday</span>
            <button type="button" @click="applyMonday()" :disabled="days[1].status !== 'open' || ! complete(days[1])"
                class="text-sm font-medium text-[#012877] underline disabled:cursor-not-allowed disabled:text-slate-400 disabled:no-underline">Apply Monday hours to Mon–Fri</button>
        </div>
    </div>
    <div class="divide-y divide-slate-100">
        @foreach($weekdays as $day => $label)
            <fieldset class="py-2.5" data-schedule-day="{{ $day }}" x-data="{ day: days[{{ $day }}] }">
                <legend class="sr-only">{{ $label }}</legend>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <span class="w-24 shrink-0 text-sm font-semibold text-slate-800" aria-hidden="true">{{ $label }}</span>
                    <label for="schedule-status-{{ $day }}" class="sr-only">{{ $label }} status</label>
                    <select id="schedule-status-{{ $day }}" name="schedules[{{ $day }}][status]" x-model="day.status"
                        @change="statusChanged(day)" class="admin-input w-40 shrink-0 py-1.5">
                        <option value="unknown">Not yet verified</option>
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                    </select>
                    <span class="min-w-0 flex-1 text-sm" :class="complete(day) ? 'text-slate-700' : 'text-amber-700'" x-text="hours(day)"></span>
                    <button type="button" x-show="day.status === 'open'" x-cloak @click="expandOnly(day, ! day.expanded)" :aria-expanded="day.expanded"
                        aria-label="Edit {{ $label }} hours" class="text-sm font-medium text-[#012877] underline" x-text="day.expanded ? 'Done' : 'Edit'"></button>
                </div>
                <div data-schedule-hours x-show="day.status === 'open' && day.expanded" x-cloak class="mt-2 space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <template x-for="(interval, index) in day.intervals" :key="index">
                        <div class="flex flex-wrap items-end gap-3">
                            <label class="text-xs font-medium text-slate-700">Opens
                                <input type="time" step="60" x-model="interval.opens_at" :name="'schedules[{{ $day }}][intervals][' + index + '][opens_at]'"
                                    :required="day.status === 'open'" :disabled="day.status !== 'open'" class="admin-input mt-1 w-36 py-1.5">
                            </label>
                            <label class="text-xs font-medium text-slate-700">Closes
                                <input type="time" step="60" x-model="interval.closes_at" :name="'schedules[{{ $day }}][intervals][' + index + '][closes_at]'"
                                    :required="day.status === 'open'" :disabled="day.status !== 'open'" class="admin-input mt-1 w-36 py-1.5">
                            </label>
                            <button type="button" @click="day.intervals.splice(index, 1)" class="py-2 text-sm text-slate-600 underline" :aria-label="'Remove {{ $label }} interval ' + (index + 1)">Remove</button>
                        </div>
                    </template>
                    <button type="button" @click="day.intervals.push({ opens_at: '', closes_at: '' })" :disabled="day.intervals.length >= 4"
                        class="text-sm font-medium text-[#012877] underline disabled:cursor-not-allowed disabled:text-slate-400 disabled:no-underline">Add interval</button>
                </div>
                @foreach($errors->get('schedules.'.$day.'.*') as $messages)
                    @foreach((array) $messages as $message)<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@endforeach
                @endforeach
            </fieldset>
        @endforeach
    </div>
</section>
