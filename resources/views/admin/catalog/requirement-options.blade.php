@php
    $optionKey = 'requirement-'.($option?->id ?? 'new');
    $restoreOption = old('_form')===$optionKey;
    $selectedGroup = $restoreOption ? old('requirement_group_id') : $option?->requirement_group_id;
    $dependency = $restoreOption ? old('is_dependency') : $option?->is_dependency;
@endphp
<div>
    <label for="{{ $optionKey }}-group" class="mb-2 block text-sm font-medium">Requirement group</label>
    <select id="{{ $optionKey }}-group" name="requirement_group_id" class="admin-input">
        <option value="">Ungrouped / not yet reviewed</option>
        @foreach($record->requirementGroups as $group)<option value="{{ $group->id }}" @selected($selectedGroup==$group->id)>{{ $group->name }} — {{ $group->rule_label }}</option>@endforeach
    </select>
</div>
@if(!$option || $option->type !== 'custom')
<label class="flex items-start gap-3 text-sm"><input type="checkbox" name="is_dependency" value="1" @checked($dependency==1) class="mt-1 rounded border-slate-300 text-[#012877]" @if(!$option) :disabled="type === 'custom'" @endif><span>Obtain this ID/document first if missing<small class="mt-1 block text-slate-500">Use only for a researched prerequisite, not an action such as personal appearance.</small></span></label>
@endif
