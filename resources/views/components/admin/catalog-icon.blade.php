@props(['document' => false])
<svg {{ $attributes->merge(['class' => 'h-6 w-6']) }} aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
    @if($document)
        <path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z" />
        <path d="M14 3v5h5M9 12h6M9 16h6" />
    @else
        <rect x="3" y="5" width="18" height="14" rx="2" />
        <circle cx="8" cy="11" r="2" /><path d="M5 16c1-3 5-3 6 0M14 10h4M14 14h3" />
    @endif
</svg>
