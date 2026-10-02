@php
    $officeOptions = $offices->map(fn ($office) => [
        'id' => (string) $office->id,
        'name' => $office->name,
        'agency' => $office->agency?->name ?? 'Agency not yet assigned',
        'status' => $office->status,
        'location' => collect([$office->municipality, $office->province])->filter()->implode(', ') ?: 'Location not yet researched',
    ])->values();
    $savedLinks = $selectedOffices->map(fn ($office) => [
        'office_id' => (string) $office->id,
        'new_application_status' => $office->pivot->new_application_status,
        'renewal_status' => $office->pivot->renewal_status,
        'replacement_status' => $office->pivot->replacement_status,
        'service_notes' => $office->pivot->service_notes ?? '',
    ])->all();
    // The marker distinguishes removing every row from a request without this editor.
    $submitted = session()->hasOldInput('office_links_present');
    $initialLinks = $submitted ? old('office_links', []) : $savedLinks;
    $initialLinks = is_array($initialLinks) ? array_values($initialLinks) : [];
    $initialLinks = collect($initialLinks)->filter(fn ($row) => is_array($row))->map(function ($row) {
        $safe = [];
        foreach (['office_id', 'new_application_status', 'renewal_status', 'replacement_status', 'service_notes'] as $field) {
            $safe[$field] = is_scalar($row[$field] ?? null) ? (string) $row[$field] : '';
        }
        return $safe;
    })->values()->all();
@endphp
<section class="admin-panel p-6" x-data="{
    options: @js($officeOptions),
    links: @js($initialLinks),
    selected: '',
    officeFor(id) { return this.options.find(office => office.id === String(id)); },
    addOffice() {
        if (!this.selected || this.links.some(link => link.office_id === this.selected)) return;
        this.links.push({ office_id: this.selected, new_application_status: 'unknown', renewal_status: 'unknown', replacement_status: 'unknown', service_notes: '' });
        this.selected = '';
    }
}">
    <div class="mb-5">
        <h2 class="text-lg font-semibold text-slate-900">Linked Offices</h2>
        <p class="mt-1 text-sm text-slate-500">Choose the branches that handle this ID. Record service availability separately for each branch.</p>
        <p class="mt-2 text-xs text-slate-500">Draft and inactive offices remain hidden from residents. Linking a branch does not publish it.</p>
    </div>
    <input type="hidden" name="office_links_present" value="1">
    @foreach($errors->get('office_links*') as $messages)
        @foreach((array) $messages as $message)<p class="mb-2 text-sm text-red-700">{{ $message }}</p>@endforeach
    @endforeach
    @if($offices->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 p-5">
            <p class="text-sm text-slate-600">No offices yet. Create a branch draft first, then return here to link it.</p>
            <a href="{{ route('admin.offices.create') }}" target="_blank" rel="noopener noreferrer" class="admin-secondary mt-3">Add Office (new tab)</a>
            <p class="mt-2 text-xs text-slate-500">Save your ID changes before refreshing this page to load newly added offices.</p>
        </div>
    @else
        <label for="office-link-picker" class="mb-2 block text-sm font-medium">Select a branch</label>
        <div class="flex flex-col gap-3 sm:flex-row">
            <select id="office-link-picker" x-model="selected" class="admin-input">
                <option value="">Choose an office</option>
                <template x-for="office in options" :key="office.id">
                    <option :value="office.id" :disabled="links.some(link => link.office_id === office.id)" x-text="office.name + ' — ' + office.location + ' (' + office.status + ')'"></option>
                </template>
            </select>
            <button type="button" @click="addOffice()" :disabled="!selected || links.length >= 100" class="admin-secondary shrink-0">Add Office</button>
        </div>
        <p x-show="links.length === 0" class="mt-4 text-sm text-slate-500">No branches linked yet. You can save this ID and add offices later.</p>
    @endif
    <div class="mt-5 space-y-5">
        <template x-for="(link, index) in links" :key="index">
            <div class="rounded-xl border border-slate-200 p-5">
                <input type="hidden" :name="'office_links[' + index + '][office_id]'" :value="link.office_id">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h3 class="break-words font-semibold text-slate-900" x-text="officeFor(link.office_id)?.name ?? 'Office no longer available'"></h3>
                        <p class="mt-1 text-xs text-slate-500" x-text="officeFor(link.office_id)?.agency"></p>
                        <p class="mt-1 text-xs text-slate-500" x-text="officeFor(link.office_id)?.location"></p>
                        <span class="mt-2 inline-block rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600" x-text="officeFor(link.office_id)?.status"></span>
                    </div>
                    <button type="button" @click="links.splice(index, 1)" class="text-sm font-medium text-red-700 hover:underline" :aria-label="'Unlink ' + (officeFor(link.office_id)?.name ?? 'office')">Remove</button>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach(['new_application_status' => 'New Application', 'renewal_status' => 'Renewal', 'replacement_status' => 'Replacement'] as $field => $label)
                        <label class="text-sm font-medium">{{ $label }}
                            <select :name="'office_links[' + index + '][{{ $field }}]'" x-model="link.{{ $field }}" class="admin-input mt-2">
                                <option value="unknown">Not yet researched</option>
                                <option value="available">Available</option>
                                <option value="unavailable">Not available</option>
                            </select>
                        </label>
                    @endforeach
                </div>
                <div class="mt-4 space-y-4">
                    <label class="block text-sm font-medium">Service Notes
                        <textarea :name="'office_links[' + index + '][service_notes]'" x-model="link.service_notes" rows="2" maxlength="10000" class="admin-input mt-2" placeholder="For example: Appointment required for new applications."></textarea>
                    </label>
                    <p class="text-xs leading-5 text-slate-500">Removing a branch here only unlinks it from this ID.</p>
                </div>
            </div>
        </template>
    </div>
</section>
