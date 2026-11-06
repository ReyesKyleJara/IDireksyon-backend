@php
    $activePath = $governmentId->requirementSets->firstWhere('id', $selectedPath ?? null);
    $configuredMethods = $governmentId->requirementSets->filter(fn ($path) => \App\Support\RequestMethodLabel::configured($path));
    $selectableMethods = $configuredMethods;
    if ($configuredMethods->isNotEmpty() && $activePath && !$configuredMethods->contains('id', $activePath->id)) {
        $selectableMethods = $configuredMethods->concat([$activePath]);
    }
@endphp
<div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm" aria-label="Request method context">
    @if($configuredMethods->isEmpty())
        <p class="text-slate-500">Create a request method in Step 2 first.</p>
    @elseif($configuredMethods->count() > 1)
        <label for="channel-context" class="font-medium text-slate-700">Editing Request Method:</label>
        <select id="channel-context" class="admin-input w-auto max-w-full py-1.5 text-sm" onchange="window.location.assign(this.value)">
            @foreach($selectableMethods as $path)
                <option value="{{ route($wizardRoutes.'.show', [$governmentId, $step, 'path' => $path->id]) }}" @selected(($selectedPath ?? null) === $path->id)>{{ \App\Support\RequestMethodLabel::label($path, $recordKind === 'document') }}</option>
            @endforeach
        </select>
    @else
        <p class="text-slate-700"><span class="font-medium">Editing Request Method:</span> {{ \App\Support\RequestMethodLabel::label($activePath, $recordKind === 'document') }}</p>
    @endif
    <a class="text-xs text-slate-500 underline underline-offset-2 hover:text-slate-800" href="{{ route($wizardRoutes.'.show', [$governmentId, 'channels', 'path' => $selectedPath ?? null]) }}">Manage Request Methods</a>
</div>
