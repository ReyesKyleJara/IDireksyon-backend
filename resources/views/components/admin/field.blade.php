@props(['name', 'label', 'value' => '', 'type' => 'text', 'required' => false, 'hint' => null, 'maxlength' => null, 'id' => null, 'min' => null, 'max' => null, 'step' => null])
@php($fieldId = $id ?? $name)
<div {{ $attributes }}>
    <label for="{{ $fieldId }}" class="mb-2 block text-sm font-medium text-slate-800">
        {{ $label }} @if($required)<span class="text-[#012877]" aria-hidden="true">*</span><span class="sr-only">(required)</span>@endif
    </label>
    @if($type === 'textarea')
        <textarea id="{{ $fieldId }}" name="{{ $name }}" rows="3" class="admin-input" @required($required)
            @if($maxlength) maxlength="{{ $maxlength }}" @endif
            @error($name) aria-invalid="true" @enderror aria-describedby="{{ $fieldId }}-help">{{ $value }}</textarea>
    @else
        <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" class="admin-input" @required($required)
            @if($maxlength) maxlength="{{ $maxlength }}" @endif
            @if($min !== null) min="{{ $min }}" @endif
            @if($step !== null) step="{{ $step }}" @endif
            @if($max !== null) max="{{ $max }}" @elseif($type === 'date') max="{{ now()->toDateString() }}" @endif
            @error($name) aria-invalid="true" @enderror aria-describedby="{{ $fieldId }}-help">
    @endif
    <div id="{{ $fieldId }}-help">
        @if($hint)<p class="mt-2 text-xs leading-relaxed text-slate-500">{{ $hint }}</p>@endif
        @error($name)<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
