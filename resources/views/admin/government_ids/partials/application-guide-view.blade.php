@php
    $guideScenarios = $governmentId->requirementSets->filter(fn ($scenario) => $scenario->applicationSteps->isNotEmpty());
@endphp
@if($guideScenarios->isNotEmpty())
<section class="admin-panel mt-6 p-6" aria-label="Application Guide">
    <h2 class="text-lg font-semibold text-slate-900">Application Guide</h2>
    <div class="mt-5 space-y-5">
        @foreach($guideScenarios as $scenario)
            <div>
                <h3 class="mb-3 font-semibold text-slate-800">{{ $scenario->display_label }}</h3>
                <div class="space-y-3">
                    @foreach($scenario->applicationSteps as $step)
                        <details class="rounded-xl border border-slate-200" @if($loop->first) open @endif>
                            <summary class="cursor-pointer p-4 font-semibold text-slate-900">{{ $loop->iteration }}. {{ $step->title }}</summary>
                            <div class="space-y-5 px-5 pb-5">
                                @if(filled($step->short_description))<p class="whitespace-pre-line text-sm text-slate-600">{{ $step->short_description }}</p>@endif
                                @foreach($step->blocks as $block)
                                    @php
                                        $content = $block->content ?? [];
                                        $hasReference = match ($block->type) {
                                            'requirements' => $scenario->groups->isNotEmpty(),
                                            'fees' => $governmentId->fees->isNotEmpty() || filled($governmentId->fee),
                                            'offices' => $governmentId->offices->isNotEmpty(),
                                            'processing_time' => filled($governmentId->processing_time),
                                            default => true,
                                        };
                                    @endphp
                                    @if($hasReference)
                                    <div class="space-y-2 text-sm {{ $block->type === 'reminder' ? 'rounded-lg bg-amber-50 p-4' : '' }}">
                                        @if(filled($block->section_title))<h4 class="font-semibold text-slate-900">{{ $block->section_title }}</h4>@endif
                                        @if(in_array($block->type, ['instructions', 'checklist'], true))
                                            @if($block->type === 'instructions')<ol class="list-decimal space-y-3 pl-5">@else<ul class="list-disc space-y-3 pl-5">@endif
                                            @foreach($content['items'] ?? [] as $item)<li class="guide-content">{!! \App\Support\GuideRichText::render(is_string($item) ? $item : ($item['body'] ?? null)) !!}</li>@endforeach
                                            @if($block->type === 'instructions')</ol>@else</ul>@endif
                                        @elseif(in_array($block->type, ['reminder', 'custom'], true))
                                            <div class="guide-content">{!! \App\Support\GuideRichText::render($content['body'] ?? null) !!}</div>
                                        @elseif($block->type === 'official_link')
                                            @if(\App\Support\GuideRichText::safeUrl($content['url'] ?? null))<a class="font-medium text-blue-800 underline" href="{{ $content['url'] }}" target="_blank" rel="noopener noreferrer">{{ $content['label'] ?? $content['url'] }} ↗</a>@endif
                                            @if(filled($content['description'] ?? null))<p class="whitespace-pre-line text-slate-600">{{ $content['description'] }}</p>@endif
                                        @else
                                            <div class="guide-content">{!! \App\Support\GuideRichText::render($content['intro'] ?? null) !!}</div>
                                            @if($block->type === 'requirements')
                                                @foreach($scenario->groups as $group)
                                                    <div class="rounded-lg border border-slate-200 p-3">
                                                        @if(filled($group->title))<p class="font-semibold">{{ $group->title }}</p>@endif
                                                        @if($group->condition_type !== 'always')<p class="my-1 text-xs text-blue-800">Only if: {{ $group->condition_label }}</p>@endif
                                                        @php
                                                            $guideWays = $group->ways->isNotEmpty() ? $group->ways : collect([null]);
                                                        @endphp
                                                        @foreach($guideWays as $way)
                                                            @unless($loop->first)<p class="my-2 font-semibold">OR</p>@endunless
                                                            @php
                                                                $guideItems = $way ? $group->items->where('requirement_way_id', $way->id) : $group->items;
                                                                $needed = $way?->required_count ?? ($group->rule === 'choose_one' ? 1 : $guideItems->count());
                                                            @endphp
                                                            @if($guideItems->count() > 1)<p class="mb-1 text-slate-600">Choose {{ $needed }} from these accepted items:</p>@endif
                                                            @if($way && $way->qualification_type !== 'none')<p class="my-1 text-xs text-blue-800">{{ $way->qualification_scope === 'at_least_one' ? 'At least one selected item must:' : 'Each selected item must:' }} {{ $way->qualification_type === 'custom' ? $way->qualification_custom : (\App\Models\GovernmentIdRequirementWay::QUALIFICATIONS[$way->qualification_type] ?? '') }}</p>@endif
                                                            <ul class="list-disc space-y-2 pl-5">
                                                                @foreach($guideItems as $item)
                                                                    <li><span class="font-medium">{{ $item->display_name }}</span>
                                                                        @if($item->quantity)<span class="ml-1 text-slate-600">Quantity: {{ $item->quantity }}</span>@endif
                                                                        @if($item->submission_format !== 'not_specified')<p class="text-xs text-slate-600">{{ $item->submission_label }}@if($item->copies) · {{ $item->copies }} {{ \Illuminate\Support\Str::plural('copy', $item->copies) }}@endif</p>@endif
                                                                        @if(filled($item->instructions))<p class="whitespace-pre-line text-xs text-slate-500">{{ $item->instructions }}</p>@endif
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            @elseif($block->type === 'fees')
                                                @foreach($governmentId->fees->sortBy('sort_order') as $fee)
                                                    <div class="flex flex-wrap justify-between gap-2 rounded-lg bg-slate-50 p-3"><div><p class="font-medium">{{ $fee->label }}@if($fee->is_optional) <span class="text-xs text-slate-500">(Optional)</span>@endif</p>@if(filled($fee->notes))<p class="whitespace-pre-line text-xs text-slate-500">{{ $fee->notes }}</p>@endif</div><p class="font-semibold">@switch($fee->type)@case('fixed')₱{{ number_format((float) $fee->amount_min, 2) }}@break @case('range')₱{{ number_format((float) $fee->amount_min, 2) }}–₱{{ number_format((float) $fee->amount_max, 2) }}@break @case('free')Free @break @case('varies')Varies @break @endswitch</p></div>
                                                @endforeach
                                                @if($governmentId->fees->isEmpty())<p class="whitespace-pre-line">{{ $governmentId->fee }}</p>@endif
                                            @elseif($block->type === 'offices')
                                                @foreach($governmentId->offices as $office)
                                                    <div class="rounded-lg bg-slate-50 p-3"><p class="font-semibold">{{ $office->name }}</p><p class="text-slate-600">{{ collect([$office->address, $office->municipality, $office->province])->filter(fn ($part) => filled($part))->implode(', ') }}</p>
                                                        @foreach($office->schedules as $schedule)
                                                            @if($schedule->status === 'closed' || ($schedule->status === 'open' && $schedule->intervals->isNotEmpty()))
                                                                <p class="mt-1 text-xs text-slate-600">{{ ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$schedule->day_of_week] }}:
                                                                    @if($schedule->status === 'closed') Closed @else @foreach($schedule->intervals as $interval){{ \Carbon\Carbon::parse($interval->opens_at)->format('g:i A') }}–{{ \Carbon\Carbon::parse($interval->closes_at)->format('g:i A') }}@unless($loop->last), @endunless @endforeach @endif
                                                                </p>
                                                            @endif
                                                        @endforeach
                                                        @if(filled($office->pivot->service_notes))<p class="mt-2 whitespace-pre-line text-xs text-slate-600">{{ $office->pivot->service_notes }}</p>@endif
                                                    </div>
                                                @endforeach
                                            @elseif($block->type === 'processing_time')<p class="text-slate-700">{{ $governmentId->processing_time }}</p>@endif
                                        @endif
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif
