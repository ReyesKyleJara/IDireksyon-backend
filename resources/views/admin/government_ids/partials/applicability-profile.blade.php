@php($applicability = \App\Support\ApplicabilityProfileSummary::forProfile($set))
<div class="space-y-2">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Applies To</p>
    <p class="break-words font-semibold text-slate-900">{{ $applicability['summary'] }}</p>
    <p @class(['text-sm font-medium', 'text-amber-800' => $applicability['needs_rule_setup'], 'text-slate-600' => !$applicability['needs_rule_setup']])>{{ $applicability['status'] }}</p>
    <p class="text-sm text-slate-600">{{ $applicability['explanation'] }}</p>
</div>
