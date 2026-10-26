@php
    $summary = [
        'basic' => [
            'Basic Information',
            '✓ Added'
        ],

        'requirements' => [
            'Requirements',
            $counts['groups']
                ? $counts['groups'].' '.\Illuminate\Support\Str::plural(
                    'group',
                    $counts['groups']
                ).' / '.$counts['items'].' '.\Illuminate\Support\Str::plural(
                    'item',
                    $counts['items']
                )
                : 'Not added yet'
        ],

        'fees' => [
            'Fees',
            $counts['fees']
                ? $counts['fees'].' '.\Illuminate\Support\Str::plural(
                    'fee',
                    $counts['fees']
                )
                : 'Not added yet'
        ],

        'guide' => [
            'Application Guide',
            $counts['guide']
                ? $counts['guide'].' '.\Illuminate\Support\Str::plural(
                    'step',
                    $counts['guide']
                )
                : 'Not added yet'
        ],

        'offices' => [
            'Offices',
            $counts['offices']
                ? $counts['offices'].' linked '.\Illuminate\Support\Str::plural(
                    'office',
                    $counts['offices']
                )
                : 'Not added yet'
        ],
    ];
@endphp

<section class="admin-panel divide-y divide-slate-200 px-6">

    @foreach($summary as $key => [$label, $status])

        <div class="flex flex-wrap items-center justify-between gap-3 py-5">

            <div>
                <h3 class="font-semibold">
                    {{ $label }}
                </h3>

                <p class="mt-1 text-sm text-slate-600">
                    {{ $status }}
                </p>
            </div>

            <a
                class="admin-secondary"
                href="{{ route(
                    $wizardRoutes.'.show',
                    [$governmentId, $key]
                ) }}"
                aria-label="Return to {{ $label }}"
            >
                Review / change
            </a>

        </div>

    @endforeach

</section>

<p class="text-sm text-slate-600">
    These counts reflect saved data. Empty sections are allowed.
    {{ $editing ? 'Finish Editing' : 'Finish' }} opens the current {{ $wizardLabel }} details page.
</p>
@if($governmentId->fees->isNotEmpty())
<section class="space-y-3" aria-label="Fee review">
    <h3 class="font-semibold">Saved fees</h3>
    @foreach($governmentId->fees->sortBy('sort_order') as $fee)
        @include('admin.government_ids.partials.fee-display')
    @endforeach
</section>
@endif
