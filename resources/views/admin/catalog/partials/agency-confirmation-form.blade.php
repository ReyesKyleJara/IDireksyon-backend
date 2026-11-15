{{--
    Inline Agency Confirmation form (a draft). Every field belongs to the standalone
    <form id="$fid"> pushed after the main wizard form, so it never mixes with the
    Basic Information save. Field names are prefixed: verification[...].
    Expects: $fid, $verification, $useOld, $agencyErrors, $agencies, $offices, $pathLabels.
--}}
@use('App\Models\CatalogAgencyVerification', 'Verification')
@php
    $value = fn ($field) => $useOld ? old('verification.'.$field, $verification->{$field}) : $verification->{$field};
    $dateValue = fn ($field) => $useOld ? old('verification.'.$field, $verification->{$field}?->format('Y-m-d')) : $verification->{$field}?->format('Y-m-d');
    $list = fn ($field) => (array) ($useOld ? old('verification.'.$field, $verification->{$field} ?? []) : ($verification->{$field} ?? []));
    $outcome = $value('outcome') ?? 'pending';
    $confirmed = $list('confirmed_aspects');
    $notCovered = $list('not_covered_aspects');
    $pathScope = $useOld ? old('verification.path_scope', $verification->scope_request_paths === null ? 'all' : 'selected')
        : ($verification->scope_request_paths === null ? 'all' : 'selected');
    $selectedPaths = array_map('intval', $list('scope_request_paths'));
    $agencyId = (string) $value('agency_id');
    $today = now('Asia/Manila')->toDateString();
    $outcomes = [
        'pending' => 'I asked and I am waiting for their reply',
        'confirmed' => 'They confirmed the information',
        'partially_confirmed' => 'They confirmed only part of it',
        'confirmed_with_corrections' => 'They confirmed it but gave corrections',
        'not_confirmed' => 'They could not confirm it',
        'no_response' => 'They did not reply',
        'withdrawn' => 'Cancel this record (it is kept, not deleted)',
    ];
    $err = fn ($field) => $useOld ? ($agencyErrors->first($field) ?: $agencyErrors->first($field.'.*')) : null;
    $name = fn ($field) => 'verification['.$field.']';
