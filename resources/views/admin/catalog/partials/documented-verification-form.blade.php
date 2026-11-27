@php
    $value = fn ($key, $saved = null) => $useOld ? old('verification.'.$key, $saved) : $saved;
@endphp
<div data-agency-form="{{ $fid }}" class="space-y-3 rounded-lg border border-slate-200 p-4"
    x-data="{ office: @js($value('office_label', $verification->office_label)), method: @js($value('method', $verification->method)), date: @js($value('responded_on', $verification->responded_on?->toDateString())) }"
    x-effect="if (open) draftVerification = { office, method, date }">
    <input type="hidden" form="{{ $fid }}" name="verification[outcome]" value="documented">
    @if($useOld && $agencyErrors->any())
        <div class="rounded-lg bg-red-50 p-3 text-red-800" role="alert">
            @foreach($agencyErrors->all() as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endif
    <label class="block font-medium">Office or branch name
        <input class="admin-input mt-1" form="{{ $fid }}" name="verification[office_label]" maxlength="255" required x-model="office">
    </label>
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="block font-medium">Verification method
            <select class="admin-input mt-1" form="{{ $fid }}" name="verification[method]" required x-model="method">
                <option value="">Select a method</option>
                @foreach(['office_visit' => 'On-site inquiry', 'phone_interview' => 'Phone inquiry', 'email' => 'Email inquiry', 'official_reply_document' => 'Official written response'] as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block font-medium">Verification date
            <input type="date" class="admin-input mt-1" form="{{ $fid }}" name="verification[responded_on]" max="{{ now('Asia/Manila')->toDateString() }}" required x-model="date">
        </label>
    </div>
    <label class="block font-medium">Verification summary <span class="font-normal text-slate-500">(optional)</span>
        <textarea class="admin-input mt-1" form="{{ $fid }}" name="verification[scope]" rows="2" maxlength="1000">{{ $value('scope', $verification->scope) }}</textarea>
    </label>
    <details class="rounded-lg border border-slate-200 p-3">
        <summary class="cursor-pointer font-medium">Private evidence</summary>
        <div class="mt-3 space-y-3">
            <label class="block font-medium">Evidence storage note <span class="font-normal text-slate-500">(optional)</span>
                <input class="admin-input mt-1" form="{{ $fid }}" name="verification[evidence_location]" maxlength="255" value="{{ $value('evidence_location', $verification->evidence_location) }}">
            </label>
            <label class="block font-medium">Supporting evidence notes <span class="font-normal text-slate-500">(optional)</span>
                <textarea class="admin-input mt-1" form="{{ $fid }}" name="verification[notes]" rows="2" maxlength="5000">{{ $value('notes', $verification->notes) }}</textarea>
            </label>
        </div>
    </details>
    <div class="flex flex-wrap gap-2">
        <button type="submit" form="{{ $fid }}" class="admin-primary">Save verification</button>
        <button type="button" class="admin-secondary" @click="open = false; draftVerification = null">Close</button>
    </div>
</div>
