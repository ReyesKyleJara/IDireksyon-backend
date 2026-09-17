@php
    $definition = app(\App\Services\CatalogDefinition::class);
    $directoryIssues = $definition->directoryIssues($record);
    $ruleIssues = $definition->sequencingIssues($record);
@endphp
<div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 text-sm">
    <p><span class="font-semibold">Directory:</span> {{ $record->is_published && !$directoryIssues ? 'Approved for directory use' : 'Hidden from residents' }} <span class="mx-2 text-slate-300">·</span> <span class="font-semibold">Requirement rules:</span> {{ $ruleIssues ? 'Need review' : 'Reviewed' }}</p>
    @if($ruleIssues)
        <details class="mt-2 text-slate-500"><summary class="cursor-pointer">What still needs attention?</summary><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($ruleIssues as $issue)<li>{{ $issue }}</li>@endforeach</ul></details>
    @endif
    <p class="mt-2 text-xs text-slate-500">These checks prepare content for integration. They do not generate a resident roadmap.</p>
</div>
