{{--
    Data Sources & Verification in Basic Information.
    Official sources save with the step. Agency verifications save independently. Their forms are pushed after the main
    form (stack "outside-step-form") and linked with the form="" attribute.
--}}
@use('App\Models\CatalogAgencyVerification', 'Verification')
@php
    $dsRecord = $governmentId?->exists ? $governmentId : null;
    $dsRoutes = $recordKind === 'document' ? 'admin.documents' : 'admin.government-ids';
    $base = $dsRoutes.'.research-validation.';
    $user = auth()->user();
    $agencyErrors = $errors->getBag(\App\Http\Controllers\Admin\CatalogVerificationController::ERROR_BAG);
    $openForm = session('agency_form');

    // Online Sources: a failed Basic Information save shows what was typed.
    $blankSource = ['title' => '', 'url' => '', 'checked_on' => '', 'scope' => '', 'section' => '', 'published_on' => ''];
    $savedSources = $governmentId?->research_metadata['sources'] ?? [];
    $sourceRows = collect(session()->hasOldInput('research_sources_present') ? old('research_metadata.sources', []) : $savedSources)
        ->filter(fn ($row) => is_array($row))->map(fn ($row) => array_replace($blankSource, array_intersect_key($row, $blankSource)))->values()->all();
    $sourceError = $errors->first('research_metadata.sources') ?: $errors->first('research_metadata.sources.*');
    $approvedVerification = null;

    if ($dsRecord) {
        $service = app(\App\Services\CatalogAgencyValidation::class);
        $evaluation = $service->evaluate($dsRecord);
        $approvedVerification = $service->verifiedInformation($dsRecord)['verification'];
        $rows = $dsRecord->agencyVerifications()->reorder()->latest('id')->get();
        $pathLabels = $service->pathLabels($dsRecord);
        $agencies = \App\Models\Agency::orderBy('name')->get(['id', 'name', 'acronym']);
        $offices = \App\Models\Office::orderBy('name')->get(['id', 'name']);
        $status = $evaluation['status'];
        $currentIds = $evaluation['current']->pluck('id')->all();
        $outdatedIds = $evaluation['outdated']->pluck('id')->all();
        $correctionIds = $evaluation['corrections_pending']->pluck('id')->all();
        $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('j M Y') : 'Not recorded';
        $aspects = fn ($keys) => collect($keys ?? [])->map(fn ($key) => Verification::ASPECT_LABELS[$key] ?? $key)->implode(', ');
        $notCovered = fn ($row) => array_values(array_diff(Verification::ASPECTS, $row->confirmed_aspects ?? []));
        $paths = fn ($row) => $row->scope_request_paths === null ? 'All ways to apply'
            : collect($row->scope_request_paths)->map(fn ($id) => $pathLabels[$id] ?? 'Removed way to apply #'.$id)->implode(', ');
        $method = fn ($row) => strtolower(Verification::METHOD_LABELS[$row->method] ?? $row->method);
        $toReview = $rows->filter(fn ($row) => $user->can('approve', $row));
        $attention = $evaluation['outdated']->isNotEmpty() || $evaluation['corrections_pending']->isNotEmpty() || $evaluation['not_confirmed_aspects'] !== [];
        $chip = function ($row) use ($currentIds, $outdatedIds, $correctionIds, $status) {
            $green = 'bg-emerald-50 text-emerald-900'; $amber = 'bg-amber-50 text-amber-900';
            $blue = 'bg-blue-50 text-blue-900'; $grey = 'bg-slate-100 text-slate-700'; $red = 'bg-red-50 text-red-800';
            if ($row->outcome === 'withdrawn') return ['Cancelled', $grey];
            if ($row->review_state === 'draft') return $row->returned_at ? ['Draft: sent back for changes', $amber] : ['Draft', $grey];
            if ($row->review_state === 'submitted' && $row->outcome === 'documented') return ['Private · save to update visibility', $grey];
            if ($row->review_state === 'submitted') return $row->outcome === 'pending' ? ['Waiting for agency reply', $blue] : ['Pending review', $blue];
            if ($row->revoked_at) return ['Revoked', $red];
            if ($row->outcome === 'documented' && $row->isActive()) return ['Saved for the app', $green];
            if (in_array($row->id, $outdatedIds, true)) return ['Outdated: hidden from residents', $amber];
            if (in_array($row->id, $correctionIds, true)) return ['Corrections needed', $amber];
            if (in_array($row->id, $currentIds, true)) return $status === 'agency_confirmed'
                ? ['Confirmed: shown to residents', $green] : ['Confirmed: hidden until issues are fixed', $amber];
            return match ($row->outcome) { 'not_confirmed' => ['Agency could not confirm', $red], 'no_response' => ['No reply from agency', $grey], default => ['Closed', $grey] };
        };
        $prefill = new Verification();
        $legacyFollowUp = false;
        if ($openForm === 'new' && session('agency_prefill_from')) {
            $from = $rows->firstWhere('id', (int) session('agency_prefill_from'));
            $legacyFollowUp = $from !== null && $from->outcome !== 'documented';
            $prefill = new Verification($from?->only(['agency_id', 'agency_name', 'office_id', 'office_label', 'scope_request_paths']) ?? []);
        }
        $formData = compact('agencyErrors', 'agencies', 'offices', 'pathLabels');
    }
