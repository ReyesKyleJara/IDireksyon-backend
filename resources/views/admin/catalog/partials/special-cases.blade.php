<section class="admin-panel space-y-4 p-5 sm:p-6" aria-label="Special case guides">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h2 class="text-lg font-semibold text-slate-900">Special Cases / Additional Help</h2><p class="mt-1 max-w-2xl text-sm text-slate-500">Create a separate guide for an exceptional situation. These guides help residents voluntarily; they do not change normal request options or Roadmap requirements.</p></div>
        <a class="admin-secondary" href="{{ route($recordRoutes.'.special-cases.create', $governmentId) }}">+ Add special case guide</a>
    </div>
    @if(isset($wizardRoutes) && ($step ?? null) === 'guide')
        @include('admin.catalog.partials.section-visibility', ['visibilitySection' => 'troubleshooting', 'visibilityLabel' => 'Special Cases / Additional Help', 'visibilityForm' => 'catalog-step-form'])
    @endif
    @forelse(\App\Services\CatalogSpecialCases::all($governmentId) as $case)
        <article class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-slate-200 p-4">
            <div class="min-w-0"><h3 class="font-medium">{{ $case['title'] }}</h3><p class="mt-1 text-sm text-slate-500">{{ $case['description'] }}</p><p class="mt-1 text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $case['status'] ?? 'draft')) }} · Separate assistance guide</p></div>
            <div class="flex flex-wrap items-center gap-3">
                <a class="admin-secondary" href="{{ route($recordRoutes.'.special-cases.edit', [$governmentId, $case['id']]) }}">Edit guide</a>
                <form method="POST" action="{{ route($recordRoutes.'.special-cases.action', $governmentId) }}" class="flex items-center gap-3">
                    @csrf
                    <input type="hidden" name="_catalog_revision" value="{{ \App\Services\CatalogRevision::token($governmentId) }}">
                    <input type="hidden" name="case_id" value="{{ $case['id'] }}">
                    <button class="text-sm" name="action" value="up" aria-label="Move {{ $case['title'] }} up" @disabled($loop->first)>↑</button>
                    <button class="text-sm" name="action" value="down" aria-label="Move {{ $case['title'] }} down" @disabled($loop->last)>↓</button>
                    <button class="text-sm text-red-700" name="action" value="delete" onclick="return confirm('Delete this special case guide? Normal channels and catalog records will be kept.')">Delete guide</button>
                </form>
            </div>
        </article>
    @empty
        <p class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500">No dedicated guides yet. Add one when a situation needs its own requirements, steps, fees, or offices.</p>
    @endforelse
</section>
