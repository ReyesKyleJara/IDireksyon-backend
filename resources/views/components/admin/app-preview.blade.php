@props(['section'])

<aside {{ $attributes->class(['min-w-0 self-start overflow-hidden rounded-2xl border border-slate-200 bg-slate-50']) }} aria-label="{{ $section }} app preview">
    <div class="border-b border-slate-200 bg-white px-4 py-3">
        <p class="text-xs font-medium text-slate-500">Live app preview</p>
        <h3 class="text-sm font-semibold text-[#012877]">{{ $section }}</h3>
    </div>
    <div class="space-y-3 break-words p-4">{{ $slot }}</div>
</aside>
