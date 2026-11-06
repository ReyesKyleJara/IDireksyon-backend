@php
    $sectionVisible = old('section_visibility.'.$visibilitySection, !in_array($visibilitySection, $governmentId->research_metadata['hidden_sections'] ?? [], true));
@endphp
<div class="flex flex-wrap items-center justify-between gap-2 text-sm" aria-label="{{ $visibilityLabel }} visibility">
    <label class="inline-flex cursor-pointer items-center gap-2 text-slate-600">
        <input type="hidden" name="section_visibility[{{ $visibilitySection }}]" value="0" @isset($visibilityForm) form="{{ $visibilityForm }}" @endisset>
        <input type="checkbox" name="section_visibility[{{ $visibilitySection }}]" value="1" @checked($sectionVisible) @isset($visibilityForm) form="{{ $visibilityForm }}" @endisset class="rounded border-slate-300 text-[#012877] focus:ring-[#012877]">
        Show {{ $visibilityLabel }} to residents
    </label>
</div>
