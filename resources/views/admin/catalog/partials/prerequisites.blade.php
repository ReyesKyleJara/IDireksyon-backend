@php($prerequisiteOwner->loadMissing(['prerequisites.governmentId', 'prerequisites.document']))
@if($prerequisiteOwner->prerequisites->isNotEmpty())
<section class="admin-panel space-y-3 p-6" aria-label="Prerequisites / Dependencies">
    <h2 class="text-lg font-semibold text-slate-900">Prerequisites / Dependencies</h2>
    <ol class="list-decimal space-y-3 pl-5">
        @foreach($prerequisiteOwner->prerequisites as $prerequisite)
            <li>
                <span class="whitespace-pre-line break-words">{{ $prerequisite->display_name }}</span>
                @if(filled($prerequisite->condition))<p class="whitespace-pre-line break-words text-sm text-slate-600">Applies when: {{ $prerequisite->condition }}</p>@endif
            </li>
        @endforeach
    </ol>
</section>
@endif
