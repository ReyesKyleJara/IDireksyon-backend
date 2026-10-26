<article class="space-y-3 rounded-xl border border-slate-200 p-4">
    <h4 class="font-semibold">{{ $fee->label }} @if($fee->is_optional)<span class="text-xs font-normal">Optional</span>@endif</h4>
    @if($fee->notes)<p class="whitespace-pre-line text-sm">{{ $fee->notes }}</p>@endif
    @if($fee->type === 'options')
        <p class="font-medium">Choose one:</p>
        @foreach($fee->options as $option)
            @unless($loop->first)<p class="text-xs font-semibold text-slate-500">OR</p>@endunless
            <div class="space-y-1 rounded-lg bg-slate-50 p-3">
                <p class="font-medium">{{ $option->label }} — {{ $option->display_amount }}</p>
                @if($option->description)<p class="whitespace-pre-line text-sm">{{ $option->description }}</p>@endif
                @if($option->notes)<p class="whitespace-pre-line text-xs text-slate-600">{{ $option->notes }}</p>@endif
            </div>
        @endforeach
    @else
        <p class="font-medium">{{ $fee->display_amount }}</p>
    @endif
</article>