@endphp
@include('admin.catalog.partials.source-app-preview-assets')
<section id="data-sources" class="admin-panel space-y-5 p-6" aria-label="Data Sources &amp; Verification"
    x-data="sourcePreview(@js($sourceRows), @js($blankSource), @js($approvedVerification), @js(Verification::DOCUMENTED_METHODS))">
    <h3 class="text-lg font-semibold">Data Sources &amp; Verification</h3>
    <div class="ds-layout">
    <div class="min-w-0 space-y-6">
    <div class="space-y-4" data-tab="online">
        <h4 class="font-semibold">Official Government Sources</h4>
        <input type="hidden" name="research_sources_present" value="1">
        <p class="text-sm text-slate-600">Official government websites you used as references. Residents see these links in the app. A website link is not an agency confirmation.</p>
        @if($sourceError)<p class="text-sm text-red-700" role="alert">{{ $sourceError }}</p>@endif
        <template x-for="(source, index) in sources" :key="index">
            <fieldset class="ds-source-row rounded-lg border border-slate-200 p-4 text-sm">
                <legend class="px-1 font-semibold" x-text="'Online source ' + (index + 1)"></legend>
                <div class="space-y-3">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block font-medium">Website or agency name
                        <input class="admin-input mt-1" :name="`research_metadata[sources][${index}][title]`" x-model="source.title" maxlength="255" required placeholder="e.g. DFA – Passport requirements"></label>
                    <label class="block font-medium">Official website link
                        <input type="url" class="admin-input mt-1" :name="`research_metadata[sources][${index}][url]`" x-model="source.url" maxlength="2048" required placeholder="https://"></label>
                    <label class="block font-medium">Date you checked the website
                        <input type="date" class="admin-input mt-1" :name="`research_metadata[sources][${index}][checked_on]`" x-model="source.checked_on" required max="{{ now('Asia/Manila')->toDateString() }}"></label>
                    <label class="block font-medium">Page or section <span class="font-normal text-slate-500">(optional)</span>
                        <input class="admin-input mt-1" :name="`research_metadata[sources][${index}][section]`" x-model="source.section" maxlength="255" placeholder="e.g. Requirements"></label>
                </div>
                <label class="block font-medium">What this website supports <span class="font-normal text-slate-500">(optional)</span>
                    <input class="admin-input mt-1" :name="`research_metadata[sources][${index}][scope]`" x-model="source.scope" maxlength="255" placeholder="e.g. Requirements and fees for first-time applicants"></label>
                {{-- No longer asked for, but a date saved earlier is kept. --}}
                <input type="hidden" :name="`research_metadata[sources][${index}][published_on]`" :value="source.published_on">
                <button type="button" class="text-red-700 hover:underline" @click="sources.splice(index, 1)">Remove this source</button>
                </div>
            </fieldset>
        </template>
        <p class="text-sm text-slate-600" x-show="sources.length === 0">No online sources yet.</p>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="admin-secondary" :disabled="sources.length >= 50" @click="sources.push({ ...blank })">+ Add online source</button>
            <span class="text-xs text-slate-500">Saved with “Save &amp; Continue” below.</span>
        </div>
    </div>

    <div class="space-y-4 text-sm" data-tab="agency">
        <h4 class="text-base font-semibold">Agency Verification</h4>
        @unless($dsRecord)
            <p class="text-slate-600">Save this {{ $recordKind === 'document' ? 'document' : 'ID' }} first to add an agency verification.</p>
        @else
            <p class="text-slate-600" data-agency-help>Enter the office, method, and date to show the verification in the app.</p>

            @if($agencyErrors->any() && ! in_array($openForm, ['new', ...$rows->pluck('id')->map(fn ($id) => (string) $id)->all()], true))
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-800" role="alert">
                    @foreach($agencyErrors->all() as $message)<p>{{ $message }}</p>@endforeach
                </div>
            @endif

            @if($toReview->isNotEmpty() || $attention)
                <ul class="space-y-1 rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-900" aria-label="Needs your attention">
                    @foreach($toReview as $row)<li>• Record #{{ $row->id }} is <strong>waiting for your review</strong>.</li>@endforeach
                    @foreach($evaluation['outdated'] as $row)<li data-outdated="{{ $row->id }}">• #{{ $row->id }} is outdated: the confirmed information changed, so residents no longer see it.</li>@endforeach
                    @foreach($evaluation['corrections_pending'] as $row)<li>• The agency’s corrections in #{{ $row->id }} are not applied yet. Fix the guide, then mark them as done.</li>@endforeach
                    @if($evaluation['not_confirmed_aspects'] !== [])<li>• The agency could not confirm: {{ $aspects($evaluation['not_confirmed_aspects']) }}.</li>@endif
                </ul>
            @endif

            {{-- New record --}}
            @can('create', Verification::class)
                <div x-data="{ open: @js($openForm === 'new') }" @agency-form-close.window="open = false">
                    <button type="button" class="admin-primary" x-show="!open" @click="draftVerification = null; $dispatch('agency-form-close'); open = true">+ Add agency verification</button>
                    <div x-show="open" @if($openForm !== 'new') x-cloak @endif>
                        <p class="mb-2 font-semibold">New agency verification</p>
                        @include($legacyFollowUp ? 'admin.catalog.partials.agency-confirmation-form' : 'admin.catalog.partials.documented-verification-form', [...$formData, 'fid' => 'agency-form-new',
                            'verification' => $prefill, 'useOld' => $openForm === 'new'])
                    </div>
                </div>
                @push('outside-step-form')
                    <form id="agency-form-new" method="POST" action="{{ route($base.'store', $dsRecord) }}">@csrf</form>
                @endpush
            @endcan

            {{-- Existing records, newest first. Nothing is ever deleted. --}}
            @forelse($rows as $row)
                @php [$chipLabel, $chipStyle] = $chip($row); $rid = (string) $row->id; @endphp
                <article class="rounded-lg border border-slate-200 p-4" data-verification="{{ $row->id }}" x-data="{ open: @js($openForm === $rid) }" @agency-form-close.window="open = false">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $chipStyle }}">{{ $chipLabel }}</span>
                        <span class="text-base font-semibold">#{{ $row->id }} · {{ $row->agency_name }}</span>
                    </div>
                    <div class="mt-2 space-y-1" x-show="!open">
                        @if($row->review_state === 'draft' && $row->outcome !== 'withdrawn')
                            <p class="text-slate-600">Draft: private, not submitted, never shown to residents.</p>
                        @endif
                        @if($row->outcome === 'pending' && $row->review_state === 'draft')
                            {{-- A draft never implies that the agency was contacted. --}}
                            <p class="text-slate-600">Entered so far: contact date {{ $row->requested_on ? $date($row->requested_on) : 'not recorded yet' }}, {{ $method($row) }}.</p>
                        @elseif($row->outcome === 'documented')
                            <p>{{ Verification::DOCUMENTED_METHODS[$row->method] ?? $row->method }} · {{ $date($row->responded_on) }}</p>
                        @elseif($row->outcome === 'pending')
                            <p>Agency contacted on {{ $date($row->requested_on) }} by {{ $method($row) }}.@if($row->submitted_at && $row->review_state === 'submitted') Submitted on {{ $date($row->submitted_at) }}.@endif</p>
                        @elseif(in_array($row->outcome, Verification::CONFIRMING, true))
                            <p>{{ ['confirmed' => 'Confirmed', 'partially_confirmed' => 'Partially confirmed', 'confirmed_with_corrections' => 'Confirmed with corrections'][$row->outcome] }} on {{ $date($row->responded_on) }}.</p>
                            <p><span class="font-medium">Confirmed:</span> {{ $aspects($row->confirmed_aspects) ?: 'None ticked yet' }}</p>
                            <p><span class="font-medium">Not covered:</span> {{ $aspects($notCovered($row)) ?: 'None' }}</p>
                        @elseif($row->outcome === 'not_confirmed')
                            <p><span class="font-medium">Could not confirm:</span> {{ $aspects($row->not_covered_aspects) }}</p>
                        @elseif($row->outcome === 'no_response')
                            <p>No reply to the request made on {{ $date($row->requested_on) }}.</p>
                        @else
                            <p class="text-slate-600">Cancelled. Kept for the record.</p>
                        @endif
                        @if($row->review_state === 'draft' && $row->returned_reason && $user->can('viewPrivate', $row))
                            <p class="text-amber-900"><span class="font-medium">Sent back because:</span> {{ $row->returned_reason }}</p>
                        @endif
                        @if($row->outcome !== 'documented' && $row->review_state === 'submitted' && (int) $row->recorded_by === (int) $user->id && $row->outcome !== 'pending')
                            <p class="text-slate-600">Another super admin must approve this.</p>
                        @endif
                    </div>

                    @can('update', $row)
                        <div x-show="open" @if($openForm !== $rid) x-cloak @endif class="mt-3">
                            @if($openForm === $rid && session('warning'))<p class="mb-2 text-amber-900">{{ session('warning') }}</p>@endif
                            @include($row->outcome === 'documented' ? 'admin.catalog.partials.documented-verification-form' : 'admin.catalog.partials.agency-confirmation-form', [...$formData, 'fid' => 'agency-form-'.$rid,
                                'verification' => $row, 'useOld' => $openForm === $rid])
                        </div>
                        @push('outside-step-form')
                            <form id="agency-form-{{ $rid }}" method="POST" action="{{ route($base.'update', [$dsRecord, $row->id]) }}">@csrf @method('PUT')</form>
                        @endpush
                    @endcan

                    <details class="mt-2" x-show="!open">
                        <summary class="cursor-pointer font-medium text-[#012877]">Show details</summary>
                        <dl class="mt-2 grid gap-1 sm:grid-cols-2">
                            <dt class="text-slate-500">Office</dt><dd>{{ $row->office_label ?: 'Not recorded' }}</dd>
                            @if($row->outcome !== 'documented')
                            <dt class="text-slate-500">Ways to apply</dt><dd>{{ $paths($row) }}</dd>
                            <dt class="text-slate-500">Description</dt><dd>“{{ $row->scope }}”</dd>
                            <dt class="text-slate-500">Contact</dt><dd>{{ Verification::METHOD_LABELS[$row->method] ?? $row->method }} · asked {{ $date($row->requested_on) }} · reply {{ $date($row->responded_on) }}</dd>
                            @endif
                            <dt class="text-slate-500">Recorded by</dt><dd>{{ $row->recorded_by_name }}</dd>
                            @if($row->approved_by_name)<dt class="text-slate-500">Approved in the CMS by</dt><dd>{{ $row->approved_by_name }}, {{ $date($row->approved_at) }}</dd>@endif
                        </dl>
                        @can('viewPrivate', $row)
                            <div class="mt-3 rounded-lg bg-slate-50 p-3" data-private>
                                <p class="font-semibold">🔒 Private research details: never shown to residents</p>
                                <dl class="mt-2 grid gap-1 sm:grid-cols-2">
                                    @if($row->outcome !== 'documented' || filled($row->evidence_reference))<dt class="text-slate-500">Evidence reference</dt><dd>{{ $row->evidence_reference ?: 'Not recorded' }}</dd>@endif
                                    <dt class="text-slate-500">Evidence location</dt><dd>{{ $row->evidence_location ?: 'Not recorded' }}</dd>
                                    @if($row->outcome === 'documented')<dt class="text-slate-500">Verification summary</dt><dd>{{ $row->scope ?: 'None' }}</dd>@else<dt class="text-slate-500">Job title</dt><dd>{{ $row->contact_role ?: 'Not recorded' }}</dd>@endif
                                    <dt class="text-slate-500">Notes</dt><dd>{{ $row->notes ?: 'None' }}</dd>
                                    @if($row->not_covered_note)<dt class="text-slate-500">What was not confirmed</dt><dd>{{ $row->not_covered_note }}</dd>@endif
                                    @if($row->corrections_summary)<dt class="text-slate-500">Corrections</dt><dd>{{ $row->corrections_summary }}</dd>@endif
                                    @if($row->returned_reason)<dt class="text-slate-500">Reason sent back</dt><dd>{{ $row->returned_reason }}</dd>@endif
                                    @if($row->revocation_reason)<dt class="text-slate-500">Reason revoked</dt><dd>{{ $row->revocation_reason }}</dd>@endif
                                </dl>
                            </div>
                        @endcan
                    </details>

                    {{-- Actions: only those this person may take. The server checks every one again. --}}
                    <div class="mt-3 flex flex-wrap items-center gap-2" x-show="!open">
                        @can('update', $row)
                            <button type="button" class="admin-secondary" @click="draftVerification = null; $dispatch('agency-form-close'); open = true">Edit</button>
                            @can('submit', $row)
                            <button type="submit" form="agency-submit-{{ $rid }}" class="admin-primary">Submit for review</button>
                            @push('outside-step-form')<form id="agency-submit-{{ $rid }}" method="POST" action="{{ route($base.'submit', [$dsRecord, $row->id]) }}">@csrf</form>@endpush
                            @endcan
                        @endcan
                        @can('reopen', $row)
                            <button type="submit" form="agency-reopen-{{ $rid }}" class="admin-primary">Add the agency’s reply</button>
                            @push('outside-step-form')<form id="agency-reopen-{{ $rid }}" method="POST" action="{{ route($base.'reopen', [$dsRecord, $row->id]) }}">@csrf</form>@endpush
                        @endcan
                        @if($row->outcome !== 'documented' && $row->isApproved() && $user->can('create', Verification::class))
                            <a class="admin-secondary" href="{{ route($base.'create', [$dsRecord, 'from' => $row->id]) }}">Record a new contact for the same scope</a>
                        @endif
                    </div>

                    @can('approve', $row)
                        <details class="mt-3 rounded-lg border border-slate-200 p-3" x-show="!open">
                            <summary class="cursor-pointer font-semibold text-[#012877]">Review and approve</summary>
                            <div class="mt-2 space-y-1">
                                <p class="text-slate-600">Check each item against the evidence. Only these will count as confirmed.</p>
                                <p><span class="font-medium">Confirmed aspects:</span> {{ $aspects($row->confirmed_aspects) ?: 'None' }}</p>
                                <p><span class="font-medium">Not covered:</span> {{ $aspects(in_array($row->outcome, Verification::CONFIRMING, true) ? $notCovered($row) : $row->not_covered_aspects) ?: 'None' }}</p>
                                <p><span class="font-medium">Ways to apply:</span> {{ $paths($row) }}</p>
                                <p><span class="font-medium">Description:</span> “{{ $row->scope }}”</p>
                                <p><span class="font-medium">Evidence reference:</span> {{ $row->evidence_reference ?: 'Not recorded' }}</p>
                                <p><span class="font-medium">Confirmation date:</span> {{ $date($row->responded_on) }}</p>
                                <label class="flex items-start gap-2 pt-2"><input type="checkbox" form="agency-approve-{{ $rid }}" name="reviewed" value="1" required class="mt-1">
                                    <span>I checked the aspects, ways to apply, description, evidence and date above.</span></label>
                                <button type="submit" form="agency-approve-{{ $rid }}" class="admin-primary mt-2">Approve</button>
                            </div>
                        </details>
                        @push('outside-step-form')<form id="agency-approve-{{ $rid }}" method="POST" action="{{ route($base.'approve', [$dsRecord, $row->id]) }}">@csrf</form>@endpush
                    @endcan
                    @can('returnToDraft', $row)
                        <details class="mt-2 rounded-lg border border-slate-200 p-3" x-show="!open">
                            <summary class="cursor-pointer font-medium">Send back to the recorder</summary>
                            <label class="mt-2 block font-medium">What needs to change? <span class="font-normal text-slate-500">(at least 10 characters)</span>
                                <textarea class="admin-input mt-1" form="agency-return-{{ $rid }}" name="reason" rows="2" minlength="10" maxlength="2000" required></textarea></label>
                            <button type="submit" form="agency-return-{{ $rid }}" class="admin-secondary mt-2">Send back</button>
                        </details>
                        @push('outside-step-form')<form id="agency-return-{{ $rid }}" method="POST" action="{{ route($base.'return', [$dsRecord, $row->id]) }}">@csrf</form>@endpush
                    @endcan
                    @can('markCorrectionsApplied', $row)
                        <div class="mt-2 rounded-lg border border-slate-200 p-3" x-show="!open">
                            <label class="flex items-start gap-2"><input type="checkbox" form="agency-corrections-{{ $rid }}" name="corrections_done" value="1" required class="mt-1">
                                <span>I have corrected the guide as the agency said.</span></label>
                            <button type="submit" form="agency-corrections-{{ $rid }}" class="admin-secondary mt-2">Mark corrections as done</button>
                        </div>
                        @push('outside-step-form')<form id="agency-corrections-{{ $rid }}" method="POST" action="{{ route($base.'corrections-applied', [$dsRecord, $row->id]) }}">@csrf</form>@endpush
                    @endcan
                    @can('revoke', $row)
                        <details class="mt-2 rounded-lg border border-slate-200 p-3" x-show="!open">
                            <summary class="cursor-pointer font-medium text-red-700">Revoke this confirmation</summary>
                            <p class="mt-2 text-slate-600">Residents will stop seeing it. The record stays in the history.</p>
                            <label class="mt-1 block font-medium">Why is it no longer valid? <span class="font-normal text-slate-500">(at least 10 characters)</span>
                                <textarea class="admin-input mt-1" form="agency-revoke-{{ $rid }}" name="reason" rows="2" minlength="10" maxlength="2000" required></textarea></label>
                            <button type="submit" form="agency-revoke-{{ $rid }}" class="admin-secondary mt-2">Revoke</button>
                        </details>
                        @push('outside-step-form')<form id="agency-revoke-{{ $rid }}" method="POST" action="{{ route($base.'revoke', [$dsRecord, $row->id]) }}">@csrf</form>@endpush
                    @endcan
                </article>
            @empty
                <p class="text-slate-600">No agency verification recorded yet.</p>
            @endforelse
        @endunless
    </div>
    </div>
    <aside class="ds-preview" data-resident-preview>
        <h4 class="mb-2 text-sm font-semibold">Live resident app preview</h4>
        <p class="mb-2 text-xs text-slate-600" x-show="draftVerification">Preview of your unsaved changes.</p>
        <p class="text-sm text-slate-600" x-show="!visibleSources.length && !shownVerification">Add an official source or an agency verification to preview this card.</p>
        <div class="ds-card" x-show="visibleSources.length || shownVerification">
            <h5 class="ds-heading"><span x-show="shownVerification" class="ds-verified" aria-hidden="true">✓</span>
                <span x-text="shownVerification ? 'Verified Information' : 'Official References'"></span>
            </h5>
            <p class="ds-secondary" x-show="shownVerification" x-text="shownVerification ? 'Last verified: ' + formatDate(shownVerification.date) : ''"></p>
            <hr class="ds-divider">
            <div x-show="visibleSources.length">
                <p class="ds-label">Official Government Source</p>
                <ul class="space-y-1">
                    <template x-for="source in visibleSources" :key="source.index">
                        <li><a class="ds-link" :href="source.url" target="_blank" rel="noopener noreferrer" x-text="source.title"></a></li>
                    </template>
                </ul>
            </div>
            <div x-show="shownVerification">
                <p class="ds-label" x-text="shownVerification?.method"></p>
                <p class="ds-entry" x-text="shownVerification?.office"></p>
            </div>
            <p class="ds-secondary">Information reviewed by the IDireksyon research team. Requirements and procedures may change.</p>
        </div>
    </aside>
    </div>
</section>
