@php
    $wizardContext = $editing ? 'edit' : 'create';
    $inlineConfig = [
        'methods' => $governmentId->requirementSets->map(fn ($set) => \App\Services\InlineRequestMethod::present($governmentId, $set))->values()->all(),
        'blank' => \App\Services\InlineRequestMethod::present($governmentId),
        'saveUrl' => route($recordRoutes.'.channels', $governmentId),
        'deleteUrl' => route($recordRoutes.'.request-options.destroy', [$governmentId, '__ID__']), 'wizard' => $wizardContext,
        'returnUrl' => route($wizardRoutes.'.show', [$governmentId, 'channels']),
    ];
@endphp
@include('admin.catalog.partials.request-method-styles')
<section class="request-methods" aria-label="Request methods" x-data="inlineRequestMethods(@js($inlineConfig))">
    <h2 class="request-methods-title">Request Methods</h2>
    @foreach(['outlet' => 'In Person', 'online' => 'Online', 'hybrid' => 'Other / Hybrid'] as $category => $label)
        <div x-cloak x-show="providers('{{ $category }}').length" class="request-method-category">
            <h3 class="request-method-category-heading">
                <svg class="request-method-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    @if($category === 'online')
                        <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c5 5 5 13 0 18-5-5-5-13 0-18Z"/>
                    @elseif($category === 'outlet')
                        <path d="M3 21h18M5 21V5a2 2 0 0 1 2-2h5v18m0-13h5a2 2 0 0 1 2 2v11M8 7h1m-1 4h1m-1 4h1"/>
                    @else
                        <rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/><path d="M14 6h4v4M6 14v4h4"/>
                    @endif
                </svg>
                {{ $label }}
                <span class="request-method-count" x-text="providers('{{ $category }}').length + (providers('{{ $category }}').length === 1 ? ' provider' : ' providers')"></span>
            </h3>
            <template x-for="method in providers('{{ $category }}')" :key="method.id">
                <article class="request-method-card">
                    <button type="button" class="request-method-toggle" :aria-expanded="active === method.id" :aria-controls="'request-method-' + method.id" :disabled="saving" @click="open(method)">
                        <span class="break-words" x-text="method.channel.provider || method.channel.label"></span>
                        <svg class="request-method-chevron" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m5 7.5 5 5 5-5"/></svg>
                    </button>
                    <template x-if="active === method.id">
                        <div class="request-method-body" :id="'request-method-' + method.id">@include('admin.catalog.partials.request-option-form', ['inline' => true])</div>
                    </template>
                </article>
            </template>
        </div>
    @endforeach
    <div x-cloak x-show="providers('unconfigured').length" class="request-method-legacy">
        <p>Finish setting up an existing request option. Its saved content will stay connected.</p>
        <template x-for="(method, index) in providers('unconfigured')" :key="method.id">
            <article class="request-method-card">
                <button type="button" class="request-method-toggle" :aria-expanded="active === method.id" :aria-controls="'request-method-' + method.id" :disabled="saving" @click="open(method)">
                    <span x-text="method.channel.provider ? 'Configure ' + method.channel.provider : 'Configure existing option' + (providers('unconfigured').length > 1 ? ' ' + (index + 1) : '')"></span>
                    <svg class="request-method-chevron" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m5 7.5 5 5 5-5"/></svg>
                </button>
                <template x-if="active === method.id">
                    <div class="request-method-body" :id="'request-method-' + method.id">@include('admin.catalog.partials.request-option-form', ['inline' => true])</div>
                </template>
            </article>
        </template>
    </div>
    <p x-show="!methods.length && active !== 'new'" class="text-sm text-slate-500">No request methods added yet.</p>
    <template x-if="active === 'new'">
        <div class="request-method-new"><h3 class="mb-4 text-sm font-semibold text-slate-800">New Request Method</h3>@include('admin.catalog.partials.request-option-form', ['inline' => true])</div>
    </template>
    <button type="button" class="request-method-add" @click="open()" :disabled="saving" x-show="active !== 'new'">+ Add Request Method</button>
    <noscript><p class="text-sm text-amber-800">Enable JavaScript to edit request methods.</p></noscript>
</section>
