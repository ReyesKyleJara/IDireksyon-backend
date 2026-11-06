@include('admin.catalog.partials.request-method-styles')
@if(!($inline ?? false))
    @php
        $legacyDraft = \App\Services\InlineRequestMethod::present($governmentId, $path);
        if (is_array(old('request_channel'))) $legacyDraft['channel'] = array_replace($legacyDraft['channel'], old('request_channel'));
        $inlineConfig = [
            'methods' => [], 'blank' => $legacyDraft,
            'saveUrl' => route($recordRoutes.'.channels', $governmentId),
        'deleteUrl' => route($recordRoutes.'.request-options.destroy', [$governmentId, '__ID__']), 'wizard' => $wizardContext, 'returnUrl' => $returnUrl,
        ];
    @endphp
    @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div x-data="inlineRequestMethods(@js($inlineConfig))" x-init="open()">
        <template x-if="draft">@include('admin.catalog.partials.request-option-form', ['inline' => true])</template>
    </div>
@else
<form method="POST" action="{{ route($recordRoutes.'.channels', $governmentId) }}" @submit.prevent="save($el)" class="request-method-form">
    @csrf
    <input type="hidden" name="_catalog_revision" value="{{ \App\Services\CatalogRevision::token($governmentId) }}">
    <div x-show="errors.length" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800"><template x-for="(error, index) in errors" :key="index"><p x-text="error"></p></template></div>
    <fieldset :disabled="saving">
        <section class="space-y-4">
            <fieldset class="space-y-2"><legend class="text-sm">Category</legend><div class="request-method-choices">
                @foreach(['outlet' => 'In Person', 'online' => 'Online', 'hybrid' => 'Other / Hybrid', 'partner' => 'Service Partner'] as $key => $label)
                    <label @if($key === 'partner') x-show="draft.channel.channel === 'partner'" @endif><input type="radio" name="request_method_category" value="{{ $key }}" x-model="draft.channel.channel" required> {{ $label }}</label>
                @endforeach
            </div></fieldset>
            <div class="request-method-columns">
                <label class="block text-sm">Provider, Office, or Service Name<input x-model="draft.channel.provider" required maxlength="255" class="admin-input mt-2" placeholder="e.g. PSAHelpline.ph"></label>
                <label class="block text-sm">Official Website or Service Link <span class="text-slate-400">(optional)</span><input type="url" x-model="draft.channel.portal_url" maxlength="2048" class="admin-input mt-2" placeholder="https://"></label>
            </div>
        </section>
        <section>
            <label class="block text-sm">What to Prepare
                <textarea x-model="draft.channel.preparation" rows="5" maxlength="10000" class="admin-input mt-2" placeholder="Describe what applicants should bring, the information they need, and any preparation reminders."></textarea>
            </label>
        </section>
        <div class="request-method-columns">
        <section class="space-y-3">
            <h4 class="font-semibold text-slate-900">Fees</h4>
            <button type="button" class="request-method-fee-summary" @click="editFees($el.nextElementSibling)" aria-label="Edit fees" x-text="feeSummary()"></button>
            <div class="space-y-3">
            <template x-for="(fee, fi) in draft.fees" :key="fee.id || 'new-' + fi">
                <div class="request-method-entry space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm text-slate-700" x-text="fee.label || 'New fee'"></span>
                        <div class="flex items-center gap-3">
                            <button type="button" class="request-method-link" @click="editFees($el.closest('.request-method-entry'))">Edit</button>
                            <button type="button" class="text-xs text-red-700" @click="removeFee(fi)">Remove Fee</button>
                        </div>
                    </div>
                    <p x-show="fee.shared" class="text-xs text-slate-500">Shared fee. Edits apply only to this provider.</p>
                    <fieldset class="space-y-3">
                        <label class="block text-sm">Fee name<input x-model="fee.label" required maxlength="255" class="admin-input mt-1"></label>
                        <fieldset class="space-y-2" :disabled="fee.type === 'options'"><legend class="text-sm">Fee type</legend><div class="request-method-choices">
                            @foreach(['fixed' => 'Fixed amount', 'range' => 'Range', 'free' => 'Free', 'varies' => 'Varies', 'options' => 'Fee options'] as $key => $label)
                                <label @if($key === 'options') x-show="fee.type === 'options'" @endif><input type="radio" :name="'fee-type-' + fi" value="{{ $key }}" x-model="fee.type"> {{ $label }}</label>
                            @endforeach
                        </div></fieldset>
                        <div class="grid gap-3 sm:grid-cols-2" x-show="['fixed', 'range'].includes(fee.type)">
                            <label class="block text-sm">Amount (PHP)<input type="number" min="0" max="99999999.99" step="0.01" x-model="fee.amount_min" :required="['fixed', 'range'].includes(fee.type)" :disabled="!['fixed', 'range'].includes(fee.type)" class="admin-input mt-1"></label>
                            <label x-show="fee.type === 'range'" class="block text-sm">Maximum (PHP)<input type="number" min="0" max="99999999.99" step="0.01" x-model="fee.amount_max" :required="fee.type === 'range'" :disabled="fee.type !== 'range'" class="admin-input mt-1"></label>
                        </div>
                        <label class="block text-sm">Fee details<textarea x-model="fee.notes" maxlength="10000" rows="2" class="admin-input mt-1"></textarea></label>
                    </fieldset>
                    <template x-for="option in fee.options" :key="option.id"><p class="text-sm text-slate-600" x-text="option.label + ' · ' + option.type + ' ' + (option.amount_min || '') + (option.notes ? ' · ' + option.notes : '')"></p></template>
                </div>
            </template>
            <button type="button" data-add-fee class="request-method-link" @click="addFee()">+ Add Fee</button>
            </div>
        </section>
        <section class="request-method-processing space-y-3">
            <h4 class="font-semibold text-slate-900">Processing Time</h4>
            <label class="block text-sm"><span class="sr-only">Estimated processing or delivery timeframe</span><textarea x-model="draft.channel.processing" maxlength="2000" rows="1" placeholder="Estimated timeframe" class="admin-input"></textarea></label>
        </section>
        </div>
        <section>
            <label class="block text-sm">Application Guide
                <textarea x-model="draft.channel.application_guide_text" rows="5" maxlength="10000" class="admin-input mt-2" placeholder="Explain how to apply or request through this provider, including the main process and helpful reminders."></textarea>
            </label>
        </section>
        <fieldset class="space-y-3"><legend class="font-semibold text-slate-900">How to Receive</legend><div class="request-method-fulfillment">
            @foreach(['pickup' => 'Office Pickup', 'delivery' => 'Delivery', 'download' => 'Download', 'email' => 'Email / Digital', 'view_online' => 'View Online'] as $key => $label)
                <label class="text-sm"><input type="checkbox" value="{{ $key }}" x-model="draft.channel.fulfillment"> {{ $label }}</label>
            @endforeach
        </div></fieldset>
        <div class="request-method-actions">
            <button type="submit" class="admin-primary" :disabled="saving" x-text="saving ? 'Saving…' : 'Save Method'">Save Method</button>
            <button type="button" class="admin-secondary" @click="cancel()">Cancel</button>
            <span x-show="dirty" class="text-xs text-amber-800" role="status">Unsaved changes</span>
            <button type="button" x-show="draft.id" @click="remove()" class="request-method-delete">Delete Method</button>
        </div>
    </fieldset>
</form>
@endif