@endphp
<div class="space-y-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm" data-agency-form="{{ $fid }}"
    x-data="{ outcome: @js($outcome), pathScope: @js($pathScope), agency: @js($agencyId),
        get confirming() { return ['confirmed','partially_confirmed','confirmed_with_corrections'].includes(this.outcome) },
        get answered() { return this.confirming || this.outcome === 'not_confirmed' } }">

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="block font-medium">Agency
            <select class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('agency_id') }}" x-model="agency">
                <option value="">Not in this list (type the name)</option>
                @foreach($agencies as $agency)
                    <option value="{{ $agency->id }}" @selected($agencyId === (string) $agency->id)>{{ $agency->name }}@if($agency->acronym) ({{ $agency->acronym }})@endif</option>
                @endforeach
            </select>
        </label>
        <label class="block font-medium" x-show="agency === ''">Agency name
            <input class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('agency_name') }}" maxlength="255" value="{{ $value('agency_name') }}" :disabled="agency !== ''">
            @if($err('agency_name'))<span class="mt-1 block text-red-700">{{ $err('agency_name') }}</span>@endif
        </label>
        <label class="block font-medium">Office <span class="font-normal text-slate-500">(optional)</span>
            <select class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('office_id') }}">
                <option value="">None selected</option>
                @foreach($offices as $office)<option value="{{ $office->id }}" @selected((string) $value('office_id') === (string) $office->id)>{{ $office->name }}</option>@endforeach
            </select>
        </label>
        <label class="block font-medium">Office or branch name <span class="font-normal text-slate-500">(optional)</span>
            <input class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('office_label') }}" maxlength="255" value="{{ $value('office_label') }}" placeholder="e.g. Consular office, Malolos">
        </label>
        <label class="block font-medium">How you contacted them
            <select class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('method') }}">
                @foreach(Verification::METHOD_LABELS as $code => $label)<option value="{{ $code }}" @selected($value('method') === $code)>{{ $label }}</option>@endforeach
            </select>
            @if($err('method'))<span class="mt-1 block text-red-700">{{ $err('method') }}</span>@endif
        </label>
        <label class="block font-medium">Date you contacted them
            <input type="date" class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('requested_on') }}" value="{{ $dateValue('requested_on') }}" max="{{ $today }}">
            @if($err('requested_on'))<span class="mt-1 block text-red-700">{{ $err('requested_on') }}</span>@endif
        </label>
    </div>

    <label class="block font-medium">What did the agency say?
        <select class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('outcome') }}" x-model="outcome">
            @foreach($outcomes as $code => $label)<option value="{{ $code }}" @selected($outcome === $code)>{{ $label }}</option>@endforeach
        </select>
        @if($err('outcome'))<span class="mt-1 block text-red-700">{{ $err('outcome') }}</span>@endif
    </label>
    <label class="block font-medium" x-show="answered">Date they replied
        <input type="date" class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('responded_on') }}" value="{{ $dateValue('responded_on') }}" max="{{ $today }}" :disabled="!answered">
        @if($err('responded_on'))<span class="mt-1 block text-red-700">{{ $err('responded_on') }}</span>@endif
    </label>

    <fieldset x-show="confirming">
        <legend class="font-medium">What did they confirm? <span class="font-normal text-slate-600">Tick only what the agency actually confirmed.</span></legend>
        <div class="mt-1 grid gap-1 sm:grid-cols-3">
            @foreach(Verification::ASPECT_LABELS as $code => $label)
                <label class="flex items-center gap-2"><input type="checkbox" form="{{ $fid }}" name="{{ $name('confirmed_aspects') }}[]" value="{{ $code }}" @checked(in_array($code, $confirmed, true)) :disabled="!confirming"> {{ $label }}</label>
            @endforeach
        </div>
        @if($err('confirmed_aspects'))<span class="mt-1 block text-red-700">{{ $err('confirmed_aspects') }}</span>@endif
    </fieldset>
    <fieldset x-show="outcome !== 'withdrawn'">
        <legend class="font-medium">
            <span x-show="confirming">What did they <em>not</em> confirm? <span class="font-normal text-slate-600">(needed for “only part of it”)</span></span>
            <span x-show="outcome === 'not_confirmed'">What could they not confirm?</span>
            <span x-show="outcome === 'pending' || outcome === 'no_response'">What did you ask about?</span>
        </legend>
        <div class="mt-1 grid gap-1 sm:grid-cols-3">
            @foreach(Verification::ASPECT_LABELS as $code => $label)
                <label class="flex items-center gap-2"><input type="checkbox" form="{{ $fid }}" name="{{ $name('not_covered_aspects') }}[]" value="{{ $code }}" @checked(in_array($code, $notCovered, true)) :disabled="outcome === 'withdrawn'"> {{ $label }}</label>
            @endforeach
        </div>
        @if($err('not_covered_aspects'))<span class="mt-1 block text-red-700">{{ $err('not_covered_aspects') }}</span>@endif
    </fieldset>
    <fieldset x-show="outcome !== 'withdrawn'">
        <legend class="font-medium">Which ways to apply does it cover?</legend>
        <label class="mt-1 flex items-center gap-2"><input type="radio" form="{{ $fid }}" name="{{ $name('path_scope') }}" value="all" x-model="pathScope" @checked($pathScope === 'all')> All ways to apply</label>
        <label class="flex items-center gap-2"><input type="radio" form="{{ $fid }}" name="{{ $name('path_scope') }}" value="selected" x-model="pathScope" @checked($pathScope === 'selected')> Only these:</label>
        <div class="mt-1 grid gap-1 pl-5" x-show="pathScope === 'selected'">
            @forelse($pathLabels as $id => $label)
                <label class="flex items-center gap-2"><input type="checkbox" form="{{ $fid }}" name="{{ $name('scope_request_paths') }}[]" value="{{ $id }}" @checked(in_array((int) $id, $selectedPaths, true))> {{ $label }}</label>
            @empty
                <p class="text-slate-600">This guide has no ways to apply yet.</p>
            @endforelse
        </div>
        @if($err('scope_request_paths'))<span class="mt-1 block text-red-700">{{ $err('scope_request_paths') }}</span>@endif
    </fieldset>

    <label class="block font-medium">Describe it in one or two sentences
        <span class="block font-normal text-slate-600">Residents may see this. Example: “Requirements and fees for first-time adult applications.” Describe only what they confirmed.</span>
        <textarea class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('scope') }}" rows="2" maxlength="1000">{{ $value('scope') }}</textarea>
        @if($err('scope'))<span class="mt-1 block text-red-700">{{ $err('scope') }}</span>@endif
    </label>
    <label class="block font-medium" x-show="outcome === 'partially_confirmed'">What was not confirmed, and why?
        <textarea class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('not_covered_note') }}" rows="2" maxlength="2000" :disabled="outcome !== 'partially_confirmed'">{{ $value('not_covered_note') }}</textarea>
        @if($err('not_covered_note'))<span class="mt-1 block text-red-700">{{ $err('not_covered_note') }}</span>@endif
    </label>
    <label class="block font-medium" x-show="outcome === 'confirmed_with_corrections'">What corrections did they give?
        <textarea class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('corrections_summary') }}" rows="2" maxlength="2000" :disabled="outcome !== 'confirmed_with_corrections'">{{ $value('corrections_summary') }}</textarea>
        @if($err('corrections_summary'))<span class="mt-1 block text-red-700">{{ $err('corrections_summary') }}</span>@endif
    </label>

    <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-3">
        <p class="font-semibold">🔒 Private: only you and the super admins see this</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block font-medium">Evidence reference <span class="font-normal text-slate-500">(needed when they replied)</span>
                <input class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('evidence_reference') }}" maxlength="255" value="{{ $value('evidence_reference') }}" placeholder="e.g. Appendix C, Letter 01">
                @if($err('evidence_reference'))<span class="mt-1 block text-red-700">{{ $err('evidence_reference') }}</span>@endif
            </label>
            <label class="block font-medium">Where the evidence is kept <span class="font-normal text-slate-500">(optional)</span>
                <input class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('evidence_location') }}" maxlength="255" value="{{ $value('evidence_location') }}" placeholder="e.g. Team research archive">
            </label>
            <label class="block font-medium">Their job title <span class="font-normal text-slate-500">(optional, never a name)</span>
                <input class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('contact_role') }}" maxlength="255" value="{{ $value('contact_role') }}" placeholder="e.g. Records officer">
                @if($err('contact_role'))<span class="mt-1 block text-red-700">{{ $err('contact_role') }}</span>@endif
            </label>
        </div>
        <label class="block font-medium">Notes <span class="font-normal text-slate-500">(optional; needed if “How” is “Other”)</span>
            <textarea class="admin-input mt-1" form="{{ $fid }}" name="{{ $name('notes') }}" rows="2" maxlength="5000">{{ $value('notes') }}</textarea>
            @if($err('notes'))<span class="mt-1 block text-red-700">{{ $err('notes') }}</span>@endif
        </label>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="submit" form="{{ $fid }}" name="submit_now" value="0" class="admin-primary">Save draft</button>
        <button type="submit" form="{{ $fid }}" name="submit_now" value="1" class="admin-secondary">Save and submit for review</button>
        <button type="button" class="text-sm text-slate-600 hover:underline" @click="$dispatch('agency-form-close')">Cancel</button>
    </div>
    <p class="text-xs text-slate-500">Saving reloads this page. Save other Basic Information changes first.</p>
</div>
